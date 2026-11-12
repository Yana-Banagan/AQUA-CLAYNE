<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\PayMongoController;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefillQueueEntry;
use App\Services\DeliveryRestrictionService;
use App\Services\OrderService;
use App\Services\OrderWorkflowService;
use App\Services\PayMongoService;
use App\Services\RealtimeEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * Create a new customer order.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'delivery_method' =>
                'nullable|in:home_delivery,station_pickup',

            'address_id' =>
                'nullable|uuid|exists:customer_addresses,id',

            'items' =>
                'required|array|min:1',

            'items.*.product_id' =>
                'required|uuid|exists:products,id',

            'items.*.quantity' =>
                'required|integer|min:1',

            'container_ownership' =>
                'sometimes|in:customer_owned,new_gallon',

            'expected_empty_gallons' =>
                'required|integer|min:0|max:100',

            /*
             * STEP 13
             *
             * Payment method is optional.
             * If omitted, cash will be used.
             */
            'payment_method' =>
                'nullable|in:cash,debt,online',

            'client_request_id' =>
                'nullable|string|max:80',

            'notes' =>
                'nullable|string|max:2000',
        ]);

        $deliveryMethod = $data['delivery_method'] ?? 'home_delivery';
        $data['delivery_method'] = $deliveryMethod;

        /*
         * Validate address requirements based on delivery method.
         */
        if ($deliveryMethod === 'home_delivery') {
            if (empty($data['address_id'])) {
                return $this->error('A valid delivery address is required for Home Delivery.', 422);
            }

            $address = $request->user()->customer->addresses()->find($data['address_id']);
            if (!$address) {
                return $this->error('The selected address does not belong to you.', 403);
            }

            // Verify destination within Gatid, Duhat, or Bubukal in Santa Cruz, Laguna
            if (!DeliveryRestrictionService::isDeliveryAddressAllowed($address)) {
                return $this->error(
                    DeliveryRestrictionService::RESTRICTION_MESSAGE,
                    422
                );
            }
        } else {
            // For Station Pickup, destination is the station itself
            $data['address_id'] = null;
        }

        /*
         * Default container ownership.
         */
        $data['container_ownership'] =
            $data['container_ownership'] ??
            'customer_owned';

        /*
         * Default payment method to cash.
         */
        $data['payment_method'] =
            $data['payment_method'] ??
            'cash';

        // Payment method validation: Allow online and cash on delivery.
        // Debt requires credit verification.
        if (($data['payment_method'] ?? '') === 'debt') {
            $hasCompletedPayment = Payment::query()
                ->where('status', 'paid')
                ->whereHas('order', fn ($query) => $query->where(
                    'customer_id', $request->user()->customer->id
                ))
                ->exists();
            if (!$hasCompletedPayment) {
                return $this->error('Credit/Debt is only available for returning verified customers.', 422);
            }
        }

        try {

            $order = $this->orderService->create(
                $request->user()->customer,
                $data
            );
            $order->setAttribute('qr_code', $order->qrPayload());

            return $this->success(
                $order,
                'Order placed',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {

            return $this->error(
                $e->validator->errors()->first(),
                422,
                $e->errors()
            );

        } catch (\Throwable $e) {

            Log::error('Customer order creation failed.', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            /*
             * CreditService validation errors and
             * other checkout exceptions are returned
             * as HTTP 422.
             */
            return $this->error(
                'Unable to place the order. Please try again or contact the station.',
                422
            );
        }
    }

    /**
     * Get the authenticated customer's orders.
     */
    public function index(Request $request)
    {
        $customerId = $request->user()?->customer?->id;

        if ($customerId) {
            $pendingPayments = Payment::query()
                ->where('method', 'online')
                ->where('status', 'pending')
                ->whereNotNull('gateway_checkout_session_id')
                ->whereHas('order', fn ($q) => $q->where('customer_id', $customerId))
                ->with('order')
                ->get();

            if ($pendingPayments->isNotEmpty()) {
                $payMongo = app(PayMongoService::class);
                $payMongoController = app(PayMongoController::class);
                foreach ($pendingPayments as $p) {
                    if ($p->order) {
                        try {
                            $payMongoController->syncPaymentWithGateway($p, $p->order, $payMongo);
                        } catch (\Throwable $e) {
                            Log::warning('Error auto-syncing payment in OrderController@index: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        $orders = $request
            ->user()
            ->customer
            ->orders()
            ->with(
                'items.product',
                'payment',
                'address',
                'delivery',
                'containerIntake',
                'refillQueueEntry',
                'statusHistory'
            )
            ->latest()
            ->get();

        $orders->each(fn (Order $order) => $order->setAttribute('qr_code', $order->qrPayload()));

        return $this->success($orders);
    }

    public function paymentOptions(Request $request)
    {
        $hasCompletedPayment = Payment::query()
            ->where('status', 'paid')
            ->whereHas('order', fn ($query) => $query->where(
                'customer_id', $request->user()->customer->id
            ))
            ->exists();

        return $this->success([
            'first_order' => !$hasCompletedPayment,
            'methods' => $hasCompletedPayment
                ? ['online', 'cash', 'debt']
                : ['online'],
            'online_providers' => ['gcash', 'qrph', 'card'],
        ]);
    }

    /**
     * Get one customer order.
     */
    public function show(
        Request $request,
        Order $order
    ) {
        abort_unless(
            $order->customer_id ===
            $request->user()->customer->id,
            403
        );

        $payment = $order->payment;
        if (
            $payment &&
            $payment->method === 'online' &&
            $payment->status === 'pending' &&
            !empty($payment->gateway_checkout_session_id)
        ) {
            try {
                app(PayMongoController::class)->syncPaymentWithGateway(
                    $payment,
                    $order,
                    app(PayMongoService::class)
                );
                $order->refresh();
            } catch (\Throwable $e) {
                Log::warning('Error auto-syncing payment in OrderController@show: ' . $e->getMessage());
            }
        }

        $loadedOrder = $order->load(
            'items.product',
            'payment',
            'address',
            'delivery',
            'containerIntake',
            'refillQueueEntry',
            'statusHistory'
        );

        $responseArray = $loadedOrder->toArray();
        $responseArray['qr_code'] = $loadedOrder->qrPayload();
        $responseArray['station_location'] = [
            'name' => 'Aqua Clayne Water Refilling Station',
            'address' => config('services.station.address', 'Aqua Clayne Water Refilling Station, Batisan, Gatid, Santa Cruz, Laguna'),
            'latitude' => (float) config('services.station.latitude', 14.265111),
            'longitude' => (float) config('services.station.longitude', 121.405602),
            'operating_hours' => 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM',
        ];

        return $this->success($responseArray);
    }

    /** Return only the assigned driver's latest location for an owned order. */
    public function tracking(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->customer->id, 403);

        if ($order->isStationPickup()) {
            return $this->success([
                'is_station_pickup' => true,
                'status' => $order->status,
                'pickup_location' => [
                    'name' => 'Aqua Clayne Water Refilling Station',
                    'address' => config('services.station.address', 'Aqua Clayne Water Refilling Station, Batisan, Gatid, Santa Cruz, Laguna'),
                    'latitude' => (float) config('services.station.latitude', 14.265111),
                    'longitude' => (float) config('services.station.longitude', 121.405602),
                ],
                'message' => $order->status === 'ready_for_pickup'
                    ? 'Your order is ready for pickup at the Aqua Clayne station!'
                    : 'Your order is being prepared for pickup at the Aqua Clayne station.',
            ]);
        }

        $delivery = $order->delivery;

        return $this->success([
            'is_station_pickup' => false,
            'delivery_id' => $delivery?->id,
            'status' => $delivery?->status ?? $order->status,
            'driver_latitude' => $delivery?->driver_latitude,
            'driver_longitude' => $delivery?->driver_longitude,
            'location_updated_at' => $delivery?->location_updated_at,
            'destination_latitude' => $order->address?->latitude,
            'destination_longitude' => $order->address?->longitude,
        ]);
    }

    /**
     * Update delivery method for a pending order.
     * Prevents switching once processing has begun.
     */
    public function updateDeliveryMethod(Request $request, Order $order)
    {
        abort_unless(
            $order->customer_id === $request->user()->customer->id,
            403
        );

        if ($order->status !== 'pending') {
            return $this->error(
                'Cannot change delivery method once order processing has begun.',
                422
            );
        }

        $data = $request->validate([
            'delivery_method' => 'required|in:home_delivery,station_pickup',
            'address_id' => 'required_if:delivery_method,home_delivery|nullable|uuid|exists:customer_addresses,id',
        ]);

        $newMethod = $data['delivery_method'];
        $oldMethod = $order->delivery_method ?? 'home_delivery';

        if ($newMethod === $oldMethod && ($newMethod === 'station_pickup' || $order->address_id === ($data['address_id'] ?? null))) {
            return $this->success(
                $order->load('items.product', 'payment', 'address', 'statusHistory'),
                'Delivery method unchanged.'
            );
        }

        return DB::transaction(function () use ($request, $order, $data, $newMethod) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'pending') {
                return $this->error('Cannot change delivery method once order processing has begun.', 422);
            }

            $order->loadMissing('items');
            $itemsSubtotal = 0;
            foreach ($order->items as $item) {
                $itemsSubtotal += ((float) $item->unit_price * (int) $item->quantity);
            }
            if ($itemsSubtotal <= 0) {
                $itemsSubtotal = max(0, (float) $order->total_amount - (float) ($order->delivery_fee ?? 0));
            }

            if ($newMethod === 'home_delivery') {
                $address = $request->user()->customer->addresses()->find($data['address_id']);
                if (!$address) {
                    return $this->error('The selected address does not belong to you.', 403);
                }

                if (!DeliveryRestrictionService::isDeliveryAddressAllowed($address)) {
                    return $this->error(
                        DeliveryRestrictionService::RESTRICTION_MESSAGE,
                        422
                    );
                }

                $newFee = 5.00;
                $newTotal = round($itemsSubtotal + $newFee, 2);

                $order->update([
                    'delivery_method' => 'home_delivery',
                    'delivery_fee' => $newFee,
                    'address_id' => $address->id,
                    'total_amount' => $newTotal,
                ]);
            } else {
                $newFee = 0.00;
                $newTotal = round($itemsSubtotal, 2);

                $order->update([
                    'delivery_method' => 'station_pickup',
                    'delivery_fee' => $newFee,
                    'address_id' => null,
                    'total_amount' => $newTotal,
                ]);
            }

            // Update pending payment amount if unpaid
            if ($order->payment && $order->payment->status === 'pending') {
                $order->payment->update([
                    'amount' => $newTotal,
                    'gateway_checkout_session_id' => null,
                    'gateway_checkout_idempotency_key' => null,
                ]);
            }

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'order.delivery_method_changed',
                'table_name' => 'orders',
                'record_id' => $order->id,
            ]);

            app(RealtimeEventService::class)->emit('order', $order->id, 'updated', $order->status);

            return $this->success(
                $order->fresh(['items.product', 'payment', 'address', 'statusHistory']),
                'Delivery method updated to ' . ($newMethod === 'station_pickup' ? 'Station Pickup' : 'Home Delivery') . '.'
            );
        });
    }

    /**
     * Cancel a pending customer order.
     */
    public function cancel(
        Request $request,
        Order $order,
        OrderWorkflowService $workflow
    ) {
        abort_unless(
            $order->customer_id ===
            $request->user()->customer->id,
            403
        );

        $data = $request->validate([
            'reason' =>
                'nullable|string|max:1000',
        ]);

        if ($order->status !== 'pending') {
            return $this->error(
                'This order can no longer be cancelled by the customer.',
                422
            );
        }

        return $this->success(
            $workflow->transition(
                $order,
                'cancelled',
                $request->user(),
                $data['reason'] ?? null
            ),
            'Order cancelled.'
        );
    }

    /**
     * Get queue details for a customer order.
     */
    public function queue(Request $request, Order $order)
    {
        abort_unless(
            $order->customer_id === $request->user()->customer->id,
            403
        );

        $order->load(['refillQueueEntry', 'containerIntake']);
        $entry = $order->refillQueueEntry;

        if (!$entry || !in_array($entry->status, ['waiting', 'in_progress'], true)) {
            return $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'in_queue' => false,
                'position' => 0,
                'queue_number' => $entry?->queue_number ? (string) $entry->queue_number : '-',
                'status' => $entry?->status ? strtoupper($entry->status) : 'NOT_IN_QUEUE',
                'order_status' => $order->status,
                'estimated_wait_minutes' => 0,
                'expected_empty_gallons' => $order->expected_empty_gallons,
                'received_empty_gallons' => $order->received_empty_gallons ?? $order->containerIntake?->received_count,
                'accepted_empty_gallons' => $order->accepted_empty_gallons ?? $order->containerIntake?->accepted_count,
            ]);
        }

        // Calculate FIFO position
        if ($entry->status === 'in_progress') {
            $position = 1;
            $estimatedWait = 5;
        } else {
            $inProgressCount = RefillQueueEntry::where('status', 'in_progress')->count();
            $waitingAhead = RefillQueueEntry::where('status', 'waiting')
                ->where(function ($q) use ($entry) {
                    $q->where('priority', '>', $entry->priority)
                      ->orWhere(function ($sq) use ($entry) {
                          $sq->where('priority', $entry->priority)
                             ->where('queue_number', '<', $entry->queue_number);
                      });
                })
                ->count();
            $position = $inProgressCount + $waitingAhead + 1;
            $estimatedWait = max(5, ($position - 1) * 15 + 10);
        }

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'in_queue' => true,
            'position' => $position,
            'queue_number' => (string) $entry->queue_number,
            'status' => strtoupper($entry->status),
            'order_status' => $order->status,
            'estimated_wait_minutes' => $estimatedWait,
            'expected_empty_gallons' => $order->expected_empty_gallons,
            'received_empty_gallons' => $order->received_empty_gallons ?? $order->containerIntake?->received_count,
            'accepted_empty_gallons' => $order->accepted_empty_gallons ?? $order->containerIntake?->accepted_count,
            'queued_at' => $entry->queued_at?->toIso8601String(),
        ]);
    }

    /**
     * Get active queue entry for the authenticated customer.
     */
    public function activeQueue(Request $request)
    {
        $customer = $request->user()->customer;
        $activeOrder = $customer->orders()
            ->whereHas('refillQueueEntry', function ($q) {
                $q->whereIn('status', ['waiting', 'in_progress']);
            })
            ->latest()
            ->first();

        if (!$activeOrder) {
            $latestActive = $customer->orders()
                ->whereNotIn('status', ['completed', 'delivered', 'rejected', 'cancelled', 'pickup_failed', 'delivery_failed'])
                ->latest()
                ->first();

            if ($latestActive) {
                return $this->queue($request, $latestActive);
            }

            return $this->success(null, 'No active queue entry found.');
        }

        return $this->queue($request, $activeOrder);
    }
}
