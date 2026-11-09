<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refill_queue_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('queue_number')->unique();
            $table->string('status', 20)->default('waiting');
            $table->integer('priority')->default(0);
            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('removed_at')->nullable();
            $table->text('manual_priority_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'priority', 'queued_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('refill_queue_entries'); }
};
