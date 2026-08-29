<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('maintenance_id')
                ->constrained('maintenance')
                ->cascadeOnDelete();

            $table->foreignUuid('performed_by')
                ->nullable()
                ->constrained('users');

            $table->text('notes')->nullable();
            $table->timestampTz('performed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
