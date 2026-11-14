<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Driver;
use App\Models\User;
use App\Services\AdminSharedDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'name' => 'Station Admin',
            'email' => 'admin@aquaclayne.test',
        ]);
    }

    public function test_shared_data_service_combines_counts_and_memoizes_in_memory(): void
    {
        $service = new AdminSharedDataService();

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        // First call executes exactly 1 combined query
        $counts1 = $service->getSidebarCounts($this->adminUser);
        $this->assertIsArray($counts1);
        $this->assertArrayHasKey('pendingOrders', $counts1);
        $this->assertArrayHasKey('activeDeliveries', $counts1);
        $this->assertArrayHasKey('lowStock', $counts1);
        $this->assertArrayHasKey('unreadNotifications', $counts1);
        $this->assertArrayHasKey('unreadChats', $counts1);

        $initialQueryCount = count($queries);

        // Second call on the same service instance MUST use in-memory cache and execute 0 extra queries
        $counts2 = $service->getSidebarCounts($this->adminUser);
        $this->assertSame($counts1, $counts2);
        $this->assertCount($initialQueryCount, $queries, 'In-memory memoization must prevent duplicate queries.');
    }

    public function test_shared_data_service_respects_provided_counts(): void
    {
        $service = new AdminSharedDataService();
        $service->provide([
            'pendingOrders' => 42,
            'activeDeliveries' => 99,
        ]);

        $counts = $service->getSidebarCounts($this->adminUser);
        $this->assertEquals(42, $counts['pendingOrders']);
        $this->assertEquals(99, $counts['activeDeliveries']);
    }

    public function test_admin_status_counts_endpoint_returns_combined_data(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin/status-counts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pending_orders',
                'unread_chats',
                'unread_notifications',
                'active_deliveries',
                'low_stock',
                'notifications',
            ]);
    }

    public function test_admin_notifications_recent_endpoint_returns_on_demand_list(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin/notifications/recent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'notifications',
                'unread_notifications',
            ]);
    }

    public function test_admin_pages_render_successfully_with_shared_shell(): void
    {
        $pages = [
            '/admin/dashboard',
            '/admin/orders',
            '/admin/deliveries',
            '/admin/customers',
            '/admin/complaints',
            '/admin/chat',
            '/admin/notifications',
            '/admin/payments',
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($this->adminUser)->get($url);
            $response->assertStatus(200);
            $response->assertSee('id="sidebar"', false);
            $response->assertSee('id="notifButton"', false);
        }
    }

    public function test_rfid_page_shows_register_tags_action_and_existing_modal(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/rfid')
            ->assertOk()
            ->assertSee('id="registerRfidTagButton"', false)
            ->assertSee('+ Register Tags')
            ->assertSee('id="registerRfidModal"', false)
            ->assertSee('onclick="openRegisterRfidModal()"', false);
    }

    public function test_chat_conversations_are_paginated(): void
    {
        foreach (range(1, 31) as $index) {
            $participant = User::factory()->create(['role' => 'customer', 'status' => 'active']);
            ChatConversation::create([
                'participant_id' => $participant->id,
                'participant_role' => 'customer',
                'last_message_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($this->adminUser)
            ->get('/admin/chat')
            ->assertOk()
            ->assertSee('Next');
    }

    public function test_admin_pages_do_not_make_remote_avatar_signing_requests(): void
    {
        Http::fake();
        $this->adminUser->forceFill(['avatar_path' => 'admin/avatar.png'])->save();

        $driverUser = User::factory()->create([
            'role' => 'driver',
            'status' => 'active',
            'name' => 'Dispatch Driver',
            'avatar_path' => 'drivers/avatar.png',
        ]);
        Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'TEST-123',
            'vehicle_plate' => 'TEST-01',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/deliveries');

        $response->assertOk();
        $this->actingAs($this->adminUser)->get('/admin/dashboard')->assertOk();
        $this->actingAs($this->adminUser)->get('/admin/chat')->assertOk();
        $this->actingAs($this->adminUser)->get('/admin/settings')->assertOk();

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'status' => 'active',
            'name' => 'Dispatch Staff',
            'avatar_path' => 'staff/avatar.png',
        ]);
        $this->actingAs($staffUser)->get('/admin/deliveries')->assertOk();

        $sentPaths = Http::recorded()->map(fn ($pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->all();
        $this->assertSame([], $sentPaths, 'Admin pages must not make remote HTTP requests while rendering.');
    }

    public function test_admin_reports_handle_malformed_cached_collections_gracefully(): void
    {
        Cache::store('file')->put('admin:reports:all:all', [
            'topServices' => 'bad cache payload',
            'driverPerformances' => 'bad cache payload',
        ]);

        $this->actingAs($this->adminUser)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('No service sales data yet.');
    }

    public function test_admin_settings_and_reports_do_not_reference_queue_workflow(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('Queue Dispatch Policy')
            ->assertDontSee('Operational Refill & Queue Parameters');

        $this->actingAs($this->adminUser)
            ->get('/admin/reports')
            ->assertOk()
            ->assertDontSee('Queue / Transit');
    }

    public function test_removed_station_queue_route_returns_not_found(): void
    {
        $this->assertFalse(Schema::hasTable('refill_queue_entries'));

        $this->actingAs($this->adminUser)
            ->get('/admin/refill-queue')
            ->assertNotFound();
    }
}
