<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('products')
            ->where('name', 'like', '%Alkaline%')
            ->orWhere('name', 'like', '%alkaline%')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('name', 'like', '%Alkaline%')
            ->orWhere('name', 'like', '%alkaline%')
            ->update(['is_active' => true]);
    }
};

