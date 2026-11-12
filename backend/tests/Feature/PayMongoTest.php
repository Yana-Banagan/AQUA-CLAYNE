<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PayMongoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'customer']);
        $this->customer = Customer::create([
            'user_id' => $this->user->id,
            'customer_type' => 'residential',
        ]);
        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'address_line' => '123 Test St, Test Barangay, Caloocan',
            'street' => '123 Test St',
            'barangay' => 'Test Barangay',
            'city' => 'Caloocan',
            'province' => 'Metro Manila',
            'is_default' => true,
        ]);
        $this->product = Product::first() ?? Product::create([
            'name' => 'Purified Water 5 Gallons',
            'price' => 35.00,
            'is_active' => true,
            'product_type' => 'refill',
        ]);
    }

    public function test_customer_can_create_checkout_session(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'status' => 'pending',
            'order_number' => 'TEST-01',
            'container_ownership' => 'customer_owned',
            'expected_empty_gallons' => 1,
            'payment_method' => 'online',
            'total_amount' => 35.00,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 35.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'method' => 'online',
            'amount' => 35.00,
            'status' => 'pending',
            'gateway' => 'paymongo',
            'gateway_reference' => "AC-ORDER-{$order->id}",
        ]);

        $this->mock(PayMongoService::class, function ($mock) {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://checkout.paymongo.com/test_session_123',
                    'checkout_session_id' => 'cs_test_session_123',
                    'payment_id' => 'test-id',
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/customer/orders/{$order->id}/payment/checkout");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'checkout_url' => 'https://checkout.paymongo.com/test_session_123',
            'data' => [
                'checkout_url' => 'https://checkout.paymongo.com/test_session_123',
                'checkout_session_id' => 'cs_test_session_123',
            ],
        ]);
    }

    public function test_checkout_status_retrieval_uses_the_configured_paymongo_api_version(): void
    {
        config([
            'services.paymongo.secret_key' => 'sk_test_paymongo_test_key',
            'services.paymongo.api_url' => 'https://api.paymongo.com/v2',
        ]);
        Http::fake([
            'https://api.paymongo.com/v2/checkout_sessions/cs_test_status' => Http::response([
                'data' => [
                    'id' => 'cs_test_status',
                    'attributes' => ['status' => 'active'],
                ],
            ], 200),
        ]);

        $session = app(PayMongoService::class)->retrieveCheckout('cs_test_status');

        $this->assertSame('cs_test_status', data_get($session, 'data.id'));
        Http::assertSent(fn ($request) =>
            $request->method() === 'GET' &&
            $request->url() === 'https://api.paymongo.com/v2/checkout_sessions/cs_test_status'
        );
    }

    public function test_checkout_creates_payment_if_missing(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'status' => 'pending',
            'order_number' => 'TEST-02',
            'container_ownership' => 'new_gallon',
            'expected_empty_gallons' => 0,
            'payment_method' => 'cash',
            'total_amount' => 200.00,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 200.00,
        ]);

        $this->mock(PayMongoService::class, function ($mock) {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://checkout.paymongo.com/test_session_456',
                    'checkout_session_id' => 'cs_test_session_456',
                    'payment_id' => 'test-id',
                ]);
        });

        // Customer attempts online payment on order originally missing online payment record
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/customer/orders/{$order->id}/payment/checkout");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'checkout_url' => 'https://checkout.paymongo.com/test_session_456',
        ]);

        // Verifies payment record was created
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'online',
            'status' => 'pending',
        ]);
    }

    public function test_payment_status_and_simulate_success(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'status' => 'pending',
            'order_number' => 'TEST-03',
            'container_ownership' => 'customer_owned',
            'expected_empty_gallons' => 1,
            'payment_method' => 'online',
            'total_amount' => 35.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'online',
            'amount' => 35.00,
            'status' => 'pending',
            'gateway' => 'paymongo',
            'gateway_reference' => "AC-ORDER-{$order->id}",
            'gateway_checkout_session_id' => 'cs_test_session_pending',
        ]);

        $this->mock(PayMongoService::class, function ($mock) {
            $mock->shouldReceive('retrieveCheckout')
                ->once()
                ->andReturn([
                    'data' => [
                        'attributes' => [
                            'status' => 'active',
                            'payments' => [],
                        ],
                    ],
                ]);
        });

        // Check payment status while still pending
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customer/orders/{$order->id}/payment");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'pending',
            ],
        ]);

        // Simulate payment success (testing endpoint)
        $simResponse = $this->actingAs($this->user)
            ->postJson("/api/v1/customer/orders/{$order->id}/payment/simulate-success");

        $simResponse->assertStatus(200);
        $simResponse->assertJson([
            'success' => true,
            'data' => [
                'status' => 'paid',
                'order_status' => 'confirmed',
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_payment_status_auto_syncs_when_paid_on_gateway(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'status' => 'pending',
            'order_number' => 'TEST-04',
            'container_ownership' => 'customer_owned',
            'expected_empty_gallons' => 1,
            'payment_method' => 'online',
            'total_amount' => 35.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'online',
            'amount' => 35.00,
            'status' => 'pending',
            'gateway' => 'paymongo',
            'gateway_reference' => "AC-ORDER-{$order->id}",
            'gateway_checkout_session_id' => 'cs_test_session_paid',
        ]);

        $this->mock(PayMongoService::class, function ($mock) {
            $mock->shouldReceive('retrieveCheckout')
                ->once()
                ->with('cs_test_session_paid')
                ->andReturn([
                    'data' => [
                        'attributes' => [
                            'status' => 'active',
                            'payments' => [
                                [
                                    'id' => 'pay_12345678',
                                    'attributes' => [
                                        'status' => 'paid',
                                        'fee' => 1500,
                                        'net_amount' => 2000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        });

        // Polling payment status automatically transitions pending -> paid
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customer/orders/{$order->id}/payment");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'paid',
                'gateway_payment_id' => 'pay_12345678',
            ],
        ]);

        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_customer_can_request_qrph_checkout_provider(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $this->address->id,
            'status' => 'pending',
            'order_number' => 'TEST-QRPH',
            'container_ownership' => 'customer_owned',
            'expected_empty_gallons' => 1,
            'payment_method' => 'online',
            'total_amount' => 35.00,
        ]);
        $order->items()->create(['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 35.00]);
        Payment::create([
            'order_id' => $order->id,
            'method' => 'online',
            'amount' => 35.00,
            'status' => 'pending',
            'gateway' => 'paymongo',
            'gateway_reference' => "AC-ORDER-{$order->id}",
        ]);

        $this->mock(PayMongoService::class, function ($mock) {
            $mock->shouldReceive('createCheckout')->once()
                ->withArgs(fn ($order, $payment, $provider) => $provider === 'qrph')
                ->andReturn(['checkout_url' => 'https://checkout.paymongo.com/qrph', 'checkout_session_id' => 'cs_qrph']);
        });

        $this->actingAs($this->user)
            ->postJson("/api/v1/customer/orders/{$order->id}/payment/checkout", ['payment_method_type' => 'qrph'])
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/qrph');
    }
}
