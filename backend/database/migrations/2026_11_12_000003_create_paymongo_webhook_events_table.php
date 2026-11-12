<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymongo_webhook_events', function (Blueprint $table) {
            $table->string('event_id', 120)->primary();
            $table->string('event_type', 100)->nullable();
            $table->string('payment_id', 36)->nullable()->index();
            $table->timestamp('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymongo_webhook_events');
    }
};
