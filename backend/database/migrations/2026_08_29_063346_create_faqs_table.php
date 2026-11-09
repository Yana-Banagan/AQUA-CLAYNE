<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained('faq_categories');

            $table->text('question');
            $table->text('answer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
