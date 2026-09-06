<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'changed_by', 'actor_role', 'reason', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
