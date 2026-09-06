<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $orders = Order::query();
        $stats = [
            'sales' => (clone $orders)->whereIn('status', ['completed', 'delivered'])->sum('total_amount'),
            'orders' => (clone $orders)->count(),
            'pendingOrders' => (clone $orders)->where('status', 'pending')->count(),
            'activeDeliveries' => Delivery::whereIn('status', ['assigned', 'out_for_delivery'])->count(),
            'completedDeliveries' => Delivery::whereIn('status', ['completed', 'delivered'])->whereDate('created_at', Carbon::today())->count(),
            'customers' => Customer::count(), 'drivers' => Driver::count(),
        ];
        $recentOrders = Order::with(['customer.user', 'items.product', 'delivery'])->latest()->take(6)->get();
        $activity = $recentOrders->map(fn (Order $order) => [
            'title' => 'Order #' . strtoupper(substr($order->id, 0, 8)) . ' received',
            'detail' => ($order->customer?->user?->name ?? 'Customer') . ' · ' . ucfirst(str_replace('_', ' ', $order->status)),
            'time' => $order->created_at?->diffForHumans() ?? 'Recently',
        ]);
        return view('admin.dashboard', compact('stats', 'recentOrders', 'activity'));
    }
}
