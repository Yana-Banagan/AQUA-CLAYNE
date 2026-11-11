<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfid_devices', function (Blueprint $table) {
            $id = $table->uuid('id')->primary();
            if (DB::getDriverName() === 'pgsql') {
                $id->default(DB::raw('gen_random_uuid()'));
            }

            $table->string('device_code', 50)->unique();
            $table->string('device_name', 100);
            $table->string('location', 150);
            $table->string('device_token_hash', 64);
            $table->string('status', 20)->default('online');
            $table->string('firmware_version', 30)->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->string('last_ip_address', 45)->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index('device_token_hash');
        });

        // Insert initial station scanner device
        $deviceId = (string) \Illuminate\Support\Str::uuid();
        $defaultToken = 'ac_dev_rfid_station_bay1_secret2026';
        $tokenHash = hash('sha256', $defaultToken);

        DB::table('rfid_devices')->insert([
            'id' => $deviceId,
            'device_code' => 'ESP32-STA-01',
            'device_name' => 'Main Station RFID Scanner',
            'location' => 'Station Refill Bay 1',
            'device_token_hash' => $tokenHash,
            'status' => 'online',
            'firmware_version' => '1.0.0',
            'last_heartbeat_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_devices');
    }
};

