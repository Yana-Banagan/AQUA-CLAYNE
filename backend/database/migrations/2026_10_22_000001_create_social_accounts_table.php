<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Allow password_hash to be nullable for users registered via social providers
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_hash', 255)->nullable()->change();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('provider', 30);
            $table->string('provider_user_id', 191);
            $table->string('provider_email', 191)->nullable();
            $table->text('avatar_url')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
            $table->index(['provider', 'provider_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        Schema::table('users', function (Blueprint $table) {
            $table->string('password_hash', 255)->nullable(false)->change();
        });
    }
};
