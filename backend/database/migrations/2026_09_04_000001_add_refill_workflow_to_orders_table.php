<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 40)->nullable()->unique()->after('id');
            $table->string('container_ownership', 30)->default('customer_owned')->after('status');
            $table->unsignedInteger('expected_empty_gallons')->default(1)->after('container_ownership');
            $table->string('payment_method', 20)->default('cash')->after('expected_empty_gallons');
            $table->text('notes')->nullable()->after('payment_method');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropColumn(['order_number', 'container_ownership', 'expected_empty_gallons', 'payment_method', 'notes', 'confirmed_at', 'cancelled_at', 'completed_at']);
        });
    }
};
