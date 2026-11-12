<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SocialAuthAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'flow',
        'provider',
        'state_hash',
        'code_hash',
        'user_id',
        'expires_at',
        'state_used_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'state_used_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
