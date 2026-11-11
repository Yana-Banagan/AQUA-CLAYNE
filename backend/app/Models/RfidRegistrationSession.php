<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidRegistrationSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_container_id',
        'station_device_id',
        'created_by',
        'status',
        'expires_at',
        'completed_at',
        'scanned_uid',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(CustomerContainer::class, 'customer_container_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RfidDevice::class, 'station_device_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}

