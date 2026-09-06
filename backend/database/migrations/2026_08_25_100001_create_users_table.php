<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // UUIDs are assigned by Laravel's HasUuids trait, avoiding a
            // PostgreSQL extension requirement during deployment.
            $table->uuid('id')->primary();

            $table->string('name', 150);

            $table->string('email', 150)
                ->unique();

            $table->string('phone', 30)
                ->nullable();

            $table->string('password_hash', 255);

            $table->string('role', 20)
                ->default('customer');

            $table->string('status', 20)
                ->default('active');

            $table->text('fcm_token')
                ->nullable();

            $table->timestampTz('created_at')
                ->useCurrent();

            $table->timestampTz('updated_at')
                ->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'customer', 'driver', 'staff'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
