<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\RealtimeEventService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $this->success(
            Notification::query()
                ->where('user_id', $request->user()->id)
                ->latest('created_at')
                ->get()
        );
    }

    public function markRead(Request $request, Notification $notification, RealtimeEventService $realtime)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if (!$notification->is_read) {
            $notification->update(['is_read' => true]);
            $realtime->emit('notification', $notification->id, 'read');
        }

        return $this->success($notification->fresh(), 'Notification marked as read.');
    }

    public function markAllRead(Request $request, RealtimeEventService $realtime)
    {
        $updated = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($updated > 0) {
            $realtime->emit('notification', $request->user()->id, 'all_read');
        }

        return $this->success(['updated' => $updated], 'Notifications marked as read.');
    }
}
