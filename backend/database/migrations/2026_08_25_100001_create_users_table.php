<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable UUID generation in PostgreSQL
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('gen_random_uuid()'));

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

        // Restrict allowed user roles
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('admin', 'customer', 'driver', 'staff'))
        ");

        // Restrict allowed user statuses
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_status_check
            CHECK (status IN ('active', 'inactive'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
