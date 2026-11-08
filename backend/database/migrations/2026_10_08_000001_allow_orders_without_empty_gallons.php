<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_expected_empty_gallons_check');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_expected_empty_gallons_check CHECK (expected_empty_gallons >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_expected_empty_gallons_check');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_expected_empty_gallons_check CHECK (expected_empty_gallons >= 1)');
    }
};