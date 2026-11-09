<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Keep the order record when a customer removes an old saved
            // address; PostgreSQL clears the optional reference instead of
            // rejecting the deletion with a foreign-key violation.
            $table->dropForeign(['address_id']);
            $table->foreign('address_id')
                ->references('id')
                ->on('customer_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->foreign('address_id')
                ->references('id')
                ->on('customer_addresses')
                ->restrictOnDelete();
        });
    }
};
