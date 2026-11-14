<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderContainerIntake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefillOperationService
{
    public function receiveIntake(Order $order, array $data, User $actor): OrderContainerIntake
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $canReceive = $order->status === 'picked_up'
                || ($order->status === 'confirmed' && $order->isStationPickup());

            if (!$canReceive) {
                throw ValidationException::withMessages([
                    'order' => ['Only collected orders or confirmed station-pickup orders can be received at the station.'],
                ]);
            }

            $receivedCount = (int) $data['received_count'];
            $acceptedCount = (int) $data['accepted_count'];
            $damagedCount = (int) ($data['damaged_count'] ?? 0);
            $rejectedCount = (int) ($data['rejected_count'] ?? 0);

            if (($acceptedCount + $damagedCount + $rejectedCount) > $receivedCount) {
                throw ValidationException::withMessages([
                    'accepted_count' => ['Accepted, damaged, and rejected counts cannot exceed received gallons.'],
                ]);
            }

            $hasDiscrepancy = $receivedCount !== (int) $order->expected_empty_gallons
                || $acceptedCount !== (int) $order->expected_empty_gallons
                || $damagedCount > 0
                || $rejectedCount > 0;

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
                'record_id' => $intake->id,
            ]);

            if (!$hasDiscrepancy && $acceptedCount > 0) {
                $this->startRefillIfAvailable($order->fresh(), $actor);
            }

            app(RealtimeEventService::class)->emit('order', $order->id, 'intake_recorded', 'received_at_station');

            return $intake->fresh();
        });
    }

    public function resolveIntake(Order $order, array $data, User $actor): OrderContainerIntake
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'received_at_station') {
                throw ValidationException::withMessages([
                    'order' => ['Only orders received at the station can be reconciled.'],
                ]);
            }

            $intake = OrderContainerIntake::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $receivedCount = isset($data['received_count']) ? (int) $data['received_count'] : $intake->received_count;
            $acceptedCount = (int) $data['accepted_count'];
            $damagedCount = isset($data['damaged_count']) ? (int) $data['damaged_count'] : $intake->damaged_count;
            $rejectedCount = isset($data['rejected_count']) ? (int) $data['rejected_count'] : $intake->rejected_count;

            if (($acceptedCount + $damagedCount + $rejectedCount) > $receivedCount) {
                throw ValidationException::withMessages([
                    'accepted_count' => ['Accepted, damaged, and rejected counts cannot exceed received gallons.'],
                ]);
            }

            $notes = $data['condition_notes'] ?? $intake->condition_notes;
            $notes = trim((string) $notes . ' | Resolution: ' . $data['resolution_reason']);

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

            $this->startRefillIfAvailable($order, $actor);

            return $intake->fresh();
        });
    }

    public function start(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->startRefill($order, $actor);

            return $order->fresh();
        });
    }

    public function complete(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'refilling') {
                throw ValidationException::withMessages([
                    'order' => ['Only orders currently being refilled can be completed.'],
                ]);
            }

            $toStatus = $order->isStationPickup() ? 'ready_for_pickup' : 'ready_for_delivery';
            app(OrderWorkflowService::class)->transition($order, $toStatus, $actor);
            if ($toStatus === 'ready_for_delivery') {
                $order->delivery?->update(['status' => 'ready_for_delivery']);
            }

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'refill.completed',
                'table_name' => 'orders',
                'record_id' => $order->id,
            ]);

            return $order->fresh();
        });
    }

    private function startRefillIfAvailable(Order $order, User $actor): void
    {
        $this->lockStation();

        if (Order::query()->where('status', 'refilling')->exists()) {
            return;
        }

        $this->startRefill($order, $actor, false);
    }

    private function startRefill(Order $order, User $actor, bool $acquireStationLock = true): void
    {
        if ($acquireStationLock) {
            $this->lockStation();
        }

        $order = Order::query()->lockForUpdate()->findOrFail($order->id);
        $intake = $order->containerIntake;

        if (
            $order->status !== 'received_at_station'
            || !$intake
            || !$intake->completed_at
            || $intake->accepted_count < 1
        ) {
            throw ValidationException::withMessages([
                'order' => ['A completed intake with accepted gallons is required before refilling.'],
            ]);
        }

        if (Order::query()->where('status', 'refilling')->where('id', '!=', $order->id)->exists()) {
            throw ValidationException::withMessages([
                'order' => ['Another order is currently being refilled at the station.'],
            ]);
        }

        app(OrderWorkflowService::class)->transition($order, 'refilling', $actor);
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'refill.started',
            'table_name' => 'orders',
            'record_id' => $order->id,
        ]);
    }

    private function lockStation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['aquaclayne:refill-station']);
        }
    }
}
