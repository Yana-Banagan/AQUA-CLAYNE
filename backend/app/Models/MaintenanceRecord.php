<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'maintenance_id',
        'performed_by',
        'notes',
        'cost',
        'equipment_status',
        'performed_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'performed_at' => 'datetime',
    ];

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
