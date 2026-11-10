<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminSharedDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertArrayHasKey('waitingInQueue', $counts1);
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
            'waitingInQueue' => 99,
        ]);

        $counts = $service->getSidebarCounts($this->adminUser);
        $this->assertEquals(42, $counts['pendingOrders']);
        $this->assertEquals(99, $counts['waitingInQueue']);
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
                'waiting_in_queue',
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
            '/admin/refill-queue',
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
}
