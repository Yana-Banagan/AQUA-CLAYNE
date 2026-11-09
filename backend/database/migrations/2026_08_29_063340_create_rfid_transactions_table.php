<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfid_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('rfid_tag_id')
                ->constrained('rfid_tags');

            $table->foreignUuid('order_id')
                ->nullable()
                ->constrained('orders');

            $table->string('action', 20);

            $table->foreignUuid('scanned_by')
                ->nullable()
                ->constrained('users');

            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_transactions');
    }
};
