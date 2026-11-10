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
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->get('range', 'all'); // 'today', 'week', 'month', 'all'
        $method = $request->get('method');

        $cacheKey = "admin:reports:{$range}:" . ($method ?: 'all');

        $reportData = Cache::store('file')->remember($cacheKey, now()->addSeconds(30), function () use ($range, $method) {
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

            $walkInRow = (clone $walkInQuery)->selectRaw("
                COALESCE(SUM(amount), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) AS cash_revenue,
                COALESCE(SUM(CASE WHEN payment_method IN ('credit', 'debt') THEN amount ELSE 0 END), 0) AS debt_revenue,
                COUNT(*) AS completed_count
            ")->first();

            $ordersRow = (clone $ordersQuery)->selectRaw("
                COALESCE(SUM(CASE WHEN status IN ('completed', 'delivered') THEN total_amount ELSE 0 END), 0) AS completed_rev,
                COALESCE(SUM(CASE WHEN status IN ('completed', 'delivered') AND payment_method = 'cash' THEN total_amount ELSE 0 END), 0) AS cash_rev,
                COALESCE(SUM(CASE WHEN status IN ('completed', 'delivered') AND payment_method IN ('credit', 'debt') THEN total_amount ELSE 0 END), 0) AS credit_rev,
                SUM(CASE WHEN status IN ('completed', 'delivered') THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status IN ('assigned', 'picked_up', 'received_at_station', 'waiting_in_queue', 'refilling', 'ready_for_delivery', 'ready_for_pickup', 'out_for_delivery') THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN delivery_method != 'station_pickup' THEN 1 ELSE 0 END) AS home_delivery_count,
                SUM(CASE WHEN delivery_method = 'station_pickup' THEN 1 ELSE 0 END) AS station_pickup_count
            ")->first();

            $totalRevenue = (float) ($ordersRow?->completed_rev ?? 0) + (float) ($walkInRow?->total_revenue ?? 0);
            $cashRevenue = (float) ($ordersRow?->cash_rev ?? 0) + (float) ($walkInRow?->cash_revenue ?? 0);
            $creditRevenue = (float) ($ordersRow?->credit_rev ?? 0) + (float) ($walkInRow?->debt_revenue ?? 0);

            $ordersByStatus = [
                'completed' => (int) ($ordersRow?->completed_count ?? 0) + (int) ($walkInRow?->completed_count ?? 0),
                'in_progress' => (int) ($ordersRow?->in_progress_count ?? 0),
                'pending' => (int) ($ordersRow?->pending_count ?? 0),
                'cancelled' => (int) ($ordersRow?->cancelled_count ?? 0),
            ];

            $deliveryMethodStats = [
                'home_delivery' => (int) ($ordersRow?->home_delivery_count ?? 0),
                'station_pickup' => (int) ($ordersRow?->station_pickup_count ?? 0),
            ];

            $topServices = Product::withCount(['orderItems as total_orders' => function ($q) {
                $q->selectRaw('count(distinct(order_id))');
            }])->orderBy('total_orders', 'desc')->take(5)->get();

            $creditRow = CreditAccount::selectRaw("
                COALESCE(SUM(balance), 0) AS total_balance,
                SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) AS active_count
            ")->first();

            $driverPerformances = Driver::with(['user:id,name,phone', 'deliveries' => function ($q) {
                $q->whereIn('status', ['delivered', 'completed']);
            }])->get();

            return [
                'totalRevenue' => $totalRevenue,
                'cashRevenue' => $cashRevenue,
                'creditRevenue' => $creditRevenue,
                'ordersByStatus' => $ordersByStatus,
                'deliveryMethodStats' => $deliveryMethodStats,
                'topServices' => $topServices,
                'totalOutstandingCredit' => (float) ($creditRow?->total_balance ?? 0),
                'activeCreditAccounts' => (int) ($creditRow?->active_count ?? 0),
                'driverPerformances' => $driverPerformances,
            ];
        });

        return view('admin.reports.index', array_merge($reportData, compact('range', 'method')));
    }
    }
}
