<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('driver_latitude', 9, 6)->nullable();
            $table->decimal('driver_longitude', 9, 6)->nullable();
            $table->timestampTz('location_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['driver_latitude', 'driver_longitude', 'location_updated_at']);
        });
    }
};
