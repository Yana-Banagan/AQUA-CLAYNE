<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfid_registration_sessions', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->foreignUuid('customer_container_id')
                ->constrained('customer_containers')
                ->cascadeOnDelete();

            $table->foreignUuid('station_device_id')
                ->nullable()
                ->constrained('rfid_devices')
                ->nullOnDelete();

            $table->foreignUuid('created_by')
                ->constrained('users');

            $table->string('status', 20)->default('pending'); // pending, completed, expired, cancelled
            $table->timestampTz('expires_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('scanned_uid', 64)->nullable();
            $table->timestampsTz();

            $table->index(['station_device_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_registration_sessions');
    }
};

