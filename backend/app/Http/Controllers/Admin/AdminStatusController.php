<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminStatusController extends Controller
{
    /** Return lightweight counters for the shared admin header in one request. */
    public function counts(Request $request)
    {
        $userId = $request->user()->id;
        $counts = [
            'pending_orders' => Order::where('status', 'pending')->count(),
            'unread_chats' => ChatMessage::whereIn('sender_role', ['customer', 'driver'])
                ->whereNull('read_at')
                ->count(),
            'unread_notifications' => Notification::where('user_id', $userId)->where('is_read', false)->count(),
            'notifications' => Notification::where('user_id', $userId)
                ->latest('created_at')->limit(5)->get(['id', 'title', 'body', 'is_read', 'created_at'])
                ->map(fn (Notification $notification) => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'is_read' => $notification->is_read,
                    'time_ago' => $notification->created_at?->diffForHumans() ?? 'Recently',
                ]),
        ];

        return response()->json($counts)->header('Cache-Control', 'no-store, private');
    }
}
