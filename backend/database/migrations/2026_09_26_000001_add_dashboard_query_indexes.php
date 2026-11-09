<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_created_at_index ON orders (created_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_status_created_index ON orders (status, created_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_status_paid_at_index ON payments (status, paid_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS deliveries_status_driver_index ON deliveries (status, driver_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS order_history_created_at_index ON order_status_history (created_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS maintenance_records_latest_index ON maintenance_records (maintenance_id, performed_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS walk_in_orders_created_at_index ON walk_in_orders (created_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS chat_messages_unread_role_index ON chat_messages (sender_role, read_at)');
        } else {
            DB::statement('CREATE INDEX IF NOT EXISTS orders_created_at_index ON orders (created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS orders_status_created_index ON orders (status, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS payments_status_paid_at_index ON payments (status, paid_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS deliveries_status_driver_index ON deliveries (status, driver_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS order_history_created_at_index ON order_status_history (created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS maintenance_records_latest_index ON maintenance_records (maintenance_id, performed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS walk_in_orders_created_at_index ON walk_in_orders (created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS chat_messages_unread_role_index ON chat_messages (sender_role, read_at)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_created_at_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_status_created_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_status_paid_at_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS deliveries_status_driver_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS order_history_created_at_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS maintenance_records_latest_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS walk_in_orders_created_at_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS chat_messages_unread_role_index');
        } else {
            DB::statement('DROP INDEX IF EXISTS orders_created_at_index');
            DB::statement('DROP INDEX IF EXISTS orders_status_created_index');
            DB::statement('DROP INDEX IF EXISTS payments_status_paid_at_index');
            DB::statement('DROP INDEX IF EXISTS deliveries_status_driver_index');
            DB::statement('DROP INDEX IF EXISTS order_history_created_at_index');
            DB::statement('DROP INDEX IF EXISTS maintenance_records_latest_index');
            DB::statement('DROP INDEX IF EXISTS walk_in_orders_created_at_index');
            DB::statement('DROP INDEX IF EXISTS chat_messages_unread_role_index');
        }
    }
};
