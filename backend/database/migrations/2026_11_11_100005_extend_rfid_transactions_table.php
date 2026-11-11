<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rfid_transactions', function (Blueprint $table) {
            $table->uuid('rfid_tag_id')->nullable()->change();

            $table->foreignUuid('customer_container_id')
                ->nullable()
                ->after('rfid_tag_id')
                ->constrained('customer_containers')
                ->nullOnDelete();

            $table->foreignUuid('customer_container_rfid_tag_id')
                ->nullable()
                ->after('customer_container_id')
                ->constrained('customer_container_rfid_tags')
                ->nullOnDelete();

            $table->foreignUuid('device_id')
                ->nullable()
                ->after('customer_container_rfid_tag_id')
                ->constrained('rfid_devices')
                ->nullOnDelete();

            $table->string('transaction_type', 50)->nullable()->after('order_id');
            $table->string('previous_status', 50)->nullable()->after('transaction_type');
            $table->string('new_status', 50)->nullable()->after('previous_status');
            $table->string('scan_result', 20)->default('accepted')->after('new_status');
            $table->text('message')->nullable()->after('scan_result');
            $table->json('metadata')->nullable()->after('message');
            $table->timestampTz('scanned_at')->nullable()->after('metadata');

            $table->index(['customer_container_id', 'created_at']);
            $table->index(['transaction_type', 'scan_result']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('rfid_transactions', function (Blueprint $table) {
            $table->dropForeign(['customer_container_id']);
            $table->dropForeign(['customer_container_rfid_tag_id']);
            $table->dropForeign(['device_id']);
            $table->dropColumn([
                'customer_container_id',
                'customer_container_rfid_tag_id',
                'device_id',
                'transaction_type',
                'previous_status',
                'new_status',
                'scan_result',
                'message',
                'metadata',
                'scanned_at',
            ]);
        });
    }
};

