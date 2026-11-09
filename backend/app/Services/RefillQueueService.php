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
    /**
     * Physical gallon intake verification upon arrival at station.
     * The Refill Queue does NOT start when the customer creates an order.
     * It begins ONLY after empty gallons are picked up, arrive at station,
     * and are physically counted and inspected.
     */
    public function receiveIntake(Order $order, array $data, User $actor): OrderContainerIntake
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'picked_up') {
                throw ValidationException::withMessages(['order' => ['Only picked-up orders can be received at the station.']]);
            }

            $receivedCount = (int) $data['received_count'];
            $acceptedCount = (int) $data['accepted_count'];
            $damagedCount = (int) ($data['damaged_count'] ?? 0);
            $rejectedCount = (int) ($data['rejected_count'] ?? 0);

            if ($acceptedCount > $receivedCount || ($acceptedCount + $rejectedCount) > $receivedCount) {
                throw ValidationException::withMessages(['accepted_count' => ['Accepted and rejected counts cannot exceed received gallons.']]);
            }

            // Detect discrepancy
            $hasDiscrepancy = ($receivedCount !== $order->expected_empty_gallons)
                || ($acceptedCount !== $order->expected_empty_gallons)
                || ($damagedCount > 0)
                || ($rejectedCount > 0);

            $intake = OrderContainerIntake::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'expected_count' => $order->expected_empty_gallons,
                    'received_count' => $receivedCount,
                    'accepted_count' => $acceptedCount,
                    'damaged_count' => $damagedCount,
                    'rejected_count' => $rejectedCount,
                    'condition_notes' => $data['condition_notes'] ?? null,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                    'completed_at' => $hasDiscrepancy ? null : now(),
                ]
            );

            app(OrderWorkflowService::class)->transition(
                $order,
                'received_at_station',
                $actor,
                $data['condition_notes'] ?? null,
                ['has_discrepancy' => $hasDiscrepancy]
            );

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'intake.received',
                'table_name' => 'order_container_intakes',
                'record_id' => $intake->id
            ]);

            app(RealtimeEventService::class)->emit('order', $order->id, 'intake_recorded', 'received_at_station');

            // CRITICAL BUSINESS RULE:
            // The refill queue begins ONLY after intake confirmation without discrepancy.
            // If counts differ, the order stays in received_at_station until reconciled.
            if (!$hasDiscrepancy && $acceptedCount > 0) {
                $this->queue($order->fresh(), $actor);
            }

            return $intake;
        });
    }

    /**
     * Resolve intake discrepancy and move order into WAITING_IN_QUEUE.
     */
    public function resolveIntake(Order $order, array $data, User $actor): RefillQueueEntry
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'received_at_station') {
                throw ValidationException::withMessages(['order' => ['Only orders received at station can be reconciled.']]);
            }

            $intake = OrderContainerIntake::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();

            $receivedCount = isset($data['received_count']) ? (int) $data['received_count'] : $intake->received_count;
            $acceptedCount = isset($data['accepted_count']) ? (int) $data['accepted_count'] : $intake->accepted_count;
            $damagedCount = isset($data['damaged_count']) ? (int) $data['damaged_count'] : $intake->damaged_count;
            $rejectedCount = isset($data['rejected_count']) ? (int) $data['rejected_count'] : $intake->rejected_count;

            if ($acceptedCount < 1) {
                throw ValidationException::withMessages(['accepted_count' => ['At least 1 gallon must be accepted to enter the refill queue.']]);
            }

            if ($acceptedCount > $receivedCount || ($acceptedCount + $rejectedCount) > $receivedCount) {
                throw ValidationException::withMessages(['accepted_count' => ['Accepted and rejected counts cannot exceed received gallons.']]);
            }

            $notes = $data['condition_notes'] ?? $intake->condition_notes;
            if (!empty($data['resolution_reason'])) {
                $notes = trim($notes . ' | Resolution: ' . $data['resolution_reason']);
            }

            $intake->update([
                'received_count' => $receivedCount,
                'accepted_count' => $acceptedCount,
                'damaged_count' => $damagedCount,
                'rejected_count' => $rejectedCount,
                'condition_notes' => $notes,
                'completed_at' => now(),
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'intake.discrepancy_resolved',
                'table_name' => 'order_container_intakes',
                'record_id' => $intake->id,
            ]);

            return $this->queue($order->fresh(), $actor);
        });
    }

    /**
     * Atomically creates a Refill Queue entry and moves order to WAITING_IN_QUEUE.
     */
    public function queue(Order $order, User $actor): RefillQueueEntry
    {
        $order = Order::query()->lockForUpdate()->findOrFail($order->id);
        if ($order->status !== 'received_at_station' || !$order->containerIntake || $order->containerIntake->accepted_count < 1) {
            throw ValidationException::withMessages(['order' => ['A valid intake with accepted gallons is required before queueing.']]);
        }

        $existing = RefillQueueEntry::query()->where('order_id', $order->id)->first();
        if ($existing) {
            if ($existing->status === 'waiting') {
                return $existing;
            }
            throw ValidationException::withMessages(['order' => ['A queue entry already exists for this order.']]);
        }

        $queueNumber = ((int) RefillQueueEntry::query()->max('queue_number')) + 1;
        $entry = RefillQueueEntry::create([
            'order_id' => $order->id,
            'queue_number' => $queueNumber,
            'status' => 'waiting',
            'priority' => 0,
            'queued_at' => now(),
            'created_by' => $actor->id
        ]);

        app(OrderWorkflowService::class)->transition($order, 'waiting_in_queue', $actor);

        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'refill_queue.created',
            'table_name' => 'refill_queue_entries',
            'record_id' => $entry->id,
        ]);

        app(RealtimeEventService::class)->emit('refill_queue', $entry->id, 'queue_created', 'waiting');

        return $entry;
    }

    /**
     * Starts the refill process for the next eligible order.
     * Enforces FIFO and single-active-refill station rule.
     */
    public function start(Order $order, User $actor): RefillQueueEntry
    {
        return $this->changeRefillState($order, $actor, 'waiting', 'in_progress', 'waiting_in_queue', 'refilling', 'started_at');
    }

    /**
     * Completes refill, marks containers sealed, and transitions to READY_FOR_DELIVERY or READY_FOR_PICKUP.
     */
    public function complete(Order $order, User $actor): RefillQueueEntry
    {
        $toOrder = $order->isStationPickup() ? 'ready_for_pickup' : 'ready_for_delivery';
        return $this->changeRefillState($order, $actor, 'in_progress', 'completed', 'refilling', $toOrder, 'completed_at');
    }

    /**
     * Controlled Priority Changes with mandatory operational justification & audit trail.
     */
    public function changePriority(Order $order, int $priority, string $reason, User $actor): RefillQueueEntry
    {
        return DB::transaction(function () use ($order, $priority, $reason, $actor) {
            $entry = RefillQueueEntry::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'waiting') {
                throw ValidationException::withMessages(['order' => ['Only waiting queue entries can be reprioritized.']]);
            }

            if (empty(trim($reason))) {
                throw ValidationException::withMessages(['reason' => ['An operational reason is mandatory for priority changes.']]);
            }

            $oldPriority = $entry->priority;
            $entry->update([
                'priority' => $priority,
                'manual_priority_reason' => $reason,
                'updated_by' => $actor->id
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'refill_queue.priority_changed',
                'table_name' => 'refill_queue_entries',
                'record_id' => $entry->id,
            ]);

            app(RealtimeEventService::class)->emit('refill_queue', $entry->id, 'priority_changed', $entry->status);

            return $entry->fresh();
        });
    }

    /**
     * Remove entry from queue with mandatory reason.
     */
    public function removeFromQueue(Order $order, string $reason, User $actor): RefillQueueEntry
    {
        return DB::transaction(function () use ($order, $reason, $actor) {
            $entry = RefillQueueEntry::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'waiting') {
                throw ValidationException::withMessages(['order' => ['Only waiting queue entries can be removed.']]);
            }

            if (empty(trim($reason))) {
                throw ValidationException::withMessages(['reason' => ['A removal reason is required.']]);
            }

            $entry->update([
                'status' => 'removed',
                'removed_at' => now(),
                'manual_priority_reason' => $reason,
                'updated_by' => $actor->id
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'refill_queue.removed',
                'table_name' => 'refill_queue_entries',
                'record_id' => $entry->id
            ]);

            app(RealtimeEventService::class)->emit('refill_queue', $entry->id, 'removed', 'removed');

            return $entry->fresh();
        });
    }

    /**
     * Atomic state machine transition between queue and order states.
     */
    private function changeRefillState(
        Order $order,
        User $actor,
        string $fromQueue,
        string $toQueue,
        string $fromOrder,
        string $toOrder,
        string $atColumn
    ): RefillQueueEntry {
        return DB::transaction(function () use ($order, $actor, $fromQueue, $toQueue, $fromOrder, $toOrder, $atColumn) {
            $entry = RefillQueueEntry::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($entry->status !== $fromQueue || $lockedOrder->status !== $fromOrder) {
                throw ValidationException::withMessages([
                    'order' => ["Order #{$lockedOrder->order_number} is not in {$fromQueue} state (currently {$entry->status}). Please refresh."]
                ]);
            }

            if ($fromQueue === 'waiting') {
                // RULE: Station policy allows only ONE active refill process at a time
                $activeRefill = RefillQueueEntry::query()->where('status', 'in_progress')->lockForUpdate()->first();
                if ($activeRefill && $activeRefill->id !== $entry->id) {
                    $activeOrderNumber = $activeRefill->order?->order_number ?? substr($activeRefill->order_id, 0, 8);
                    throw ValidationException::withMessages([
                        'order' => ["Another refill is currently in progress (Order #{$activeOrderNumber}). The station processes one refill at a time."]
                    ]);
                }

                // RULE: Only the next eligible order in the prioritized FIFO queue can start
                $nextEntry = RefillQueueEntry::query()
                    ->where('status', 'waiting')
                    ->orderByDesc('priority')
                    ->orderBy('queue_number')
                    ->lockForUpdate()
                    ->first();

                if (!$nextEntry || $nextEntry->id !== $entry->id) {
                    $eligibleNum = $nextEntry ? sprintf('#%03d', $nextEntry->queue_number) : 'none';
                    throw ValidationException::withMessages([
                        'order' => ["Only the next eligible order in the FIFO queue ({$eligibleNum}) can start refilling."]
                    ]);
                }
            }

            $entry->update([
                'status' => $toQueue,
                $atColumn => now(),
                'updated_by' => $actor->id
            ]);

            app(OrderWorkflowService::class)->transition($lockedOrder, $toOrder, $actor);

            if ($toOrder === 'ready_for_delivery') {
                $lockedOrder->delivery?->update(['status' => 'ready_for_delivery']);
            }

            $eventAction = $toQueue === 'in_progress' ? 'refill_started' : 'refill_completed';
            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => "refill_queue.{$eventAction}",
                'table_name' => 'refill_queue_entries',
                'record_id' => $entry->id,
            ]);

            app(RealtimeEventService::class)->emit('refill_queue', $entry->id, $eventAction, $toQueue);

            return $entry->fresh();
        });
    }
}
