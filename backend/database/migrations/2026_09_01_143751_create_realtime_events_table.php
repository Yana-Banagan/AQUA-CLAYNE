<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('realtime_events', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->string('entity_type', 20);

            $table->uuid('entity_id');

            $table->string('event', 30);

            $table->string('status', 20)->nullable();

            $table->timestampTz('created_at')->useCurrent();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE realtime_events
            ADD CONSTRAINT realtime_events_entity_type_check
            CHECK (entity_type IN ('order', 'delivery'))
        ");

        DB::statement("
            ALTER TABLE realtime_events
            ENABLE ROW LEVEL SECURITY
        ");

        DB::statement("
            CREATE POLICY anyone_can_read_signals
            ON realtime_events
            FOR SELECT
            USING (true)
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_publication_tables
                    WHERE pubname = 'supabase_realtime'
                    AND schemaname = 'public'
                    AND tablename = 'realtime_events'
                ) THEN
                    ALTER PUBLICATION supabase_realtime
                    ADD TABLE public.realtime_events;
                END IF;
            END
            \$\$
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::dropIfExists('realtime_events');
            return;
        }

        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM pg_publication_tables
                    WHERE pubname = 'supabase_realtime'
                    AND schemaname = 'public'
                    AND tablename = 'realtime_events'
                ) THEN
                    ALTER PUBLICATION supabase_realtime
                    DROP TABLE public.realtime_events;
                END IF;
            END
            \$\$
        ");

        Schema::dropIfExists('realtime_events');
    }
};
