<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidTag extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'gallon_id',
        'tag_uid',
    ];

    public function gallon(): BelongsTo
    {
        return $this->belongsTo(Gallon::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class);
    }
}
