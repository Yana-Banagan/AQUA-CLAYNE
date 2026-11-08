<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
        DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'refill_queue', 'notification', 'pickup_batch', 'delivery_route'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
        DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'refill_queue', 'notification'))");
    }
};
