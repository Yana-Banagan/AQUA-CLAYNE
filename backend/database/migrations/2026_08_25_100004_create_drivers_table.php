<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('license_number', 50)->nullable();
            $table->string('vehicle_plate', 20)->nullable();

            $table->timestampTz('created_at')
                ->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
