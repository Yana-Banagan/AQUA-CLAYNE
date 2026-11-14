<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DriverService
{
    public function create(array $data, ?User $actor = null): array
    {
        return DB::transaction(function () use ($data, $actor) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($data['password']),
                'role' => 'driver',
                'status' => 'active',
            ]);

            $driver = Driver::create([
                'user_id' => $user->id,
                'license_number' => $data['license_number'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
            ]);

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'driver.created',
                'table_name' => 'drivers',
                'record_id' => $driver->id,
            ]);

            return [
                'user' => $user,
                'driver' => $driver,
            ];
        });
    }

    public function setStatus(
        Driver $driver,
        string $status,
        ?User $actor = null
    ): void {
        $driver->user->update([
            'status' => $status,
        ]);

        AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => 'driver.status_changed',
            'table_name' => 'drivers',
            'record_id' => $driver->id,
        ]);
    }

    public function update(Driver $driver, array $data, ?User $actor = null): void
    {
        DB::transaction(function () use ($driver, $data, $actor) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];
            if (array_key_exists('status', $data)) {
                $userData['status'] = $data['status'];
            }
            $driver->user->update($userData);

            $driverData = [];
            foreach (['license_number', 'vehicle_plate'] as $field) {
                if (array_key_exists($field, $data)) {
                    $driverData[$field] = $data[$field];
                }
            }
            if ($driverData) {
                $driver->update($driverData);
            }

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'driver.updated',
                'table_name' => 'drivers',
                'record_id' => $driver->id,
            ]);
        });
    }
}
