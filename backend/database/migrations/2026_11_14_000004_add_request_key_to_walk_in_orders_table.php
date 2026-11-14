<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('walk_in_orders', function (Blueprint $table) {
            $table->uuid('request_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('walk_in_orders', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn('request_key');
        });
    }
};
