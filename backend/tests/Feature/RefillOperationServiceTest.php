<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\RefillOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefillOperationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Customer $customer;
    private CustomerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $customerUser = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $this->customer = Customer::create(['user_id' => $customerUser->id]);
        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'address_line' => '123 Test Street',
        ]);
    }

    public function test_clean_intake_starts_refilling_and_records_both_status_changes(): void
    {
        $order = $this->createPickedUpOrder();

        app(RefillOperationService::class)->receiveIntake($order, [
            'received_count' => 2,
            'accepted_count' => 2,
        ], $this->actor);

        $this->assertSame('refilling', $order->fresh()->status);
        $this->assertDatabaseCount('order_status_history', 2);
        $this->assertDatabaseHas('order_container_intakes', [
            'order_id' => $order->id,
            'accepted_count' => 2,
        ]);
    }

    public function test_intake_waits_at_station_while_another_order_is_refilling(): void
    {
        $service = app(RefillOperationService::class);
        $firstOrder = $this->createPickedUpOrder();
        $secondOrder = $this->createPickedUpOrder();

        $cleanIntake = ['received_count' => 2, 'accepted_count' => 2];
        $service->receiveIntake($firstOrder, $cleanIntake, $this->actor);
        $service->receiveIntake($secondOrder, $cleanIntake, $this->actor);

        $this->assertSame('refilling', $firstOrder->fresh()->status);
        $this->assertSame('received_at_station', $secondOrder->fresh()->status);

        $service->complete($firstOrder, $this->actor);
        $service->start($secondOrder, $this->actor);

        $this->assertSame('refilling', $secondOrder->fresh()->status);
    }

    public function test_intake_discrepancy_holds_refill_until_resolution(): void
    {
        $service = app(RefillOperationService::class);
        $order = $this->createPickedUpOrder();

        $intake = $service->receiveIntake($order, [
            'received_count' => 2,
            'accepted_count' => 1,
            'damaged_count' => 1,
        ], $this->actor);

        $this->assertNull($intake->completed_at);
        $this->assertSame('received_at_station', $order->fresh()->status);

        $service->resolveIntake($order, [
            'accepted_count' => 2,
            'damaged_count' => 0,
            'rejected_count' => 0,
            'resolution_reason' => 'Inspection confirmed both containers are usable.',
        ], $this->actor);

        $this->assertSame('refilling', $order->fresh()->status);
        $this->assertNotNull($order->containerIntake()->firstOrFail()->completed_at);
    }

    public function test_intake_rejects_counts_that_exceed_received_gallons(): void
    {
        $order = $this->createPickedUpOrder();

        try {
            app(RefillOperationService::class)->receiveIntake($order, [
                'received_count' => 2,
                'accepted_count' => 1,
                'damaged_count' => 1,
                'rejected_count' => 1,
            ], $this->actor);
            $this->fail('Invalid intake counts should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accepted_count', $exception->errors());
        }

        $this->assertSame('picked_up', $order->fresh()->status);
        $this->assertDatabaseMissing('order_container_intakes', ['order_id' => $order->id]);
    }

    private function createPickedUpOrder(): Order
    {
        return Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'delivery_method' => 'home_delivery',
            'delivery_fee' => 30,
            'status' => 'picked_up',
            'total_amount' => 100,
            'order_number' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'expected_empty_gallons' => 2,
            'container_ownership' => 'customer_owned',
        ]);
    }
}
