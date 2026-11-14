<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRACKING_TABLE = 'timezone_migration_2026_11_14_000005';

    private const LEGACY_COLUMNS = [
        'products' => ['created_at', 'updated_at'],
        'orders' => ['created_at', 'updated_at', 'assigned_at'],
        'personal_access_tokens' => ['last_used_at', 'expires_at', 'created_at', 'updated_at'],
        'password_reset_otps' => ['expires_at', 'verified_at', 'created_at', 'updated_at'],
        'paymongo_webhook_events' => ['processed_at'],
        'walk_in_orders' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (!Schema::hasTable(self::TRACKING_TABLE)) {
            Schema::create(self::TRACKING_TABLE, function (Blueprint $table) {
                $table->string('table_name', 63);
                $table->string('column_name', 63);
                $table->primary(['table_name', 'column_name']);
            });
        }

        foreach (self::LEGACY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnHasType($table, $column, 'timestamp without time zone')) {
                    continue;
                }

                // These application-managed columns were written while Laravel used UTC.
                DB::statement(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamp with time zone USING "%s" AT TIME ZONE \'UTC\'',
                    $table,
                    $column,
                    $column
                ));
                DB::table(self::TRACKING_TABLE)->insert([
                    'table_name' => $table,
                    'column_name' => $column,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (!Schema::hasTable(self::TRACKING_TABLE)) {
            return;
        }

        foreach (self::LEGACY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (!DB::table(self::TRACKING_TABLE)
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->exists()
                    || !$this->columnHasType($table, $column, 'timestamp with time zone')) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamp without time zone USING "%s" AT TIME ZONE \'UTC\'',
                    $table,
                    $column,
                    $column
                ));
            }
        }

        Schema::drop(self::TRACKING_TABLE);
    }

    private function columnHasType(string $table, string $column, string $type): bool
    {
        $result = DB::selectOne(
            'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        return $result && $result->data_type === $type;
    }
};
