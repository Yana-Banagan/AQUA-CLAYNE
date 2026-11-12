<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticDriverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_fifteen_confirmed_home_delivery_orders_are_assigned_to_test_driver(): void
    {
        config()->set('dispatch.assignment_batch_size', 15);
        config()->set('dispatch.primary_driver_email', 'testdriver123@example.com');

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create([
            'name' => 'Test Driver',
            'email' => 'testdriver123@example.com',
            'role' => 'driver',
            'status' => 'active',
        ]);

        Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'DEV-001',
            'vehicle_plate' => 'DEV-123',
        ]);

        $customerUser = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'address_line' => '123 Sample Street',
            'barangay' => 'Gatid',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'zip_code' => '4009',
            'latitude' => 14.2814,
            'longitude' => 121.4169,
            'is_default' => true,
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'delivery_method' => 'home_delivery',
                'delivery_fee' => 5.00,
                'status' => 'confirmed',
                'order_number' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'total_amount' => 100.00,
                'payment_method' => 'cash',
                'expected_empty_gallons' => 1,
                'container_ownership' => 'customer_owned',
                'notes' => 'test order ' . $i,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => 'cash',
                'amount' => 100.00,
                'status' => 'pending',
            ]);
        }

        $driver = Driver::query()->where('user_id', $driverUser->id)->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.orders.confirm-all'))
            ->assertOk();

        $assigned = Delivery::query()->where('driver_id', $driver->id)->count();

        $this->assertSame(15, $assigned);
        $this->assertDatabaseCount('deliveries', 15);
    }
}
