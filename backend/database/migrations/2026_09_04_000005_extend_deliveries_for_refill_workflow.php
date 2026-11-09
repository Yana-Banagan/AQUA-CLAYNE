<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->timestampTz('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('customer_confirmed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'failure_reason', 'customer_confirmed']);
        });
    }
};
