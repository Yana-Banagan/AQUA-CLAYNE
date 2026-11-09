<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryRestrictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefillAndDeliveryRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_alkaline_products_are_excluded_from_catalog(): void
    {
        $purified = Product::updateOrCreate(
            ['name' => 'Purified Water Refill'],
            [
                'price' => 35.00,
                'product_type' => 'refill',
                'is_active' => true,
            ]
        );

        $alkaline = Product::updateOrCreate(
            ['name' => 'Alkaline Water Refill'],
            [
                'price' => 50.00,
                'product_type' => 'refill',
                'is_active' => false,
            ]
        );

        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Purified Water Refill'));
        $this->assertFalse($names->contains('Alkaline Water Refill'));

        // Show endpoint also rejects alkaline products
        $showRes = $this->getJson('/api/v1/products/' . $alkaline->id);
        $showRes->assertStatus(404);
    }

    public function test_delivery_restriction_service_validates_barangays(): void
    {
        // Gatid, Duhat, Bubukal are allowed
        $this->assertTrue(DeliveryRestrictionService::isAllowedBarangay('Gatid'));
        $this->assertTrue(DeliveryRestrictionService::isAllowedBarangay('Brgy. Duhat'));
        $this->assertTrue(DeliveryRestrictionService::isAllowedBarangay('Bubukal'));
        $this->assertTrue(DeliveryRestrictionService::isAllowedBarangay(null, '007 Batisan St, Gatid, Santa Cruz'));

        // Other barangays are rejected
        $this->assertFalse(DeliveryRestrictionService::isAllowedBarangay('Poblacion I'));
        $this->assertFalse(DeliveryRestrictionService::isAllowedBarangay('San Jose'));
        $this->assertFalse(DeliveryRestrictionService::isAllowedBarangay('Pagsawitan'));
    }

    public function test_address_creation_rejects_outside_barangays(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'phone' => '+639123456789',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Try creating an address outside Gatid/Duhat/Bubukal
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/addresses', [
                'label' => 'Office',
                'address_line' => '123 Rizal St',
                'barangay' => 'Poblacion I',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'zip_code' => '4009',
                'latitude' => 14.2814,
                'longitude' => 121.4169,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment([
            'message' => DeliveryRestrictionService::RESTRICTION_MESSAGE,
        ]);

        // Creating an address in Gatid succeeds
        $resGatid = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/addresses', [
                'label' => 'Home',
                'address_line' => '007 Batisan St',
                'barangay' => 'Gatid',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'zip_code' => '4009',
                'latitude' => 14.265111,
                'longitude' => 121.405602,
            ]);

        $resGatid->assertStatus(201);
    }
}

