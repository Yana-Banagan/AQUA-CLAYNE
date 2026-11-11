<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_container_rfid_tags', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->foreignUuid('customer_container_id')
                ->constrained('customer_containers')
                ->cascadeOnDelete();

            $table->string('uid', 64)->unique();
            $table->string('status', 20)->default('active'); // active, inactive, replaced
            $table->timestampTz('registered_at')->useCurrent();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();

            $table->index(['customer_container_id', 'status']);
            $table->index('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX unique_active_tag_per_customer_container ON customer_container_rfid_tags (customer_container_id) WHERE status = \'active\'');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS unique_active_tag_per_customer_container');
        }
        Schema::dropIfExists('customer_container_rfid_tags');
    }
};

