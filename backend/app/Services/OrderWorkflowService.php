<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['assigned', 'received_at_station', 'cancelled'],
        'assigned' => ['picked_up', 'pickup_failed', 'cancelled'],
        'picked_up' => ['received_at_station'],
        'received_at_station' => ['refilling', 'cancelled'],
        'refilling' => ['ready_for_delivery', 'ready_for_pickup'],
        'ready_for_pickup' => ['completed', 'cancelled'],
        'ready_for_delivery' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered', 'delivery_failed'],
        'delivered' => ['completed'],
    ];

    public function transition(Order $order, string $toStatus, ?User $actor = null, ?string $reason = null, array $metadata = []): Order
    {
        $execute = function () use ($order, $toStatus, $actor, $reason, $metadata) {
            $order = DB::transactionLevel() > 0
                ? Order::findOrFail($order->id)
                : Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $order->status;

            if ($order->isStationPickup() && in_array($toStatus, ['assigned', 'out_for_delivery', 'delivered'], true)) {
                throw ValidationException::withMessages(['status' => ['Station pickup orders do not require driver assignment or delivery dispatch.']]);
            }

            if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ["Cannot change an order from {$fromStatus} to {$toStatus}."]]);
            }

            if (in_array($toStatus, ['rejected', 'cancelled', 'pickup_failed', 'delivery_failed'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => ['A reason is required for this action.']]);
            }

            $updates = ['status' => $toStatus];
            if ($toStatus === 'confirmed') { $updates['confirmed_at'] = now(); }
            if ($toStatus === 'cancelled') { $updates['cancelled_at'] = now(); }
            if ($toStatus === 'completed') {
                $updates['completed_at'] = now();
                if ($order->isStationPickup() && $order->payment && $order->payment->method === 'cash' && $order->payment->status === 'pending') {
                    $order->payment->update(['status' => 'paid', 'paid_at' => now()]);
                }
            }
            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => $fromStatus, 'to_status' => $toStatus,
                'changed_by' => $actor?->id, 'actor_role' => $actor?->role, 'reason' => $reason, 'metadata' => $metadata ?: null,
            ]);
            AuditLog::create(['actor_id' => $actor?->id, 'action' => "order.{$toStatus}", 'table_name' => 'orders', 'record_id' => $order->id]);
            app(RealtimeEventService::class)->emit('order', $order->id, 'status_changed', $toStatus);

            $messages = [
                'confirmed' => ['Order Confirmed', 'Your refill order has been confirmed.'],
                'picked_up' => ['Empty Gallons Picked Up', 'Your empty gallons have been collected for refilling.'],
                'received_at_station' => ['Gallons Received', 'Your gallons have been received at the station.'],
                'refilling' => ['Refill Started', 'Your gallons are now being refilled.'],
                'ready_for_delivery' => ['Refill Ready', 'Your refilled gallons are ready for delivery.'],
                'ready_for_pickup' => ['Order Ready for Pickup', 'Your order is ready! Please collect it from the Aqua Clayne station.'],
                'completed' => ['Order Completed', 'Thank you! Your order has been completed.'],
                'cancelled' => ['Order Cancelled', 'Your refill order has been cancelled.'],
                'rejected' => ['Order Update', 'Your refill order was not accepted.'],
                'pickup_failed' => ['Pickup Update', 'We could not complete the gallon pickup.'],
                'delivery_failed' => ['Delivery Update', 'We could not complete the delivery.'],
            ];

            if (isset($messages[$toStatus])) {
                $order->loadMissing('customer.user');
                if ($order->customer?->user) {
                    app(NotificationService::class)->sendToUser(
                        $order->customer->user,
                        $messages[$toStatus][0],
                        $messages[$toStatus][1],
                        ['entity_type' => 'order', 'entity_id' => $order->id]
                    );
                }
            }

            return $order->fresh();
        };

        // If an outer transaction is already active (e.g. in DeliveryService::assign), execute directly
        // to avoid nested SAVEPOINTs which are rejected by the Supabase PgBouncer/Supavisor transaction pooler.
        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }
}
