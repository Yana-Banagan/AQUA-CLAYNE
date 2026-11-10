<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all'); // 'all', 'unread'

        $query = Notification::where('user_id', $request->user()->id)->latest();

        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $statsRow = Notification::where('user_id', $request->user()->id)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN is_read = false OR is_read = 0 THEN 1 ELSE 0 END) AS unread
            ")->first();

        $stats = [
            'total' => (int) ($statsRow?->total ?? 0),
            'unread' => (int) ($statsRow?->unread ?? 0),
        ];

        return view('admin.notifications.index', compact('notifications', 'stats', 'filter'));
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['is_read' => true]);
        Cache::store('file')->forget('admin-header-v2:' . $request->user()->id);

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        Cache::store('file')->forget('admin-header-v2:' . $request->user()->id);

        return back()->with('success', 'All notifications marked as read.');
    }
}
