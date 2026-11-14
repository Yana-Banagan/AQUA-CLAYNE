<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'address_id',
        'delivery_method',
        'delivery_fee',
        'status',
        'total_amount',
        'order_number',
        'client_request_id',
        'container_ownership',
        'expected_empty_gallons',
        'payment_method',
        'notes',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
    ];

    protected $hidden = ['qr_token'];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->qr_token ??= hash('sha256', Str::random(80));
        });
    }

    public function qrPayload(): string
    {
        return 'AQUA-CLAYNE:' . $this->qr_token;
    }

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isStationPickup(): bool
    {
        return ($this->delivery_method ?? 'home_delivery') === 'station_pickup';
    }

    public function isHomeDelivery(): bool
    {
        return !$this->isStationPickup();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(
            CustomerAddress::class,
            'address_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function containerIntake(): HasOne
    {
        return $this->hasOne(OrderContainerIntake::class);
    }

    public function pickupBatchItem(): HasOne
    {
        return $this->hasOne(PickupBatchItem::class);
    }

    public function deliveryStop(): HasOne
    {
        return $this->hasOne(DeliveryStop::class);
    }

    public function rfidTransactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class);
    }
}
