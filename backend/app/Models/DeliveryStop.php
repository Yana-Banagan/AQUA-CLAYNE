<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStop extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'delivery_route_id',
        'order_id',
        'delivery_id',
        'stop_sequence',
        'status',
        'arrived_at',
        'delivered_at',
        'failure_reason',
    ];

    protected $casts = [
        'stop_sequence' => 'integer',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
