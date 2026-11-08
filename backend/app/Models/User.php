<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Services\SupabaseStorageService;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password_hash',
        'role',
        'status',
        'fcm_token',
        'avatar_path',
    ];

    protected $hidden = [
        'password_hash',
        'fcm_token',
    ];

    /**
     * The application stores password hashes in `password_hash` instead of
     * Laravel's conventional `password` column.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (array_key_exists('avatar_url', $this->attributes)) {
            return $this->attributes['avatar_url'];
        }

        if (!$this->avatar_path) {
            return $this->socialAccounts()->first()?->avatar_url;
        }

        try {
            return app(SupabaseStorageService::class)
                ->signedProfilePhotoUrl((string) $this->id, $this->avatar_path);
        } catch (\Throwable) {
            return $this->socialAccounts()->first()?->avatar_url;
        }
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function rfidTransactions(): HasMany
    {
        return $this->hasMany(RfidTransaction::class, 'scanned_by');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'performed_by');
    }

    public function complaintHistory(): HasMany
    {
        return $this->hasMany(ComplaintHistory::class, 'changed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
