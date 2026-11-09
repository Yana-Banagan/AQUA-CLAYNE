<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
        DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'refill_queue', 'notification', 'payment', 'pickup_batch', 'delivery_route', 'chat'))");

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM pg_publication
                    WHERE pubname = 'supabase_realtime'
                ) AND NOT EXISTS (
                    SELECT 1
                    FROM pg_publication_tables
                    WHERE pubname = 'supabase_realtime'
                      AND schemaname = 'public'
                      AND tablename = 'realtime_events'
                ) THEN
                    ALTER PUBLICATION supabase_realtime ADD TABLE public.realtime_events;
                END IF;
            END
            $$
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
        DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'refill_queue', 'notification', 'chat'))");
    }
};
