<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rfid_registration_sessions', function (Blueprint $table) {
            $table->timestampTz('command_sent_at')->nullable();
            $table->timestampTz('scan_received_at')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rfid_registration_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'command_sent_at',
                'scan_received_at',
                'failure_code',
                'failure_message',
            ]);
        });
    }
};
