<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'paid_at',
        'gateway',
        'gateway_reference',
        'gateway_checkout_session_id',
        'gateway_checkout_idempotency_key',
        'gateway_payment_intent_id',
        'gateway_payment_id',
        'gateway_fee',
        'gateway_net_amount',
        'failure_reason',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'gateway_net_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
