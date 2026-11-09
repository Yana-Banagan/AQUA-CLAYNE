<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id')
                ->unique()
                ->constrained('customers');

            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('credit_limit', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
