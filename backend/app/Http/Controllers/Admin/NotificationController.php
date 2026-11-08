<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

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

        $stats = [
            'total' => Notification::where('user_id', $request->user()->id)->count(),
            'unread' => Notification::where('user_id', $request->user()->id)->where('is_read', false)->count(),
        ];

        return view('admin.notifications.index', compact('notifications', 'stats', 'filter'));
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['is_read' => true]);
        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
