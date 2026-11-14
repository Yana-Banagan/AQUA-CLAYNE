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
        'customer_container_id',
        'customer_container_rfid_tag_id',
        'device_id',
        'order_id',
        'action',
        'transaction_type',
        'previous_status',
        'new_status',
        'scan_result',
        'message',
        'metadata',
        'scan_id',
        'scanned_by',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'scanned_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class);
    }

    public function customerContainer(): BelongsTo
    {
        return $this->belongsTo(CustomerContainer::class, 'customer_container_id');
    }

    public function customerContainerTag(): BelongsTo
    {
        return $this->belongsTo(CustomerContainerRfidTag::class, 'customer_container_rfid_tag_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RfidDevice::class, 'device_id');
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
