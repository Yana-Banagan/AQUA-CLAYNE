<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('credit_account_id')
                ->constrained('credit_accounts')
                ->cascadeOnDelete();

            $table->foreignUuid('order_id')
                ->nullable()
                ->constrained('orders');

            $table->string('type', 10);
            $table->decimal('amount', 10, 2);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
