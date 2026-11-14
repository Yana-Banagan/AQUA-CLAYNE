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
            ['order', 'delivery', 'notification', 'payment', 'pickup_batch', 'delivery_route', 'chat', 'rfid'],
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

            // Invalidate centralized shared administrative counters
            \App\Services\AdminSharedDataService::invalidate();

            if (in_array($entityType, ['order', 'payment', 'delivery', 'pickup_batch', 'delivery_route'], true)) {
                $cache->forget('admin:dashboard:station-kpis');
                $cache->forget('admin:dashboard:intake-discrepancies');
                $cache->forget('admin:dashboard:recent-activities');
                $cache->forget('admin:deliveries:stats');
                $cache->forget('admin:deliveries:dispatch-bundle');
                $cache->forget('admin-pending-orders-count');
            }

            if (in_array($entityType, ['delivery', 'pickup_batch', 'delivery_route'], true)) {
                $cache->forget('dispatch:driver-statuses');
                $cache->forget('dispatch:driver-statuses:no-avatars');
                $cache->forget('admin:deliveries:history-batches');
                $cache->forget('admin:deliveries:history-routes');
            }

            if ($entityType === 'chat') {
                $cache->forget('admin-unread-chats-count');
            }

            if ($entityType === 'rfid') {
                $cache->forget('admin:rfid:stats');
                $cache->forget('admin:rfid:active-devices');
            }
        } catch (\Throwable) {
            // Silently continue if cache store is temporarily unreachable
        }
    }
}
