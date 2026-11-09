<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_container_intakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_count');
            $table->unsignedInteger('received_count');
            $table->unsignedInteger('accepted_count');
            $table->unsignedInteger('damaged_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);
            $table->text('condition_notes')->nullable();
            $table->foreignUuid('received_by')->constrained('users');
            $table->timestampTz('received_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void { Schema::dropIfExists('order_container_intakes'); }
};
