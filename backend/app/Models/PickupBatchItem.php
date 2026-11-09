<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupBatchItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pickup_batch_id',
        'order_id',
        'stop_sequence',
        'expected_gallons',
        'received_gallons',
        'accepted_gallons',
        'damaged_count',
        'rejected_count',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'stop_sequence' => 'integer',
        'expected_gallons' => 'integer',
        'received_gallons' => 'integer',
        'accepted_gallons' => 'integer',
        'damaged_count' => 'integer',
        'rejected_count' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PickupBatch::class, 'pickup_batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
