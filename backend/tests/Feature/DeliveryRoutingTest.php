<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryRoutingTest extends TestCase
{
    /** Test that the station endpoint returns official Aqua Clayne station origin and Santa Cruz bounds */
    public function test_station_endpoint_returns_aqua_clayne_official_origin_and_bounds(): void
    {
        $response = $this->getJson('/api/v1/station');

        $response->assertStatus(200)
            ->assertJsonPath('data.latitude', 14.265111)
            ->assertJsonPath('data.longitude', 121.405602)
            ->assertJsonPath('data.name', 'Aqua Clayne Water Refilling Station')
            ->assertJsonPath('data.bounds.min_latitude', 14.22)
            ->assertJsonPath('data.bounds.max_latitude', 14.34)
            ->assertJsonPath('data.bounds.min_longitude', 121.37)
            ->assertJsonPath('data.bounds.max_longitude', 121.47);

        $this->assertStringContainsString('007 Batisan St., Brgy. Gatid', (string) $response->json('data.address'));
    }

    /** Test that arbitrary route requests without fixed station origin are strictly rejected */
    public function test_arbitrary_route_request_is_rejected(): void
    {
        $user = User::factory()->make(['role' => 'admin']);

        $response = $this->actingAs($user)->getJson('/api/v1/routing/route');

        $response->assertStatus(422);
        $this->assertStringContainsString('Aqua Clayne station', (string) $response->json('message'));
    }
}
