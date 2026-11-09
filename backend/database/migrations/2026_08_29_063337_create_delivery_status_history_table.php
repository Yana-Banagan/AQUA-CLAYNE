<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('delivery_id')
                ->constrained('deliveries')
                ->cascadeOnDelete();

            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_history');
    }
};
