<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('walk_in_orders', function (Blueprint $table) {
            // Nullable keeps historical counter-sale records valid; all new
            // walk-in sales require a contact number in the admin form.
            $table->string('phone', 30)->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('walk_in_orders', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
