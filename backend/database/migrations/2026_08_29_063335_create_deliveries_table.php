<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('order_id')
                ->unique()
                ->constrained('orders');

            $table->foreignUuid('driver_id')
                ->nullable()
                ->constrained('drivers');

            $table->timestampTz('eta')->nullable();
            $table->text('proof_photo_url')->nullable();
            $table->text('signature_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
