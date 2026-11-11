<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_code',
        'device_name',
        'location',
        'device_token_hash',
        'status',
        'firmware_version',
        'last_heartbeat_at',
        'last_ip_address',
    ];

    protected $hidden = [
        'device_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function registrationSessions(): HasMany
    {
        return $this->hasMany(RfidRegistrationSession::class, 'station_device_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class, 'device_id');
    }

    /**
     * Determine if device is considered currently online (heartbeat within 2 minutes).
     */
    public function isOnline(): bool
    {
        return $this->status === 'online'
            && $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->gte(now()->subMinutes(2));
    }

    /**
     * Record heartbeat.
     */
    public function markHeartbeat(?string $ip = null, ?string $firmwareVersion = null): void
    {
        $this->last_heartbeat_at = now();
        if ($ip) {
            $this->last_ip_address = $ip;
        }
        if ($firmwareVersion) {
            $this->firmware_version = $firmwareVersion;
        }
        if ($this->status === 'offline') {
            $this->status = 'online';
        }
        $this->save();
    }
}

