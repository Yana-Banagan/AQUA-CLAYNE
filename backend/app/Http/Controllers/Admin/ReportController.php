<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAccount;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\WalkInOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->get('range', 'all'); // 'today', 'week', 'month', 'all'
        $method = $request->get('method');

        $ordersQuery = Order::query();

        if ($range === 'today') {
            $ordersQuery->whereDate('created_at', Carbon::today());
        } elseif ($range === 'week') {
            $ordersQuery->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($range === 'month') {
            $ordersQuery->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        if ($method) {
            $ordersQuery->where('payment_method', $method);
        }

        $walkInQuery = WalkInOrder::query();
        if ($range === 'today') {
            $walkInQuery->whereDate('created_at', Carbon::today());
        } elseif ($range === 'week') {
            $walkInQuery->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($range === 'month') {
            $walkInQuery->where('created_at', '>=', Carbon::now()->subDays(30));
        }
        if ($method === 'credit' || $method === 'debt') {
            $walkInQuery->whereIn('payment_method', ['credit', 'debt']);
        } elseif ($method === 'online') {
            $walkInQuery->whereIn('payment_method', ['online', 'gcash', 'paymongo']);
        } elseif ($method) {
            $walkInQuery->where('payment_method', $method);
        }

        $walkInRevenue = (float) (clone $walkInQuery)->sum('amount');
        $walkInCashRevenue = (float) (clone $walkInQuery)
            ->where('payment_method', 'cash')
            ->sum('amount');
        $walkInDebtRevenue = (float) (clone $walkInQuery)
            ->whereIn('payment_method', ['credit', 'debt'])
            ->sum('amount');
        $walkInCompletedCount = (clone $walkInQuery)->count();

        // 1. Revenue
        $totalRevenue = (clone $ordersQuery)->whereIn('status', ['completed', 'delivered'])->sum('total_amount') + $walkInRevenue;
        $cashRevenue = (clone $ordersQuery)->whereIn('status', ['completed', 'delivered'])->where('payment_method', 'cash')->sum('total_amount') + $walkInCashRevenue;
        $creditRevenue = (clone $ordersQuery)->whereIn('status', ['completed', 'delivered'])->whereIn('payment_method', ['credit', 'debt'])->sum('total_amount') + $walkInDebtRevenue;

        // 2. Orders by status
        $ordersByStatus = [
            'completed' => (clone $ordersQuery)->whereIn('status', ['completed', 'delivered'])->count() + $walkInCompletedCount,
            'in_progress' => (clone $ordersQuery)->whereIn('status', ['assigned', 'picked_up', 'received_at_station', 'waiting_in_queue', 'refilling', 'ready_for_delivery', 'ready_for_pickup', 'out_for_delivery'])->count(),
            'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'cancelled' => (clone $ordersQuery)->whereIn('status', ['cancelled', 'rejected'])->count(),
        ];

        // 2b. Delivery Method Breakdown
        $deliveryMethodStats = [
            'home_delivery' => (clone $ordersQuery)->where('delivery_method', '!=', 'station_pickup')->count(),
            'station_pickup' => (clone $ordersQuery)->where('delivery_method', 'station_pickup')->count(),
        ];

        // 3. Top Services
        $topServices = Product::withCount(['orderItems as total_orders' => function ($q) {
            $q->selectRaw('count(distinct(order_id))');
        }])->orderBy('total_orders', 'desc')->take(5)->get();

        // 4. Outstanding Credit
        $totalOutstandingCredit = CreditAccount::sum('balance');
        $activeCreditAccounts = CreditAccount::where('balance', '>', 0)->count();

        // 5. Active Drivers performance
        $driverPerformances = Driver::with(['user', 'deliveries' => function ($q) {
            $q->whereIn('status', ['delivered', 'completed']);
        }])->get();

        return view('admin.reports.index', compact(
            'totalRevenue',
            'cashRevenue',
            'creditRevenue',
            'ordersByStatus',
            'deliveryMethodStats',
            'topServices',
            'totalOutstandingCredit',
            'activeCreditAccounts',
            'driverPerformances',
            'range',
            'method'
        ));
    }
}
