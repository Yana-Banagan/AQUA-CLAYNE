<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminStatusController extends Controller
{
    /** Return lightweight counters for the shared admin header in one request. */
    public function counts(Request $request)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json([
                'pending_orders' => 0,
                'unread_chats' => 0,
                'unread_notifications' => 0,
                'notifications' => [],
            ]);
        }

        $forceFresh = $request->boolean('fresh');
        $cacheKey = "admin-status-counts:{$userId}";

        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        $counts = Cache::remember($cacheKey, now()->addSeconds(10), function () use ($userId) {
            // Unread chats for admin/staff
            $unreadChats = Cache::remember('admin-unread-chats-count', now()->addSeconds(10), function () {
                return ChatMessage::whereIn('sender_role', ['customer', 'driver'])
                    ->whereNull('read_at')
                    ->count();
            });

            // Pending orders count
            $pendingOrders = Cache::remember('admin-pending-orders-count', now()->addSeconds(10), function () {
                return Order::where('status', 'pending')->count();
            });

            // Unread notifications and recent list
            $unreadNotifications = Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->count();

            $notifications = Notification::where('user_id', $userId)
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'title', 'body', 'is_read', 'created_at'])
                ->map(fn (Notification $notification) => [
                    'id' => (string) $notification->id,
                    'title' => (string) $notification->title,
                    'body' => (string) $notification->body,
                    'is_read' => (bool) $notification->is_read,
                    'time_ago' => $notification->created_at?->diffForHumans() ?? 'Recently',
                ])
                ->all();

            return [
                'pending_orders' => $pendingOrders,
                'unread_chats' => $unreadChats,
                'unread_notifications' => $unreadNotifications,
                'notifications' => $notifications,
            ];
        });

        return response()->json($counts)->header('Cache-Control', 'no-store, private');
    }
}
