<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // These match the customer history, driver assignment, and unread
        // notification queries used on every dashboard refresh.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'orders_customer_created_index');
            $table->index(['customer_id', 'status'], 'orders_customer_status_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'payments_order_status_index');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['driver_id', 'status', 'created_at'], 'deliveries_driver_status_created_index');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
        });
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index(['customer_id', 'is_default'], 'addresses_customer_default_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_created_index');
            $table->dropIndex('orders_customer_status_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_status_index');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_driver_status_created_index');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_created_index');
        });
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_customer_default_index');
        });
    }
};
