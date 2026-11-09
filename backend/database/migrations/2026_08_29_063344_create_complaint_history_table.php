<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('complaint_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('complaint_id')
                ->constrained('complaints')
                ->cascadeOnDelete();

            $table->text('note');

            $table->foreignUuid('changed_by')
                ->nullable()
                ->constrained('users');

            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_history');
    }
};
