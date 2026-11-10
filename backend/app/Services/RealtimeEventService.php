<?php

namespace App\Services;

use App\Models\RealtimeEvent;

class RealtimeEventService
{
    public function emit(
        string $entityType,
        string $entityId,
        string $event,
        ?string $status = null
    ): void {
        // Only insert if entity_type satisfies the PostgreSQL check constraint:
        // Keep this list aligned with the database check constraint.
        if (!in_array(
            $entityType,
            ['order', 'delivery', 'refill_queue', 'notification', 'payment', 'pickup_batch', 'delivery_route', 'chat'],
            true
        )) {
            return;
        }

        RealtimeEvent::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event' => $event,
            'status' => $status,
        ]);

        // Invalidate relevant server-side operational and dashboard caches
        $this->invalidateCaches($entityType);
    }

    private function invalidateCaches(string $entityType): void
    {
        try {
            $cache = \Illuminate\Support\Facades\Cache::store();

            if (in_array($entityType, ['order', 'payment', 'refill_queue', 'delivery', 'pickup_batch', 'delivery_route'], true)) {
                $cache->forget('admin:dashboard:station-kpis');
                $cache->forget('admin-pending-orders-count');
                $cache->forget('refill-queue:summary-stats');
                $cache->forget('refill-queue:quick-status');
            }

            if (in_array($entityType, ['delivery', 'pickup_batch', 'delivery_route'], true)) {
                $cache->forget('dispatch:driver-statuses');
            }

            if ($entityType === 'chat') {
                $cache->forget('admin-unread-chats-count');
            }
        } catch (\Throwable) {
            // Silently continue if cache store is temporarily unreachable
        }
    }
}
