<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id')
                ->constrained('customers');

            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained('complaint_categories');

            $table->string('subject', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
