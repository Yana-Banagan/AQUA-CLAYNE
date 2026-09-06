<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefillQueueEntry extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'queue_number', 'status', 'priority', 'queued_at', 'started_at', 'completed_at', 'removed_at', 'manual_priority_reason', 'created_by', 'updated_by'];
    protected function casts(): array { return ['queued_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'removed_at' => 'datetime']; }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
