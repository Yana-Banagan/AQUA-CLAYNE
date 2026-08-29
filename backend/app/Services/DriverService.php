<?php

namespace App\Services;

use App\Models\User;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverService
{
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $tempPassword = Str::random(10);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($tempPassword),
                'role' => 'driver',
                'status' => 'active',
            ]);

            $driver = Driver::create([
                'user_id' => $user->id,
                'license_number' => $data['license_number'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
            ]);

            return [
                'user' => $user,
                'driver' => $driver,
                'temp_password' => $tempPassword,
            ];
        });
    }

    public function setStatus(
        Driver $driver,
        string $status
    ): void {
        $driver->user->update([
            'status' => $status
        ]);
    }
}
