<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'rfid_tag_id',
        'order_id',
        'action',
        'scanned_by',
    ];

    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
