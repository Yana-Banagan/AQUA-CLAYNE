<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. SAFELY REPAIR CREDIT TABLES IF MISSING IN SUPABASE
        if (!Schema::hasTable('credit_accounts')) {
            Schema::create('credit_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('customer_id')
                    ->unique()
                    ->constrained('customers');
                $table->decimal('balance', 10, 2)->default(0);
                $table->decimal('credit_limit', 10, 2)->default(0);
            });
        }

        if (!Schema::hasTable('credit_transactions')) {
            Schema::create('credit_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('credit_account_id')
                    ->constrained('credit_accounts')
                    ->cascadeOnDelete();
                $table->foreignUuid('order_id')
                    ->nullable()
                    ->constrained('orders');
                $table->string('type', 10);
                $table->decimal('amount', 10, 2);
                $table->timestampTz('created_at')->useCurrent();
            });
        }

        // 2. ADD COMPOSITE PERFORMANCE INDEXES
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS credit_accounts_balance_index ON credit_accounts (balance)');
            DB::statement('CREATE INDEX IF NOT EXISTS credit_trans_account_created_index ON credit_transactions (credit_account_id, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS credit_trans_order_id_index ON credit_transactions (order_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS refill_queue_status_prio_num_idx ON refill_queue_entries (status, priority DESC, queue_number ASC)');
            DB::statement('CREATE INDEX IF NOT EXISTS refill_queue_status_completed_idx ON refill_queue_entries (status, completed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS intakes_received_at_idx ON order_container_intakes (received_at DESC)');
            DB::statement('CREATE INDEX IF NOT EXISTS intakes_completed_at_idx ON order_container_intakes (completed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS inventory_stock_reorder_idx ON inventory (quantity_on_hand, reorder_level)');
            DB::statement('CREATE INDEX IF NOT EXISTS orders_payment_status_idx ON orders (payment_method, status)');
        } else {
            // SQLite and other drivers
            DB::statement('CREATE INDEX IF NOT EXISTS credit_accounts_balance_index ON credit_accounts (balance)');
            DB::statement('CREATE INDEX IF NOT EXISTS credit_trans_account_created_index ON credit_transactions (credit_account_id, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS credit_trans_order_id_index ON credit_transactions (order_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS refill_queue_status_prio_num_idx ON refill_queue_entries (status, priority, queue_number)');
            DB::statement('CREATE INDEX IF NOT EXISTS refill_queue_status_completed_idx ON refill_queue_entries (status, completed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS intakes_received_at_idx ON order_container_intakes (received_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS intakes_completed_at_idx ON order_container_intakes (completed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS inventory_stock_reorder_idx ON inventory (quantity_on_hand, reorder_level)');
            DB::statement('CREATE INDEX IF NOT EXISTS orders_payment_status_idx ON orders (payment_method, status)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS credit_accounts_balance_index');
            DB::statement('DROP INDEX IF EXISTS credit_trans_account_created_index');
            DB::statement('DROP INDEX IF EXISTS credit_trans_order_id_index');
            DB::statement('DROP INDEX IF EXISTS refill_queue_status_prio_num_idx');
            DB::statement('DROP INDEX IF EXISTS refill_queue_status_completed_idx');
            DB::statement('DROP INDEX IF EXISTS intakes_received_at_idx');
            DB::statement('DROP INDEX IF EXISTS intakes_completed_at_idx');
            DB::statement('DROP INDEX IF EXISTS inventory_stock_reorder_idx');
            DB::statement('DROP INDEX IF EXISTS orders_payment_status_idx');
        } else {
            DB::statement('DROP INDEX IF EXISTS credit_accounts_balance_index');
            DB::statement('DROP INDEX IF EXISTS credit_trans_account_created_index');
            DB::statement('DROP INDEX IF EXISTS credit_trans_order_id_index');
            DB::statement('DROP INDEX IF EXISTS refill_queue_status_prio_num_idx');
            DB::statement('DROP INDEX IF EXISTS refill_queue_status_completed_idx');
            DB::statement('DROP INDEX IF EXISTS intakes_received_at_idx');
            DB::statement('DROP INDEX IF EXISTS intakes_completed_at_idx');
            DB::statement('DROP INDEX IF EXISTS inventory_stock_reorder_idx');
            DB::statement('DROP INDEX IF EXISTS orders_payment_status_idx');
        }
    }
};

