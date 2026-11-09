<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Gallon extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'status',
    ];

    public function rfidTag(): HasOne
    {
        return $this->hasOne(RfidTag::class);
    }
}
