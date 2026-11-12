<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_auth_attempts', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->string('flow', 16);
            $table->string('provider', 30);
            $table->char('state_hash', 64)->unique();
            $table->char('code_hash', 64)->nullable()->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('state_used_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['flow', 'provider', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_auth_attempts');
    }
};
