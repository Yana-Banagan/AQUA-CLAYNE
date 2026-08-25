<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * NOTE: Laravel's default users table already has: id, name, email,
     * password, timestamps, etc. This migration ADDS the extra columns
     * your system needs on top of that — it does NOT recreate the table.
     */
    public function up(): void
    {
        // Make sure pgcrypto is available (needed if you use gen_random_uuid()
        // anywhere else in the schema; Laravel's default users.id is usually
        // a bigint, not a uuid, so we leave the primary key untouched here).
        DB::statement('create extension if not exists "pgcrypto"');

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('customer')->after('phone');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('active')->after('role');
            }
            if (!Schema::hasColumn('users', 'fcm_token')) {
                $table->text('fcm_token')->nullable()->after('status');
            }
        });

        // Enforce the same check constraints your raw SQL had
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','customer','driver','staff'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','inactive'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'status', 'fcm_token']);
        });
    }
};
