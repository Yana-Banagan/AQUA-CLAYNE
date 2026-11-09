<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'batch_number',
        'driver_id',
        'area',
        'status',
        'total_expected_gallons',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'total_expected_gallons' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickupBatchItem::class)->orderBy('stop_sequence');
    }
}
