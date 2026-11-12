<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['status', 'delivered_at', 'created_at'], 'deliveries_status_archive_index');
            $table->index('created_at', 'deliveries_created_at_index');
            $table->index('delivered_at', 'deliveries_delivered_at_index');
        });
        Schema::table('delivery_status_history', function (Blueprint $table) {
            $table->index(['delivery_id', 'created_at'], 'delivery_history_delivery_created_index');
        });
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'delivery_routes_status_created_index');
        });
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->index(['order_id', 'delivery_route_id'], 'delivery_stops_order_route_index');
            $table->index(['delivery_route_id', 'status'], 'delivery_stops_route_status_index');
        });
        Schema::table('pickup_batch_items', function (Blueprint $table) {
            $table->index(['order_id', 'pickup_batch_id'], 'pickup_items_order_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_status_archive_index');
            $table->dropIndex('deliveries_created_at_index');
            $table->dropIndex('deliveries_delivered_at_index');
        });
        Schema::table('delivery_status_history', function (Blueprint $table) {
            $table->dropIndex('delivery_history_delivery_created_index');
        });
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropIndex('delivery_routes_status_created_index');
        });
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropIndex('delivery_stops_order_route_index');
            $table->dropIndex('delivery_stops_route_status_index');
        });
        Schema::table('pickup_batch_items', function (Blueprint $table) {
            $table->dropIndex('pickup_items_order_batch_index');
        });
    }
};
