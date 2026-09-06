<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderContainerIntake;
use App\Models\RefillQueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefillQueueService
{
    public function receiveIntake(Order $order, array $data, User $actor): OrderContainerIntake
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'picked_up') {
                throw ValidationException::withMessages(['order' => ['Only picked-up orders can be received at the station.']]);
            }
            if ($data['accepted_count'] > $data['received_count'] || ($data['accepted_count'] + $data['rejected_count']) > $data['received_count']) {
                throw ValidationException::withMessages(['accepted_count' => ['Accepted and rejected counts cannot exceed received gallons.']]);
            }

            $intake = OrderContainerIntake::create([
                'order_id' => $order->id, 'expected_count' => $order->expected_empty_gallons,
                'received_count' => $data['received_count'], 'accepted_count' => $data['accepted_count'],
                'damaged_count' => $data['damaged_count'] ?? 0, 'rejected_count' => $data['rejected_count'] ?? 0,
                'condition_notes' => $data['condition_notes'] ?? null, 'received_by' => $actor->id,
                'received_at' => now(), 'completed_at' => now(),
            ]);
            app(OrderWorkflowService::class)->transition($order, 'received_at_station', $actor, $data['condition_notes'] ?? null);
            $this->queue($order->fresh(), $actor);
            return $intake;
        });
    }

    public function queue(Order $order, User $actor): RefillQueueEntry
    {
        $order = Order::query()->lockForUpdate()->findOrFail($order->id);
        if ($order->status !== 'received_at_station' || !$order->containerIntake || $order->containerIntake->accepted_count < 1) {
            throw ValidationException::withMessages(['order' => ['A valid intake with accepted gallons is required before queueing.']]);
        }
        $queueNumber = ((int) RefillQueueEntry::query()->max('queue_number')) + 1;
        $entry = RefillQueueEntry::create(['order_id' => $order->id, 'queue_number' => $queueNumber, 'queued_at' => now(), 'created_by' => $actor->id]);
        app(OrderWorkflowService::class)->transition($order, 'waiting_in_queue', $actor);
        return $entry;
    }

    public function start(Order $order, User $actor): RefillQueueEntry
    {
        return $this->changeRefillState($order, $actor, 'waiting', 'in_progress', 'waiting_in_queue', 'refilling', 'started_at');
    }

    public function complete(Order $order, User $actor): RefillQueueEntry
    {
        return $this->changeRefillState($order, $actor, 'in_progress', 'completed', 'refilling', 'ready_for_delivery', 'completed_at');
    }

    public function changePriority(Order $order, int $priority, string $reason, User $actor): RefillQueueEntry
    {
        return DB::transaction(function () use ($order, $priority, $reason, $actor) {
            $entry = RefillQueueEntry::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'waiting') throw ValidationException::withMessages(['order' => ['Only waiting queue entries can be reprioritized.']]);
            $entry->update(['priority' => $priority, 'manual_priority_reason' => $reason, 'updated_by' => $actor->id]);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'refill_queue.priority_changed', 'table_name' => 'refill_queue_entries', 'record_id' => $entry->id]);
            app(RealtimeEventService::class)->emit('refill_queue', $entry->id, 'priority_changed', $entry->status);
            return $entry->fresh();
        });
    }

    private function changeRefillState(Order $order, User $actor, string $fromQueue, string $toQueue, string $fromOrder, string $toOrder, string $atColumn): RefillQueueEntry
    {
        return DB::transaction(function () use ($order, $actor, $fromQueue, $toQueue, $fromOrder, $toOrder, $atColumn) {
            $entry = RefillQueueEntry::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($entry->status !== $fromQueue || $lockedOrder->status !== $fromOrder) throw ValidationException::withMessages(['order' => ['The order is not ready for this refill action.']]);
            $entry->update(['status' => $toQueue, $atColumn => now(), 'updated_by' => $actor->id]);
            app(OrderWorkflowService::class)->transition($lockedOrder, $toOrder, $actor);
            if ($toOrder === 'ready_for_delivery') {
                $lockedOrder->delivery?->update(['status' => 'ready_for_delivery']);
            }
            return $entry->fresh();
        });
    }
}
