<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'role',
        'status',
        'fcm_token',
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
