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
        User::updateOrCreate(['email' => 'aquaclayne.noreply@gmail.com'], [
            'name' => 'Aqua Clayne Admin',
            'username' => 'admin',
            'phone' => '09170000000',
            'password_hash' => Hash::make('aquaclaynenianton'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'staff@aquaclayne.test'], [
            'name' => 'Aqua Clayne Staff',
            'username' => 'staff',
            'phone' => '09171112233',
            'password_hash' => Hash::make('password123'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $customerUser = User::updateOrCreate(['email' => 'juan2@example.com'], [
            'name' => 'Juan Customer',
            'username' => 'juan2',
            'phone' => '09171234567',
            'password_hash' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);
        $customer = Customer::firstOrCreate(['user_id' => $customerUser->id]);

        \App\Models\CustomerAddress::firstOrCreate([
            'customer_id' => $customer->id,
            'label' => 'Home',
        ], [
            'address_line' => '123 Rizal Street, Poblacion',
            'barangay' => 'Poblacion I',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'zip_code' => '4009',
            'latitude' => 14.2814,
            'longitude' => 121.4169,
            'is_default' => true,
        ]);

        \App\Models\CreditAccount::firstOrCreate([
            'customer_id' => $customer->id,
        ], [
            'balance' => 0.00,
            'credit_limit' => 1500.00,
        ]);

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

        $catalog = [
            [
                'name' => 'Purified Water Refill',
                'description' => 'Multi-stage purified water with reverse osmosis and UV filtration. Customer provides container.',
                'price' => 35.00,
                'product_type' => 'refill',
                'is_active' => true,
            ],
            [
                'name' => 'Mineral Water Refill',
                'description' => 'Natural mineral-enriched drinking water replenishing essential electrolytes and minerals.',
                'price' => 40.00,
                'product_type' => 'refill',
                'is_active' => true,
            ],
            [
                'name' => 'Alkaline Water Refill',
                'description' => 'High pH antioxidant water with enhanced alkalinity (pH 8.5-9.5) and superior cellular hydration.',
                'price' => 50.00,
                'product_type' => 'refill',
                'is_active' => false,
            ],
            [
                'name' => 'New Gallon with Water',
                'description' => 'A brand-new 5-gallon container filled with purified water. No empty container pickup needed.',
                'price' => 200.00,
                'product_type' => 'new_gallon',
                'is_active' => true,
            ],
        ];

        foreach ($catalog as $item) {
            $product = \App\Models\Product::updateOrCreate(
                ['name' => $item['name']],
                $item
            );

            \App\Models\Inventory::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'quantity_on_hand' => 999,
                    'reorder_level' => 10,
                ]
            );
        }
    }
}
