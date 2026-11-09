<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 100)->nullable()->index();
            $table->string('gateway_checkout_session_id', 100)->nullable()->index();
            $table->string('gateway_payment_intent_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->decimal('gateway_fee', 10, 2)->nullable();
            $table->decimal('gateway_net_amount', 10, 2)->nullable();
            $table->text('failure_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropIndex(['gateway_reference']);
            $table->dropIndex(['gateway_checkout_session_id']);
            $table->dropColumn(['gateway', 'gateway_reference', 'gateway_checkout_session_id', 'gateway_payment_intent_id', 'gateway_payment_id', 'gateway_fee', 'gateway_net_amount', 'failure_reason']);
        });
    }
};
