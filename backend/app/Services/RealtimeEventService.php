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
        RealtimeEvent::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event' => $event,
            'status' => $status,
        ]);
    }
}
