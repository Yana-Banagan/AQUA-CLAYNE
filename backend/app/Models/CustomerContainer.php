<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerContainer extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'container_code',
        'container_type',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rfidTags(): HasMany
    {
        return $this->hasMany(CustomerContainerRfidTag::class);
    }

    public function activeTag(): HasOne
    {
        return $this->hasOne(CustomerContainerRfidTag::class)->where('status', 'active');
    }

    public function registrationSessions(): HasMany
    {
        return $this->hasMany(RfidRegistrationSession::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class);
    }
}

