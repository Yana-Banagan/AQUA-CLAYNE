<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'driver_id',
        'eta',
        'proof_photo_url',
        'proof_status',
        'signature_url',
        'status',
        'delivered_at',
        'failure_reason',
        'customer_confirmed',
        'driver_latitude',
        'driver_longitude',
        'location_updated_at',
        'assignment_source',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'eta' => 'datetime',
            'created_at' => 'datetime',
            'delivered_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'assigned_at' => 'datetime',
            'customer_confirmed' => 'boolean',
            'driver_latitude' => 'float',
            'driver_longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class);
    }
}
