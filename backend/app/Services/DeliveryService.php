<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Payment;
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
        ?User $actor = null
    ): Delivery {
        return DB::transaction(
            function () use ($order, $driver, $actor) {

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

                // Driver must be active.
                if ($driver->user->status !== 'active') {
                    throw ValidationException::withMessages([
                        'driver' => [
                            'The selected driver is inactive.'
                        ]
                    ]);
                }

                // Create delivery.
                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'status' => 'assigned',
                ]);

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
                    'note' => 'Assigned by admin',
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
                        'en_route_for_pickup'
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

                    // Proof of delivery is required.
                    if (blank($delivery->proof_photo_url)) {
                        throw ValidationException::withMessages([
                            'proof' => [
                                'Proof of delivery is required.'
                            ]
                        ]);
                    }

                    // Payment must already be paid or verified.
                    if (
                        !Payment::where('order_id', $order->id)
                            ->whereIn(
                                'status',
                                ['paid', 'verified']
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'payment' => [
                                'A verified payment is required before delivery completion.'
                            ]
                        ]);
                    }

                    // Check gallon/container reconciliation.
                    $intake = $order->containerIntake;

                    if (
                        !$intake ||
                        $intake->accepted_count < 1 ||
                        $intake->returned_count !== 0
                    ) {
                        throw ValidationException::withMessages([
                            'intake' => [
                                'Gallon reconciliation is incomplete.'
                            ]
                        ]);
                    }

                    // Mark all accepted gallons as returned/reconciled.
                    $intake->update([
                        'returned_count' => $intake->accepted_count
                    ]);

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
