<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfid_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('gallon_id')
                ->unique()
                ->constrained('gallons')
                ->cascadeOnDelete();

            $table->string('tag_uid', 64)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_tags');
    }
};
