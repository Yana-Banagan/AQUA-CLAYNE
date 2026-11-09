<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_expected_empty_gallons_check CHECK (expected_empty_gallons >= 1)');
        DB::statement('ALTER TABLE order_container_intakes ADD CONSTRAINT intake_accepted_not_more_than_received_check CHECK (accepted_count <= received_count)');
        DB::statement('ALTER TABLE order_container_intakes ADD CONSTRAINT intake_rejected_not_more_than_received_check CHECK (rejected_count <= received_count)');
        DB::statement('ALTER TABLE order_container_intakes ADD CONSTRAINT intake_returned_not_more_than_accepted_check CHECK (returned_count <= accepted_count)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_expected_empty_gallons_check');
        DB::statement('ALTER TABLE order_container_intakes DROP CONSTRAINT IF EXISTS intake_accepted_not_more_than_received_check');
        DB::statement('ALTER TABLE order_container_intakes DROP CONSTRAINT IF EXISTS intake_rejected_not_more_than_received_check');
        DB::statement('ALTER TABLE order_container_intakes DROP CONSTRAINT IF EXISTS intake_returned_not_more_than_accepted_check');
    }
};
