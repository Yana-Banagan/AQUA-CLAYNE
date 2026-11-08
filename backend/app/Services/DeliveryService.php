<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /**
     * Assign a confirmed order to an active driver.
     */
    public function assign(
        Order $order,
        Driver $driver,
        ?User $actor = null,
        ?string $eta = null
    ): Delivery {
        return DB::transaction(
            function () use ($order, $driver, $actor, $eta) {

                // Lock the order to prevent conflicting assignments.
                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                // Only confirmed orders can be assigned.
                if ($order->status !== 'confirmed') {
                    throw ValidationException::withMessages([
                        'order' => [
                            'Only confirmed orders can be assigned.'
                        ]
                    ]);
                }

                if ($order->isStationPickup()) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'Station pickup orders do not require driver assignment.'
                        ]
                    ]);
                }

                // Driver must be active.
                if ($driver->user->status !== 'active') {
                    throw ValidationException::withMessages([
                        'driver' => [
                            'The selected driver is inactive.'
                        ]
                    ]);
                }

                // Double assignment protection
                $existingDelivery = Delivery::where('order_id', $order->id)->first();
                if ($existingDelivery && $existingDelivery->driver_id) {
                    throw ValidationException::withMessages([
                        'delivery' => [
                            'Delivery is already assigned.'
                        ]
                    ]);
                }

                $parsedEta = null;
                if ($eta) {
                    try {
                        $parsedEta = \Illuminate\Support\Carbon::parse($eta);
                    } catch (\Throwable $e) {
                        $parsedEta = null;
                    }
                }

                // If no manual ETA was provided, automatically generate estimated travel time
                // from the official Aqua Clayne station origin to the verified delivery destination.
                if (!$parsedEta && $order->address) {
                    $lat = $order->address->latitude;
                    $lng = $order->address->longitude;
                    if (is_numeric($lat) && is_numeric($lng) && (float) $lat >= 14.22 && (float) $lat <= 14.34 && (float) $lng >= 121.37 && (float) $lng <= 121.47 && !((float) $lat === 0.0 && (float) $lng === 0.0)) {
                        $stationLat = (float) config('services.station.latitude', 14.265111);
                        $stationLng = (float) config('services.station.longitude', 121.405602);
                        $dLat = deg2rad((float) $lat - $stationLat);
                        $dLon = deg2rad((float) $lng - $stationLng);
                        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($stationLat)) * cos(deg2rad((float) $lat)) * sin($dLon / 2) * sin($dLon / 2);
                        $directDist = 6371000 * (2 * atan2(sqrt($a), sqrt(1 - $a)));
                        $roadDist = $directDist * 1.34;
                        $transitSec = (int) round($roadDist / (25 * 1000 / 3600));
                        // Estimated arrival = 15m prep buffer + transit duration
                        $parsedEta = now()->addMinutes(15)->addSeconds($transitSec);
                    }
                }

                // Create or update delivery record.
                if ($existingDelivery) {
                    $existingDelivery->update([
                        'driver_id' => $driver->id,
                        'status' => 'assigned',
                        'eta' => $parsedEta ?? $existingDelivery->eta,
                    ]);
                    $delivery = $existingDelivery;
                } else {
                    $delivery = Delivery::create([
                        'order_id' => $order->id,
                        'driver_id' => $driver->id,
                        'status' => 'assigned',
                        'eta' => $parsedEta,
                    ]);
                }

                // Update order workflow.
                app(OrderWorkflowService::class)->transition(
                    $order,
                    'assigned',
                    $actor
                );

                // Create delivery history.
                DeliveryStatusHistory::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'assigned',
                    'note' => 'Assigned to driver · Official route generated from Aqua Clayne station',
                ]);

                AuditLog::create([
                    'actor_id' => $actor?->id,
                    'action' => 'delivery.assigned',
                    'table_name' => 'deliveries',
                    'record_id' => $delivery->id,
                ]);

                // ----------------------------------------------------
                // PHASE 9 — SUPABASE REALTIME
                // ----------------------------------------------------
    
                app(RealtimeEventService::class)->emit(
                    'delivery',
                    $delivery->id,
                    'assigned',
                    $delivery->status
                );

                // ----------------------------------------------------
                // PHASE 11 — FCM NOTIFICATION TO DRIVER
                // ----------------------------------------------------
    
                app(NotificationService::class)->sendToUser(
                    $driver->user,
                    'New Delivery Assigned',
                    'You have a new delivery to complete.',
                    [
                        'entity_type' => 'delivery',
                        'entity_id' => $delivery->id,
                    ]
                );

                return $delivery;
            }
        );
    }

    /**
     * Update the status of an assigned delivery.
     */
    public function updateStatus(
        Delivery $delivery,
        string $status,
        ?string $note = null,
        ?string $eta = null,
        ?User $actor = null
    ): Delivery {
        return DB::transaction(
            function () use ($delivery, $status, $note, $eta, $actor) {

                // Lock the delivery to prevent conflicting updates.
                $delivery = Delivery::query()
                    ->lockForUpdate()
                    ->findOrFail($delivery->id);

                // ----------------------------------------------------
                // ALLOWED DELIVERY STATUS TRANSITIONS
                // ----------------------------------------------------
    
                $allowed = [
                    'assigned' => [
                        'en_route_for_pickup',
                        // The driver app records the customer gallon pickup in
                        // one action. Keep en_route_for_pickup for older clients.
                        'picked_up'
                    ],

                    'en_route_for_pickup' => [
                        'picked_up',
                        'failed'
                    ],

                    'picked_up' => [
                        'at_station'
                    ],

                    'ready_for_delivery' => [
                        'out_for_delivery'
                    ],

                    'out_for_delivery' => [
                        'delivered',
                        'failed'
                    ],
                ];

                if (
                    !in_array(
                        $status,
                        $allowed[$delivery->status] ?? [],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'This delivery transition is not allowed.'
                        ]
                    ]);
                }

                // Lock the related order.
                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($delivery->order_id);

                // ----------------------------------------------------
                // PICKED UP
                // ----------------------------------------------------
    
                if ($status === 'picked_up') {
                    app(OrderWorkflowService::class)->transition(
                        $order,
                        'picked_up',
                        $actor,
                        $note
                    );
                }

                // ----------------------------------------------------
                // OUT FOR DELIVERY
                // ----------------------------------------------------
    
                if ($status === 'out_for_delivery') {
                    app(OrderWorkflowService::class)->transition(
                        $order,
                        'out_for_delivery',
                        $actor
                    );
                }

                // ----------------------------------------------------
                // DELIVERED
                // ----------------------------------------------------
    
                if ($status === 'delivered') {

                    // Proof is optional. Persist an explicit skipped state
                    // so the admin dashboard can distinguish it from pending.

                    // ------------------------------------------------
                    // PHASE 12 — CASH PAYMENT GUARD
                    // ------------------------------------------------
    
                    $payment = $delivery->order->payment;

                    /*
                     * Cash orders must be collected before the
                     * delivery can be marked as delivered.
                     *
                     * Credit orders bypass this guard because
                     * Credit is already marked as "paid" during
                     * checkout.
                     */
                    if (
                        $payment &&
                        $payment->method === 'cash' &&
                        $payment->status !== 'paid'
                    ) {
                        throw new \Exception(
                            'Cash payment must be collected before marking as delivered.'
                        );
                    }

                    if (
                        $payment &&
                        $payment->method === 'online' &&
                        $payment->status !== 'paid'
                    ) {
                        throw new \Exception(
                            'Online payment must be confirmed as paid before marking as delivered.'
                        );
                    }

                    // ------------------------------------------------
                    // GALLON / CONTAINER RECONCILIATION
                    // ------------------------------------------------
    
                    $intake = $order->containerIntake;

                    // PostgreSQL may hydrate numeric columns as strings. Cast
                    // explicitly so a valid returned_count of "0" does not
                    // incorrectly block the driver's completion action.
                    $acceptedCount = $intake ? (int) $intake->accepted_count : 0;
                    $returnedCount = $intake ? (int) $intake->returned_count : 0;

                    if (!$intake || $acceptedCount < 1 || $returnedCount !== 0) {
                        throw ValidationException::withMessages([
                            'intake' => [
                                'Gallon reconciliation is incomplete.'
                            ]
                        ]);
                    }

                    // Mark all accepted gallons as returned/reconciled.
                    $intake->update([
                        'returned_count' => $acceptedCount
                    ]);

                    // Sync with DeliveryStop if part of a DeliveryRoute
                    $stop = \App\Models\DeliveryStop::where('order_id', $order->id)->first();
                    if ($stop) {
                        $stop->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                        ]);
                        $route = $stop->route;
                        if ($route && $route->stops()->whereNotIn('status', ['delivered', 'failed'])->count() === 0) {
                            $route->update(['status' => 'completed', 'completed_at' => now()]);
                        }
                    }

                    // Move order to delivered.
                    app(OrderWorkflowService::class)->transition(
                        $order,
                        'delivered',
                        $actor
                    );

                    // Move order to completed.
                    app(OrderWorkflowService::class)->transition(
                        $order->fresh(),
                        'completed',
                        $actor
                    );
                }

                // ----------------------------------------------------
                // FAILED
                // ----------------------------------------------------
    
                if ($status === 'failed') {

                    $failureStatus =
                        $order->status === 'out_for_delivery'
                        ? 'delivery_failed'
                        : 'pickup_failed';

                    // Sync with DeliveryStop if part of a DeliveryRoute
                    $stop = \App\Models\DeliveryStop::where('order_id', $order->id)->first();
                    if ($stop) {
                        $stop->update([
                            'status' => 'failed',
                            'failure_reason' => $note,
                        ]);
                        $route = $stop->route;
                        if ($route && $route->stops()->whereNotIn('status', ['delivered', 'failed'])->count() === 0) {
                            $route->update(['status' => 'completed', 'completed_at' => now()]);
                        }
                    }

                    app(OrderWorkflowService::class)->transition(
                        $order,
                        $failureStatus,
                        $actor,
                        $note
                    );
                }

                // ----------------------------------------------------
                // UPDATE DELIVERY
                // ----------------------------------------------------
    
                $delivery->update([
                    'status' => $status,

                    'proof_status' => $status === 'delivered'
                        ? ($delivery->proof_photo_url ? 'provided' : 'skipped')
                        : $delivery->proof_status,

                    'eta' => $eta ?? $delivery->eta,

                    'delivered_at' =>
                        $status === 'delivered'
                        ? now()
                        : $delivery->delivered_at,

                    'failure_reason' =>
                        $status === 'failed'
                        ? $note
                        : $delivery->failure_reason,
                ]);

                // ----------------------------------------------------
                // STATUS HISTORY
                // ----------------------------------------------------
    
                DeliveryStatusHistory::create([
                    'delivery_id' => $delivery->id,
                    'status' => $status,
                    'note' => $note,
                ]);

                AuditLog::create([
                    'actor_id' => $actor?->id,
                    'action' => 'delivery.status_changed',
                    'table_name' => 'deliveries',
                    'record_id' => $delivery->id,
                ]);

                // ----------------------------------------------------
                // PHASE 9 — SUPABASE REALTIME SIGNAL
                // ----------------------------------------------------
    
                app(RealtimeEventService::class)->emit(
                    'delivery',
                    $delivery->id,
                    'status_changed',
                    $status
                );

                // ----------------------------------------------------
                // PHASE 11 — CUSTOMER NOTIFICATIONS
                // ----------------------------------------------------
    
                $messages = [
                    'out_for_delivery' => [
                        'title' => 'Order Update',
                        'body' => 'Your order is on the way!',
                    ],

                    'delivered' => [
                        'title' => 'Order Update',
                        'body' => 'Your order has been delivered.',
                    ],

                    'failed' => [
                        'title' => 'Order Update',
                        'body' => 'Delivery could not be completed. We will follow up shortly.',
                    ],
                ];

                if (isset($messages[$status])) {

                    // Refresh the order/customer relationship
                    // so we are using the current data.
                    $order = $order->fresh([
                        'customer.user',
                    ]);

                    if (
                        $order->customer &&
                        $order->customer->user
                    ) {
                        app(NotificationService::class)->sendToUser(
                            $order->customer->user,
                            $messages[$status]['title'],
                            $messages[$status]['body'],
                            [
                                'entity_type' => 'delivery',
                                'entity_id' => $delivery->id,
                            ]
                        );
                    }
                }

                return $delivery->fresh();
            }
        );
    }
}
