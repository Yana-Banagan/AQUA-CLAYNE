<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RealtimeEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'realtime_events';

    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'event',
        'status',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
