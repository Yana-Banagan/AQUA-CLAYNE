<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 30)->default('refill')->after('description');
        });

        $exists = DB::table('products')->where('product_type', 'new_gallon')->exists();
        if (!$exists) {
            $id = (string) Str::uuid();
            DB::table('products')->insert([
                'id' => $id,
                'name' => 'New Gallon with Water',
                'description' => 'A brand-new gallon, filled with purified water. No empty gallon pickup is required.',
                'product_type' => 'new_gallon',
                'price' => 200,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('inventory')->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $id,
                'quantity_on_hand' => 999,
                'reorder_level' => 10,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')->where('product_type', 'new_gallon')->delete();
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
    }
};
