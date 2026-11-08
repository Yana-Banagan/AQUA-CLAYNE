<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('barangay', 100)->nullable()->after('address_line');
            $table->string('city', 100)->nullable()->after('barangay');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('zip_code', 20)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['barangay', 'city', 'province', 'zip_code']);
        });
    }
};
