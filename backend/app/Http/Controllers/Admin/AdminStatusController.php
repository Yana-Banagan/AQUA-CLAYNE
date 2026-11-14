<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSharedDataService;
use Illuminate\Http\Request;

class AdminStatusController extends Controller
{
    /** Return lightweight counters for the shared admin header in one request. */
    public function counts(Request $request, AdminSharedDataService $sharedData)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'pending_orders' => 0,
                'unread_chats' => 0,
                'unread_notifications' => 0,
                'active_deliveries' => 0,
                'low_stock' => 0,
                'notifications' => [],
            ]);
        }

        if ($request->boolean('fresh')) {
            AdminSharedDataService::invalidate((string) $user->id);
        }

        $includeNotifications = $request->boolean('include_notifications');
        $counts = $sharedData->getStatusCounts($user, $includeNotifications);

        return response()->json($counts)->header('Cache-Control', 'no-store, private');
    }
}
