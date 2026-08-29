<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gallons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 30);
            $table->string('status', 20)->default('in_stock');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallons');
    }
};
