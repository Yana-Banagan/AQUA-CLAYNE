<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use App\Models\PickupBatch;
use App\Models\PickupBatchItem;
use App\Services\DeliveryService;
use App\Services\OrderWorkflowService;
use App\Services\PaymentService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {
    }

    /**
     * Get the authenticated driver's Driver record.
     */
    private function driver(Request $request): Driver
    {
        return $request->user()->driver;
    }

    /**
     * Get active/non-completed deliveries
     * belonging ONLY to the authenticated driver.
     */
    public function index(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereNotIn(
                'status',
                [
                    'delivered',
                    'failed',
                ]
            )
            ->with([
                'order.customer.user',
                'order.address',
                'order.payment',
                'order.items.product',
                'order.containerIntake',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get completed delivery history
     * belonging ONLY to the authenticated driver.
     */
    public function history(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereIn(
                'status',
                [
                    'delivered',
                    'failed',
                ]
            )
            ->with([
                'order.customer.user',
                'order.address',
                'order.payment',
                'order.items.product',
                'order.containerIntake',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get one delivery.
     *
     * The delivery MUST belong to
     * the authenticated driver.
     */
    public function show(
        Request $request,
        $delivery,
        SupabaseStorageService $storage
    ) {
        $driver = $this->driver($request);

        if ($delivery instanceof Delivery) {
            $deliveryModel = $delivery;
        } else {
            $deliveryModel = Delivery::where('id', (string) $delivery)
                ->orWhere('order_id', (string) $delivery)
                ->first();
        }

        abort_if(!$deliveryModel, 404, 'Delivery not found.');
        abort_if(
            $deliveryModel->driver_id !== $driver->id,
            403,
            'You are not authorized to view this delivery.'
        );

        $data = $deliveryModel
            ->load(
                'order.items.product',
                'order.customer.user',
                'order.address',
                'order.payment',
                'order.containerIntake',
                'statusHistory'
            )
            ->toArray();

        /*
         * Generate signed delivery proof URL.
         */
        if ($delivery->proof_photo_url) {
            try {
                $data['proof_photo_signed_url'] =
                    $storage->signedUrl(
                        'delivery-proofs',
                        $delivery->proof_photo_url
                    );
            } catch (\Throwable) {
                $data['proof_photo_signed_url'] = null;
            }
        }

        /*
         * Generate signed signature URL.
         */
        if ($delivery->signature_url) {
            try {
                $data['signature_signed_url'] =
                    $storage->signedUrl(
                        'delivery-proofs',
                        $delivery->signature_url
                    );
            } catch (\Throwable) {
                $data['signature_signed_url'] = null;
            }
        }

        return $this->success($data);
    }

    /**
     * Update delivery status.
     *
     * The delivery MUST belong to
     * the authenticated driver.
     */
    public function updateStatus(
        Request $request,
        Delivery $delivery
    ) {
        $driver = $this->driver($request);

        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to update this delivery.'
        );

        $data = $request->validate([
            'status' => [
                'required',
                'in:en_route_for_pickup,picked_up,at_station,out_for_delivery,delivered,failed',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'eta' => [
                'nullable',
                'date',
            ],
        ]);

        $updated =
            $this->deliveryService->updateStatus(
                $delivery,
                $data['status'],
                $data['note'] ?? null,
                $data['eta'] ?? null,
                $request->user()
            );

        return $this->success($updated);
    }

    /** Store the latest GPS fix for customer order tracking. */
    public function updateLocation(Request $request, Delivery $delivery)
    {
        abort_if($delivery->driver_id !== $this->driver($request)->id, 403);
        abort_unless(
            in_array(
                $delivery->status,
                ['assigned', 'en_route_for_pickup', 'picked_up', 'at_station', 'ready_for_delivery', 'out_for_delivery'],
                true
            ),
            422,
            'Location sharing is available while navigating an active delivery.'
        );

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $delivery->forceFill([
            'driver_latitude' => $data['latitude'],
            'driver_longitude' => $data['longitude'],
            'location_updated_at' => now(),
        ])->save();

        // Publish a lightweight signal; customers/admin fetch the latest
        // authenticated tracking payload after receiving it.
        app(\App\Services\RealtimeEventService::class)->emit(
            'delivery', $delivery->id, 'location_updated', $delivery->status
        );

        return $this->success(['location_updated_at' => $delivery->location_updated_at]);
    }

    /**
     * Upload proof of delivery.
     *
     * Only the assigned driver can upload
     * proof for this delivery.
     */
    public function uploadProof(
        Request $request,
        Delivery $delivery,
        SupabaseStorageService $storage
    ) {
        abort_if(
            $delivery->driver_id !==
            $this->driver($request)->id,
            403
        );

        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png',
                'max:5120',
            ],

            'signature' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png',
                'max:2048',
            ],
        ]);

        /*
         * Delivery photo.
         */
        $photo = $request->file('photo');

        $photoPath =
            "{$delivery->id}/photo." .
            $photo->extension();

        $storage->upload(
            'delivery-proofs',
            $photoPath,
            file_get_contents(
                $photo->getRealPath()
            ),
            $photo->getMimeType()
        );

        /*
         * Optional signature.
         */
        $signaturePath = null;

        if ($request->hasFile('signature')) {

            $signature =
                $request->file('signature');

            $signaturePath =
                "{$delivery->id}/signature." .
                $signature->extension();

            $storage->upload(
                'delivery-proofs',
                $signaturePath,
                file_get_contents(
                    $signature->getRealPath()
                ),
                $signature->getMimeType()
            );
        }

        /*
         * Save storage paths in the delivery.
         */
        $delivery->update([
            'proof_photo_url' =>
                $photoPath,

            'proof_status' => 'provided',

            'signature_url' =>
                $signaturePath,
        ]);

        return $this->success(
            $delivery->fresh(),
            'Proof uploaded'
        );
    }

    /**
     * STEP 14
     *
     * Collect the customer's cash payment.
     *
     * Only the driver assigned to this delivery
     * is allowed to collect its payment.
     */
    public function collectPayment(
        Request $request,
        Delivery $delivery,
        PaymentService $paymentService
    ) {
        /*
         * Get authenticated driver.
         */
        $driver = $this->driver($request);

        /*
         * Ownership protection.
         *
         * A driver cannot collect payment
         * from another driver's delivery.
         */
        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to collect payment for this delivery.'
        );

        /*
         * Payment must exist and must be cash.
         */
        $payment =
            $delivery->order->payment;

        abort_if(
            !$payment ||
            $payment->method !== 'cash',
            422,
            'This order has no cash payment to collect.'
        );

        /*
         * Cash can only be collected when the
         * delivery is currently out for delivery.
         */
        abort_if(
            $delivery->status !== 'out_for_delivery',
            422,
            'Cash cannot be collected at this stage.'
        );

        /*
         * Require the amount collected from
         * the customer.
         */
        $data = $request->validate([
            'amount' =>
                'required|numeric|min:0',
        ]);

        /*
         * Require the full payment amount.
         */
        if (
            (float) $data['amount'] <
            (float) $payment->amount
        ) {
            return $this->error(
                'The full cash amount is required.',
                422
            );
        }

        /*
         * PaymentService changes the payment
         * status to paid.
         */
        $payment =
            $paymentService->collectCash(
                $payment,
                $request->user()
            );

        return $this->success(
            $payment,
            'Payment collected'
        );
    }

    /**
     * Report a delivery problem/issue.
     */
    public function reportIssue(
        Request $request,
        Delivery $delivery
    ) {
        $driver = $this->driver($request);

        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to report an issue on this delivery.'
        );

        $validated = $request->validate([
            'issue_type' => 'required|string|in:wrong_address,customer_unavailable,vehicle_problem,gallon_issue,other',
            'description' => 'required|string|min:3|max:1000',
            'mark_failed' => 'nullable|boolean',
        ]);

        $issueLabel = match($validated['issue_type']) {
            'wrong_address' => 'Wrong Address',
            'customer_unavailable' => 'Customer Unavailable',
            'vehicle_problem' => 'Vehicle Problem',
            'gallon_issue' => 'Gallon / Container Issue',
            default => 'Other Delivery Issue',
        };

        $note = "[{$issueLabel}] {$validated['description']}";

        if (!empty($validated['mark_failed'])) {
            $updated = $this->deliveryService->updateStatus(
                $delivery,
                'failed',
                $note,
                null,
                $request->user()
            );
            return $this->success($updated, 'Delivery marked as failed with reported issue.');
        }

        // Record in delivery status history and audit log without failing delivery
        \App\Models\DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'status' => $delivery->status,
            'note' => $note,
        ]);

        \App\Models\AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'delivery.issue_reported',
            'table_name' => 'deliveries',
            'record_id' => $delivery->id,
        ]);

        return $this->success($delivery->fresh(), 'Delivery issue reported successfully.');
    }

    /**
     * Get active pickup batches assigned to the authenticated driver.
     */
    public function pickupBatches(Request $request)
    {
        $driver = $this->driver($request);

        $batches = PickupBatch::where('driver_id', $driver->id)
            ->whereIn('status', ['ready', 'in_progress'])
            ->with([
                'items' => fn($q) => $q->orderBy('stop_sequence'),
                'items.order.customer.user',
                'items.order.address',
            ])
            ->latest()
            ->get();

        return $this->success($batches);
    }

    /**
     * Get single pickup batch details.
     */
    public function showPickupBatch(Request $request, PickupBatch $batch)
    {
        $driver = $this->driver($request);
        abort_if($batch->driver_id !== $driver->id, 403, 'You are not assigned to this pickup batch.');

        $batch->load([
            'items' => fn($q) => $q->orderBy('stop_sequence'),
            'items.order.customer.user',
            'items.order.address',
        ]);

        return $this->success($batch);
    }

    /**
     * Start or complete a pickup batch.
     */
    public function updatePickupBatchStatus(Request $request, PickupBatch $batch)
    {
        $driver = $this->driver($request);
        abort_if($batch->driver_id !== $driver->id, 403, 'You are not assigned to this pickup batch.');

        $data = $request->validate([
            'status' => 'required|in:in_progress,completed,cancelled',
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === 'in_progress' && !$batch->started_at) {
            $updates['started_at'] = now();
        } elseif ($data['status'] === 'completed') {
            $updates['completed_at'] = now();
        }

        $batch->update($updates);

        \App\Models\AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => "pickup_batch.{$data['status']}",
            'table_name' => 'pickup_batches',
            'record_id' => $batch->id,
        ]);

        app(\App\Services\RealtimeEventService::class)->emit(
            'delivery',
            $batch->id,
            'pickup_batch_status',
            $data['status']
        );

        return $this->success($batch->fresh());
    }

    /**
     * Driver records collection at a customer pickup stop.
     * Records expected vs received vs damaged vs accepted.
     */
    public function collectPickupStop(Request $request, PickupBatch $batch, PickupBatchItem $item)
    {
        $driver = $this->driver($request);
        abort_if($batch->driver_id !== $driver->id, 403, 'You are not assigned to this pickup batch.');
        abort_if($item->pickup_batch_id !== $batch->id, 422, 'This stop does not belong to this pickup batch.');

        $data = $request->validate([
            'received_gallons' => 'nullable|integer|min:0',
            'accepted_gallons' => 'nullable|integer|min:0',
            'collected_count' => 'nullable|integer|min:0',
            'damaged_count' => 'nullable|integer|min:0',
            'rejected_count' => 'nullable|integer|min:0',
            'status' => 'nullable|in:picked_up,failed',
            'failure_reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $status = $data['status'] ?? 'picked_up';
        $received = $data['received_gallons'] ?? $data['collected_count'] ?? $item->expected_gallons;
        $accepted = $data['accepted_gallons'] ?? $data['collected_count'] ?? $received;
        $damaged = $data['damaged_count'] ?? 0;
        $rejected = $data['rejected_count'] ?? 0;
        $failureReason = $data['failure_reason'] ?? $data['notes'] ?? null;

        $item->update([
            'received_gallons' => $received,
            'accepted_gallons' => $accepted,
            'damaged_count' => $damaged,
            'rejected_count' => $rejected,
            'status' => $status,
            'failure_reason' => $failureReason,
        ]);

        $order = $item->order;
        if ($status === 'picked_up') {
            app(OrderWorkflowService::class)->transition(
                $order,
                'picked_up',
                $request->user(),
                $data['failure_reason'] ?? null
            );
        } else {
            app(OrderWorkflowService::class)->transition(
                $order,
                'pickup_failed',
                $request->user(),
                $data['failure_reason'] ?? 'Driver marked pickup as failed.'
            );
        }

        // Check if all stops in batch are done
        $pendingStops = $batch->items()->whereIn('status', ['pending', 'arrived'])->count();
        if ($pendingStops === 0) {
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return $this->success([
            'item' => $item->fresh(),
            'batch_completed' => $pendingStops === 0,
        ], 'Pickup stop recorded.');
    }

    /**
     * Get active delivery routes assigned to authenticated driver.
     */
    public function deliveryRoutes(Request $request)
    {
        $driver = $this->driver($request);

        $routes = DeliveryRoute::where('driver_id', $driver->id)
            ->whereIn('status', ['ready', 'in_progress'])
            ->with([
                'stops' => fn($q) => $q->orderBy('stop_sequence'),
                'stops.order.customer.user',
                'stops.order.address',
                'stops.order.payment',
                'stops.delivery',
            ])
            ->latest()
            ->get();

        return $this->success($routes);
    }

    /**
     * Get single delivery route details.
     */
    public function showDeliveryRoute(Request $request, DeliveryRoute $route)
    {
        $driver = $this->driver($request);
        abort_if($route->driver_id !== $driver->id, 403, 'You are not assigned to this delivery route.');

        $route->load([
            'stops' => fn($q) => $q->orderBy('stop_sequence'),
            'stops.order.customer.user',
            'stops.order.address',
            'stops.order.payment',
            'stops.delivery',
        ]);

        return $this->success($route);
    }

    /**
     * Start or complete a delivery route.
     */
    public function updateDeliveryRouteStatus(Request $request, DeliveryRoute $route)
    {
        $driver = $this->driver($request);
        abort_if($route->driver_id !== $driver->id, 403, 'You are not assigned to this delivery route.');

        $data = $request->validate([
            'status' => 'required|in:in_progress,completed,cancelled',
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === 'in_progress' && !$route->started_at) {
            $updates['started_at'] = now();
        } elseif ($data['status'] === 'completed') {
            $updates['completed_at'] = now();
        }

        $route->update($updates);

        \App\Models\AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => "delivery_route.{$data['status']}",
            'table_name' => 'delivery_routes',
            'record_id' => $route->id,
        ]);

        app(\App\Services\RealtimeEventService::class)->emit(
            'delivery',
            $route->id,
            'delivery_route_status',
            $data['status']
        );

        return $this->success($route->fresh());
    }

    /**
     * Update delivery stop status (en_route, arrived, failed).
     */
    public function updateDeliveryStopStatus(Request $request, DeliveryRoute $route, DeliveryStop $stop)
    {
        $driver = $this->driver($request);
        abort_if($route->driver_id !== $driver->id, 403, 'You are not assigned to this delivery route.');
        abort_if($stop->delivery_route_id !== $route->id, 422, 'This stop does not belong to this delivery route.');

        $data = $request->validate([
            'status' => 'required|in:en_route,arrived,failed',
            'failure_reason' => 'nullable|string|max:1000',
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === 'arrived') {
            $updates['arrived_at'] = now();
        } elseif ($data['status'] === 'failed') {
            $updates['failure_reason'] = $data['failure_reason'] ?? null;
        }

        $stop->update($updates);

        if ($data['status'] === 'failed' && $stop->delivery) {
            $this->deliveryService->updateStatus(
                $stop->delivery,
                'failed',
                $data['failure_reason'] ?? 'Marked as failed by driver.',
                null,
                $request->user()
            );
        }

        return $this->success($stop->fresh(), 'Stop status updated.');
    }
}
