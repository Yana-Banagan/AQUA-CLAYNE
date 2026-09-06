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
        'confirmed' => ['assigned', 'cancelled'],
        'assigned' => ['picked_up', 'pickup_failed', 'cancelled'],
        'picked_up' => ['received_at_station'],
        'received_at_station' => ['waiting_in_queue'],
        'waiting_in_queue' => ['refilling', 'cancelled'],
        'refilling' => ['ready_for_delivery'],
        'ready_for_delivery' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'delivery_failed'],
        'delivered' => ['completed'],
    ];

    public function transition(Order $order, string $toStatus, ?User $actor = null, ?string $reason = null, array $metadata = []): Order
    {
        return DB::transaction(function () use ($order, $toStatus, $actor, $reason, $metadata) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $order->status;

            if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ["Cannot change an order from {$fromStatus} to {$toStatus}."]]);
            }

            if (in_array($toStatus, ['rejected', 'cancelled', 'pickup_failed', 'delivery_failed'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => ['A reason is required for this action.']]);
            }

            $updates = ['status' => $toStatus];
            if ($toStatus === 'confirmed') { $updates['confirmed_at'] = now(); }
            if ($toStatus === 'cancelled') { $updates['cancelled_at'] = now(); }
            if ($toStatus === 'completed') { $updates['completed_at'] = now(); }
            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => $fromStatus, 'to_status' => $toStatus,
                'changed_by' => $actor?->id, 'actor_role' => $actor?->role, 'reason' => $reason, 'metadata' => $metadata ?: null,
            ]);
            AuditLog::create(['actor_id' => $actor?->id, 'action' => "order.{$toStatus}", 'table_name' => 'orders', 'record_id' => $order->id]);
            app(RealtimeEventService::class)->emit('order', $order->id, 'status_changed', $toStatus);

            return $order->fresh();
        });
    }
}
