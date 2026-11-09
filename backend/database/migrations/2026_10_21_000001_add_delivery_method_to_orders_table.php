<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 30)->default('home_delivery')->after('address_id');
            $table->decimal('delivery_fee', 10, 2)->default(30.00)->after('delivery_method');
            $table->index(['delivery_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_method', 'status']);
            $table->dropColumn(['delivery_method', 'delivery_fee']);
        });
    }
};
