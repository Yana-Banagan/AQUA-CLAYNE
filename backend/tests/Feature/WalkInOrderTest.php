<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalkInOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkInOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_walk_in_form_omits_contact_input_and_displays_the_current_rate(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertOk()
            ->assertSee('Rate: ₱30.00 per gallon', false)
            ->assertSee('Rate per gallon</span><strong class="walk-in-summary-value">₱30.00', false)
            ->assertSee('const refillRate = 30;', false)
            ->assertSee('id="walkInGallons"', false)
            ->assertDontSee('id="walkInPhone"', false);
    }

    public function test_walk_in_total_is_calculated_by_the_server_and_duplicate_submissions_are_idempotent(): void
    {
        $requestKey = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'request_key' => $requestKey,
            'full_name' => 'Walk In Customer',
            'gallons' => 2,
            'payment_method' => 'cash',
            'amount' => '₱999.00',
        ];

        $this->actingAs($this->admin)
            ->post('/admin/orders/walk-in', $payload)
            ->assertRedirect('/admin/orders');

        $this->assertDatabaseHas('walk_in_orders', [
            'request_key' => $requestKey,
            'full_name' => 'Walk In Customer',
            'gallons' => 2,
            'amount' => '60.00',
            'payment_method' => 'cash',
            'phone' => null,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/orders/walk-in', [...$payload, 'gallons' => 5])
            ->assertRedirect('/admin/orders');

        $this->assertSame(1, WalkInOrder::where('request_key', $requestKey)->count());
        $this->assertSame('60.00', WalkInOrder::where('request_key', $requestKey)->value('amount'));

        $this->actingAs($this->admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('₱60.00', false);

        $this->actingAs($this->admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('₱60.00', false);
    }
}
