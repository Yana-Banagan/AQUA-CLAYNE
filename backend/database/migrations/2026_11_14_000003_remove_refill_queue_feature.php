<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->where('status', 'waiting_in_queue')
                ->update(['status' => 'received_at_station']);
        }

        if (Schema::hasTable('realtime_events')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
            }

            DB::table('realtime_events')
                ->where('entity_type', 'refill_queue')
                ->update(['entity_type' => 'order']);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'notification', 'payment', 'pickup_batch', 'delivery_route', 'chat', 'rfid'))");
            }
        }

        Schema::dropIfExists('refill_queue_entries');
    }

    public function down(): void
    {
        if (!Schema::hasTable('refill_queue_entries')) {
            Schema::create('refill_queue_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('queue_number')->unique();
                $table->string('status', 20)->default('waiting');
                $table->integer('priority')->default(0);
                $table->timestampTz('queued_at');
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampTz('removed_at')->nullable();
                $table->text('manual_priority_reason')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampsTz();
                $table->index(['status', 'priority', 'queued_at']);
            });
        }

        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('realtime_events')) {
            DB::statement('ALTER TABLE realtime_events DROP CONSTRAINT IF EXISTS realtime_events_entity_type_check');
            DB::statement("ALTER TABLE realtime_events ADD CONSTRAINT realtime_events_entity_type_check CHECK (entity_type IN ('order', 'delivery', 'refill_queue', 'notification', 'payment', 'pickup_batch', 'delivery_route', 'chat', 'rfid'))");
        }
    }
};
