<?php

namespace Tests\Feature;

use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\PickupBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deliveries_page_renders_with_history_batches_and_routes(): void
    {
        $admin = User::create([
            'name' => 'Station Admin',
            'email' => 'admin@aquaclayne.test',
            'role' => 'admin',
            'status' => 'active',
            'password_hash' => bcrypt('secret123'),
        ]);

        $driverUser = User::create([
            'name' => 'Juan Driver',
            'email' => 'driver@aquaclayne.test',
            'role' => 'driver',
            'status' => 'active',
            'password_hash' => bcrypt('secret123'),
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'vehicle_plate' => 'ABC-1234',
            'license_number' => 'N01-12-345678',
        ]);

        PickupBatch::create([
            'batch_number' => 'PB-20261115-001',
            'driver_id' => $driver->id,
            'area' => 'Gatid',
            'status' => 'completed',
            'total_expected_gallons' => 20,
            'completed_at' => now(),
            'created_by' => $admin->id,
        ]);

        DeliveryRoute::create([
            'route_number' => 'RT-20261115-001',
            'driver_id' => $driver->id,
            'area' => 'Pagsawitan',
            'status' => 'completed',
            'total_gallons' => 25,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/deliveries');

        $response->assertStatus(200);
        $response->assertSee('PB-20261115-001');
        $response->assertSee('RT-20261115-001');
        $response->assertSee('20 gal');
        $response->assertSee('25 gal');
    }
}
