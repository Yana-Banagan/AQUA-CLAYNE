<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderContainerIntake extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'expected_count', 'received_count', 'accepted_count', 'damaged_count', 'rejected_count', 'returned_count', 'condition_notes', 'received_by', 'received_at', 'completed_at'];
    protected function casts(): array
    {
        return [
            'expected_count' => 'integer',
            'received_count' => 'integer',
            'accepted_count' => 'integer',
            'damaged_count' => 'integer',
            'rejected_count' => 'integer',
            'returned_count' => 'integer',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
