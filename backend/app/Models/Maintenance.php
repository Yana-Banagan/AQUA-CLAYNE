<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use HasUuids;

    protected $table = 'maintenance';

    public $timestamps = false;

    protected $fillable = [
        'equipment_name',
        'schedule_interval_days',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
