<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerContainerRfidTag extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_container_id',
        'uid',
        'status',
        'registered_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(CustomerContainer::class, 'customer_container_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class, 'customer_container_rfid_tag_id');
    }
}

