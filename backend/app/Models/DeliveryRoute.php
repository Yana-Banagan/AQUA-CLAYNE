<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'route_number',
        'driver_id',
        'area',
        'status',
        'total_gallons',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'total_gallons' => 'integer',
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

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('stop_sequence');
    }
}
