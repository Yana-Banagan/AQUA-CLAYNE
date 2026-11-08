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
    }
}
