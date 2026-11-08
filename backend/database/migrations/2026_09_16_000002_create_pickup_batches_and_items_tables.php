<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pickup_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number', 50)->unique();
            $table->foreignUuid('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('area', 100);
            $table->string('status', 30)->default('ready'); // ready, in_progress, completed, cancelled
            $table->unsignedInteger('total_expected_gallons')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['driver_id', 'status']);
            $table->index(['area', 'status']);
        });

        Schema::create('pickup_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pickup_batch_id')->constrained('pickup_batches')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('stop_sequence')->default(1);
            $table->unsignedInteger('expected_gallons');
            $table->unsignedInteger('received_gallons')->nullable();
            $table->unsignedInteger('accepted_gallons')->nullable();
            $table->unsignedInteger('damaged_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->string('status', 30)->default('pending'); // pending, arrived, picked_up, failed
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['pickup_batch_id', 'order_id']);
            $table->index(['pickup_batch_id', 'stop_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_batch_items');
        Schema::dropIfExists('pickup_batches');
    }
};
