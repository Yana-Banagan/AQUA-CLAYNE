<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\AdminSharedDataService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, AdminSharedDataService $sharedData)
    {
        $filter = $request->get('filter', 'all'); // 'all', 'unread'
        $userId = $request->user()->id;

        $query = Notification::where('user_id', $userId)->latest();

        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $statsRow = Notification::where('user_id', $userId)
            ->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN is_read = false THEN 1 ELSE 0 END), 0) AS unread
            ")->first();

        $stats = [
            'total' => (int) ($statsRow?->total ?? 0),
            'unread' => (int) ($statsRow?->unread ?? 0),
        ];

        // Provide unread count so the admin layout requires zero extra notification queries
        $sharedData->provide([
            'unreadNotifications' => $stats['unread'],
        ]);

        return view('admin.notifications.index', compact('notifications', 'stats', 'filter'));
    }

    /**
     * Retrieve recent notifications on demand when the operator opens the topbar bell panel.
     * Prevents loading notification records on every regular admin page load.
     */
    public function recent(Request $request, AdminSharedDataService $sharedData)
    {
        $notifications = $sharedData->getRecentNotifications($request->user());
        $counts = $sharedData->getSidebarCounts($request->user());

        return response()->json([
            'notifications' => $notifications,
            'unread_notifications' => $counts['unreadNotifications'],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['is_read' => true]);
        AdminSharedDataService::invalidate((string) $request->user()->id);

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        AdminSharedDataService::invalidate((string) $request->user()->id);

        return back()->with('success', 'All notifications marked as read.');
    }
}
