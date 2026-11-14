<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminSharedDataService
{
    /**
     * In-memory memoized sidebar counters for the current request lifecycle.
     */
    private ?array $sidebarCounts = null;

    /**
     * In-memory memoized admin avatar URL for the current request lifecycle.
     */
    private ?string $adminAvatarUrl = null;

    /**
     * In-memory cache of provided counts from controllers.
     */
    private array $providedCounts = [];

    /**
     * Default structure of sidebar and header counters.
     */
    public const DEFAULT_COUNTS = [
        'pendingOrders' => 0,
        'activeDeliveries' => 0,
        'lowStock' => 0,
        'unreadNotifications' => 0,
        'unreadChats' => 0,
    ];

    /**
     * Inject or pre-populate counters already computed by a controller
     * so that subsequent layout rendering requires zero additional database queries.
     */
    public function provide(array $counts): void
    {
        $this->providedCounts = array_merge($this->providedCounts, $counts);
        if ($this->sidebarCounts !== null) {
            $this->sidebarCounts = array_merge($this->sidebarCounts, $counts);
        }
    }

    /**
     * Retrieve shared sidebar and header counters for an authenticated admin/staff user.
     * Uses in-memory memoization per request, short-lived cache (10s), and a single
     * combined ANSI SQL aggregate query covering all 6 system badges in ONE network roundtrip.
     */
    public function getSidebarCounts(?User $user = null, array $overrides = []): array
    {
        if (!empty($overrides)) {
            $this->provide($overrides);
        }

        // Return from in-memory cache if already resolved in this request
        if ($this->sidebarCounts !== null) {
            return array_merge($this->sidebarCounts, $this->providedCounts);
        }

        $user = $user ?? auth()->user();
        if (!$user) {
            $this->sidebarCounts = array_merge(self::DEFAULT_COUNTS, $this->providedCounts);
            return $this->sidebarCounts;
        }

        $userId = (string) $user->getAuthIdentifier();
        $isAdminOrStaff = in_array($user->role, ['admin', 'staff'], true);

        // Fetch or compute combined status counts cached for 10 seconds.
        $cacheKey = "admin:shared-counts:{$userId}";
        $rawCounts = Cache::remember($cacheKey, now()->addSeconds(10), function () use ($userId, $isAdminOrStaff) {
            return $this->executeCombinedCountQuery($userId, $isAdminOrStaff);
        });

        $this->sidebarCounts = array_merge([
            'pendingOrders' => (int) ($rawCounts['pending_orders'] ?? 0),
            'activeDeliveries' => (int) ($rawCounts['active_deliveries'] ?? 0),
            'lowStock' => (int) ($rawCounts['low_stock'] ?? 0),
            'unreadNotifications' => (int) ($rawCounts['unread_notifications'] ?? 0),
            'unreadChats' => $isAdminOrStaff ? (int) ($rawCounts['unread_chats'] ?? 0) : 0,
        ], $this->providedCounts);

        return $this->sidebarCounts;
    }

    /**
     * Execute a single combined database query across PostgreSQL and SQLite
     * that aggregates all administrative indicators in one trip.
     */
    private function executeCombinedCountQuery(string $userId, bool $includeChats): array
    {
        $chatQuery = $includeChats
            ? "(SELECT COUNT(*) FROM chat_messages WHERE sender_role IN ('customer', 'driver') AND read_at IS NULL)"
            : "0";

        $sql = "
            SELECT
                (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending_orders,
                (SELECT COUNT(*) FROM deliveries WHERE status IN ('assigned', 'en_route', 'en_route_for_pickup', 'picked_up', 'at_station', 'out_for_delivery')) AS active_deliveries,
                (SELECT COUNT(*) FROM inventory WHERE quantity_on_hand <= reorder_level) AS low_stock,
                (SELECT COUNT(*) FROM notifications WHERE user_id = :userId AND is_read = false) AS unread_notifications,
                {$chatQuery} AS unread_chats
        ";

        try {
            $row = DB::selectOne($sql, ['userId' => $userId]);
            return [
                'pending_orders' => (int) ($row->pending_orders ?? 0),
                'active_deliveries' => (int) ($row->active_deliveries ?? 0),
                'low_stock' => (int) ($row->low_stock ?? 0),
                'unread_notifications' => (int) ($row->unread_notifications ?? 0),
                'unread_chats' => (int) ($row->unread_chats ?? 0),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Combined status count query failed: ' . $e->getMessage());
            return [
                'pending_orders' => 0,
                'active_deliveries' => 0,
                'low_stock' => 0,
                'unread_notifications' => 0,
                'unread_chats' => 0,
            ];
        }
    }

    /**
     * Retrieve payload for the /admin/status-counts JSON endpoint.
     */
    public function getStatusCounts(?User $user = null, bool $includeNotifications = false): array
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return [
                'pending_orders' => 0,
                'unread_chats' => 0,
                'unread_notifications' => 0,
                'active_deliveries' => 0,
                'low_stock' => 0,
                'notifications' => [],
            ];
        }

        $counts = $this->getSidebarCounts($user);

        return [
            'pending_orders' => $counts['pendingOrders'],
            'unread_chats' => $counts['unreadChats'],
            'unread_notifications' => $counts['unreadNotifications'],
            'active_deliveries' => $counts['activeDeliveries'],
            'low_stock' => $counts['lowStock'],
            'notifications' => $includeNotifications ? $this->getRecentNotifications($user) : [],
        ];
    }

    /**
     * Retrieve recent notifications for the header bell dropdown on demand.
     * Keeps page loads lightweight by not loading notification rows until requested.
     */
    public function getRecentNotifications(?User $user = null, int $limit = 5): array
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return [];
        }

        $userId = (string) $user->getAuthIdentifier();

        return Notification::where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'body', 'is_read', 'created_at'])
            ->map(fn (Notification $notification) => [
                'id' => (string) $notification->id,
                'title' => (string) $notification->title,
                'body' => (string) $notification->body,
                'is_read' => (bool) $notification->is_read,
                'time_ago' => $notification->created_at?->diffForHumans() ?? 'Recently',
            ])
            ->all();
    }

    /**
     * Retrieve signed profile photo URL for the authenticated admin with memoization.
     */
    public function getAdminAvatarUrl(?User $user = null): ?string
    {
        if ($this->adminAvatarUrl !== null) {
            return $this->adminAvatarUrl;
        }

        $user = $user ?? auth()->user();
        if (!$user || !$user->avatar_path) {
            $this->adminAvatarUrl = null;
            return null;
        }

        $this->adminAvatarUrl = app(SupabaseStorageService::class)
            ->cachedProfilePhotoUrl((string) $user->id, $user->avatar_path);

        return $this->adminAvatarUrl;
    }

    /**
     * Invalidate operational caches when relevant database records change.
     */
    public static function invalidate(?string $userId = null): void
    {
        try {
            $cache = Cache::store();
            if ($userId) {
                $cache->forget("admin:shared-counts:{$userId}");
                $cache->forget("admin-status-counts:{$userId}");
                $cache->forget("admin-header-unread-notifications:{$userId}");
                $cache->forget("admin-header-v2:{$userId}");
            }
            $cache->forget('admin-pending-orders-count');
            $cache->forget('admin-unread-chats-count');
            $cache->forget('admin-header-unread-chats');
            $cache->forget('admin:deliveries:stats');
            $cache->forget('admin:orders:stats');
        } catch (\Throwable) {
            // Silently ignore if cache store is unreachable
        }
    }
}

