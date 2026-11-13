<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RfidDevice;
use App\Models\User;
use Illuminate\Support\Str;

class RfidDeviceService
{
    /**
     * Generate a cryptographically secure plain device token.
     */
    public function generatePlainToken(): string
    {
        return 'ac_dev_' . Str::random(32);
    }

    /**
     * Register a new RFID device.
     */
    public function registerDevice(array $data, User $actor): array
    {
        $plainToken = $this->generatePlainToken();
        $tokenHash = hash('sha256', $plainToken);

        $device = RfidDevice::create([
            'device_code' => strtoupper($data['device_code']),
            'device_name' => $data['device_name'],
            'location' => $data['location'],
            'device_token_hash' => $tokenHash,
            'status' => 'offline',
            'firmware_version' => $data['firmware_version'] ?? '1.0.0',
        ]);

        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'rfid.device_registered',
            'table_name' => 'rfid_devices',
            'record_id' => $device->id,
        ]);

        return [
            'device' => $device,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Rotate the security credential for an existing device.
     */
    public function rotateToken(RfidDevice $device, User $actor): string
    {
        $plainToken = $this->generatePlainToken();
        $device->device_token_hash = hash('sha256', $plainToken);
        $device->save();

        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'rfid.device_token_rotated',
            'table_name' => 'rfid_devices',
            'record_id' => $device->id,
        ]);

        return $plainToken;
    }

    /**
     * Count currently online devices (heartbeat within 2 minutes).
     */
    public function getOnlineDevicesCount(): int
    {
        return RfidDevice::where('status', 'online')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>=', now()->subMinutes(2))
            ->count();
    }

    /**
     * Count offline or non-responsive devices.
     */
    public function getOfflineDevicesCount(): int
    {
        return RfidDevice::where(function ($q) {
            $q->where('status', '!=', 'online')
              ->orWhereNull('last_heartbeat_at')
              ->orWhere('last_heartbeat_at', '<', now()->subMinutes(2));
        })->count();
    }
}

