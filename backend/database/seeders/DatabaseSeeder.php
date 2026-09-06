<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@aquaclayne.test'], [
            'name' => 'Aqua Clayne Admin',
            'phone' => '09170000000',
            'password_hash' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $customerUser = User::updateOrCreate(['email' => 'juan2@example.com'], [
            'name' => 'Juan Customer',
            'phone' => '09171234567',
            'password_hash' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        Customer::firstOrCreate(['user_id' => $customerUser->id]);

        $driverUser = User::updateOrCreate(['email' => 'testdriver123@example.com'], [
            'name' => 'Test Driver',
            'phone' => '09179876543',
            'password_hash' => Hash::make('password123'),
            'role' => 'driver',
            'status' => 'active',
        ]);
        Driver::updateOrCreate(['user_id' => $driverUser->id], [
            'user_id' => $driverUser->id,
            'license_number' => 'DEV-001',
            'vehicle_plate' => 'DEV-123',
        ]);
    }
}
