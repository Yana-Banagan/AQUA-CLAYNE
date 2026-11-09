<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('route_number', 50)->unique();
            $table->foreignUuid('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('area', 100);
            $table->string('status', 30)->default('ready'); // ready, in_progress, completed, cancelled
            $table->unsignedInteger('total_gallons')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['driver_id', 'status']);
            $table->index(['area', 'status']);
        });

        Schema::create('delivery_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->unsignedInteger('stop_sequence')->default(1);
            $table->string('status', 30)->default('assigned'); // assigned, en_route, arrived, delivered, failed
            $table->timestampTz('arrived_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['delivery_route_id', 'order_id']);
            $table->index(['delivery_route_id', 'stop_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_stops');
        Schema::dropIfExists('delivery_routes');
    }
};
