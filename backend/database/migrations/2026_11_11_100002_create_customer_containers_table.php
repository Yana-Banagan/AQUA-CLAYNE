<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_containers', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('container_code', 50)->unique();
            $table->string('container_type', 100)->default('5-Gallon Standard Slim');
            $table->decimal('capacity', 5, 2)->default(5.00);
            $table->string('status', 20)->default('active'); // active, inactive, damaged, retired
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_containers');
    }
};

