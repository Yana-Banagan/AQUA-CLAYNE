<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditAccount;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Maintenance;
use App\Models\Order;
use App\Models\OrderContainerIntake;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\RefillQueueEntry;
use App\Models\WalkInOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Display the AQUA CLAYNE Admin Station Dashboard.
     */
    public function index(?Request $request = null)
    {
        $request = $request ?? request();
        $today = Carbon::today();
        $sevenDaysAgo = Carbon::today()->subDays(6);
        $forceFresh = $request->boolean('fresh');

        $cacheKey = 'admin:dashboard:station-kpis';
        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        // Consolidated Station KPI bundle (KPIs, Financials, Charts, Drivers summary)
        // Cached for 15s to eliminate 20+ repeated remote database round trips.
        $kpi = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($today, $sevenDaysAgo) {
            $isPgsql = DB::getDriverName() === 'pgsql';

            if ($isPgsql) {
                $orderSummary = Order::query()->selectRaw("
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                    COUNT(*) FILTER (WHERE status = 'waiting_in_queue') AS waiting_in_queue,
                    COUNT(*) FILTER (WHERE status = 'refilling') AS refilling,
                    COUNT(*) FILTER (WHERE status = 'confirmed') AS confirmed,
                    COUNT(*) FILTER (WHERE status = 'assigned') AS assigned,
                    COUNT(*) FILTER (WHERE status = 'picked_up') AS picked_up,
                    COUNT(*) FILTER (WHERE status = 'received_at_station') AS received_at_station,
                    COUNT(*) FILTER (WHERE status = 'ready_for_delivery') AS ready_for_delivery,
                    COUNT(*) FILTER (WHERE status = 'ready_for_pickup') AS ready_for_pickup,
                    COUNT(*) FILTER (WHERE status = 'out_for_delivery') AS out_for_delivery,
                    COUNT(*) FILTER (WHERE status = 'delivered') AS delivered,
                    COUNT(*) FILTER (WHERE status = 'completed') AS completed,
                    COUNT(*) FILTER (WHERE status IN ('cancelled', 'rejected', 'pickup_failed', 'delivery_failed')) AS exceptions,
                    COUNT(*) FILTER (WHERE status IN ('pending', 'pickup_failed', 'delivery_failed')) AS attention
                ")->first();

                $walkInSummary = WalkInOrder::query()
                    ->selectRaw('COUNT(*) AS total, COALESCE(SUM(amount) FILTER (WHERE created_at >= ? AND created_at < ?), 0) AS today_amount', [
                        $today->copy()->startOfDay(),
                        $today->copy()->addDay()->startOfDay(),
                    ])
                    ->first();

                $paymentSummary = Payment::query()
                    ->where('status', 'paid')
                    ->whereDate('paid_at', $today)
                    ->selectRaw("COALESCE(SUM(amount), 0) AS total,
                        COALESCE(SUM(amount) FILTER (WHERE method = 'cash'), 0) AS cash,
                        COALESCE(SUM(amount) FILTER (WHERE method = 'credit'), 0) AS credit,
                        COALESCE(SUM(amount) FILTER (WHERE method = 'online'), 0) AS online")
                    ->first();

                $dailyOrders = Order::query()
                    ->whereBetween('created_at', [$sevenDaysAgo->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->selectRaw("DATE(created_at) AS day,
                        COUNT(*) FILTER (WHERE status IN ('completed', 'delivered')) AS completed,
                        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                        COUNT(*) FILTER (WHERE status IN ('confirmed', 'assigned', 'picked_up', 'received_at_station', 'waiting_in_queue', 'refilling', 'ready_for_delivery', 'ready_for_pickup', 'out_for_delivery')) AS in_progress")
                    ->groupBy('day')
                    ->get()
                    ->keyBy(fn ($row) => (string) $row->day);
            } else {
                $orderSummary = Order::query()->selectRaw("
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'waiting_in_queue' THEN 1 ELSE 0 END) AS waiting_in_queue,
                    SUM(CASE WHEN status = 'refilling' THEN 1 ELSE 0 END) AS refilling,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned,
                    SUM(CASE WHEN status = 'picked_up' THEN 1 ELSE 0 END) AS picked_up,
                    SUM(CASE WHEN status = 'received_at_station' THEN 1 ELSE 0 END) AS received_at_station,
                    SUM(CASE WHEN status = 'ready_for_delivery' THEN 1 ELSE 0 END) AS ready_for_delivery,
                    SUM(CASE WHEN status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_for_pickup,
                    SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS out_for_delivery,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status IN ('cancelled', 'rejected', 'pickup_failed', 'delivery_failed') THEN 1 ELSE 0 END) AS exceptions,
                    SUM(CASE WHEN status IN ('pending', 'pickup_failed', 'delivery_failed') THEN 1 ELSE 0 END) AS attention
                ")->first();

                $walkInSummary = WalkInOrder::query()
                    ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN amount ELSE 0 END), 0) AS today_amount', [
                        $today->copy()->startOfDay(),
                        $today->copy()->addDay()->startOfDay(),
                    ])
                    ->first();

                $paymentSummary = Payment::query()
                    ->where('status', 'paid')
                    ->whereDate('paid_at', $today)
                    ->selectRaw("COALESCE(SUM(amount), 0) AS total,
                        COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS cash,
                        COALESCE(SUM(CASE WHEN method = 'credit' THEN amount ELSE 0 END), 0) AS credit,
                        COALESCE(SUM(CASE WHEN method = 'online' THEN amount ELSE 0 END), 0) AS online")
                    ->first();

                $dailyOrders = Order::query()
                    ->whereBetween('created_at', [$sevenDaysAgo->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->selectRaw("DATE(created_at) AS day,
                        SUM(CASE WHEN status IN ('completed', 'delivered') THEN 1 ELSE 0 END) AS completed,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                        SUM(CASE WHEN status IN ('confirmed', 'assigned', 'picked_up', 'received_at_station', 'waiting_in_queue', 'refilling', 'ready_for_delivery', 'ready_for_pickup', 'out_for_delivery') THEN 1 ELSE 0 END) AS in_progress")
                    ->groupBy('day')
                    ->get()
                    ->keyBy(fn ($row) => (string) $row->day);
            }

            $walkInOrdersCount = (int) ($walkInSummary->total ?? 0);
            $totalOrders = (int) ($orderSummary->total ?? 0) + $walkInOrdersCount;
            $pendingOrders = (int) ($orderSummary->pending ?? 0);

            $waitingInQueueCount = (int) ($orderSummary->waiting_in_queue ?? 0);
            $refillingCount = (int) ($orderSummary->refilling ?? 0);
            $queueOrdersCount = $waitingInQueueCount + $refillingCount;

            $queueGallons = (int) Order::query()
                ->leftJoin('order_container_intakes', 'order_container_intakes.order_id', '=', 'orders.id')
                ->whereIn('orders.status', ['waiting_in_queue', 'refilling'])
                ->sum(DB::raw('COALESCE(order_container_intakes.accepted_count, orders.expected_empty_gallons, 0)'));

            $todayRevenue = (float) ($paymentSummary->total ?? 0);
            if ($todayRevenue <= 0) {
                $todayRevenue = (float) Order::query()
                    ->whereIn('status', ['completed', 'delivered'])
                    ->whereDate('updated_at', $today)
                    ->sum('total_amount');
            }
            $todayRevenue += (float) ($walkInSummary->today_amount ?? 0);

            $activeDrivers = Driver::whereHas('user', function ($q) {
                $q->where('status', 'active');
            })->count();

            $deliveringDrivers = Delivery::whereIn('status', ['en_route_for_pickup', 'picked_up', 'out_for_delivery'])
                ->distinct('driver_id')
                ->count('driver_id');

            $lowStockCount = Inventory::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count();

            $attentionOrders = (int) ($orderSummary->attention ?? 0);

            // Chart 1: Order Overview (Last 7 Days)
            $dailyWalkIns = WalkInOrder::query()
                ->whereBetween('created_at', [$sevenDaysAgo->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $chartDates = [];
            $chartLabels = [];
            $completedDaily = [];
            $pendingDaily = [];
            $inProgressDaily = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dateStr = $date->format('Y-m-d');
                $chartDates[] = $dateStr;
                $chartLabels[] = $date->format('D (M j)');

                $row = $dailyOrders->get($dateStr);
                $completedDaily[] = (int) ($row->completed ?? 0) + (int) ($dailyWalkIns[$dateStr] ?? 0);
                $pendingDaily[] = (int) ($row->pending ?? 0);
                $inProgressDaily[] = (int) ($row->in_progress ?? 0);
            }

            // Chart 2: Canonical Lifecycle Status Counts
            $statusCounts = [
                'pending' => $pendingOrders,
                'confirmed' => (int) ($orderSummary->confirmed ?? 0),
                'assigned' => (int) ($orderSummary->assigned ?? 0),
                'picked_up' => (int) ($orderSummary->picked_up ?? 0),
                'received_at_station' => (int) ($orderSummary->received_at_station ?? 0),
                'waiting_in_queue' => $waitingInQueueCount,
                'refilling' => $refillingCount,
                'ready_for_delivery' => (int) ($orderSummary->ready_for_delivery ?? 0),
                'out_for_delivery' => (int) ($orderSummary->out_for_delivery ?? 0),
                'delivered' => (int) ($orderSummary->delivered ?? 0),
                'completed' => (int) ($orderSummary->completed ?? 0) + $walkInOrdersCount,
                'exceptions' => (int) ($orderSummary->exceptions ?? 0),
            ];

            // Payments Summary
            $todayCash = (float) ($paymentSummary->cash ?? 0) + (float) ($walkInSummary->today_amount ?? 0);
            $todayCredit = (float) ($paymentSummary->credit ?? 0);
            $todayOnline = (float) ($paymentSummary->online ?? 0);

            $pendingCash = (float) Order::where('payment_method', 'cash')
                ->whereIn('status', ['ready_for_delivery', 'ready_for_pickup', 'out_for_delivery', 'delivered'])
                ->whereDoesntHave('payment', function ($q) {
                    $q->where('status', 'paid');
                })
                ->sum('total_amount');

            $outstandingCredit = 0.0;
            try {
                if (Schema::hasTable('credit_accounts')) {
                    $outstandingCredit = (float) CreditAccount::sum('balance');
                }
            } catch (\Throwable) {
                $outstandingCredit = 0.0;
            }

            return [
                'totalOrders' => $totalOrders,
                'pendingOrders' => $pendingOrders,
                'waitingInQueueCount' => $waitingInQueueCount,
                'refillingCount' => $refillingCount,
                'queueOrdersCount' => $queueOrdersCount,
                'queueGallons' => $queueGallons,
                'todayRevenue' => $todayRevenue,
                'activeDrivers' => $activeDrivers,
                'deliveringDrivers' => $deliveringDrivers,
                'lowStockCount' => $lowStockCount,
                'attentionOrders' => $attentionOrders,
                'chartLabels' => $chartLabels,
                'completedDaily' => $completedDaily,
                'pendingDaily' => $pendingDaily,
                'inProgressDaily' => $inProgressDaily,
                'statusCounts' => $statusCounts,
                'todayCash' => $todayCash,
                'todayCredit' => $todayCredit,
                'todayOnline' => $todayOnline,
                'pendingCash' => $pendingCash,
                'outstandingCredit' => $outstandingCredit,
            ];
        });

        // ---------------------------------------------------------------------
        // 2. LIVE OPERATIONAL ALERTS & INTAKE DISCREPANCIES
        // ---------------------------------------------------------------------
        $intakeDiscrepancies = OrderContainerIntake::with([
            'order:id,order_number,customer_id',
            'order.customer:id,user_id',
            'order.customer.user:id,name,phone'
        ])
            ->whereColumn('expected_count', '!=', 'received_count')
            ->orWhere('rejected_count', '>', 0)
            ->latest('received_at')
            ->take(3)
            ->get();

        // ---------------------------------------------------------------------
        // 3. LATEST ORDERS TABLE (paginated, 5 per page with specific column selection)
        // ---------------------------------------------------------------------
        $latestOrders = Order::with([
            'customer.user:id,name,phone,email,avatar_path',
            'items:id,order_id,product_id,quantity,unit_price',
            'items.product:id,name,type',
            'containerIntake',
            'delivery.driver.user:id,name,phone'
        ])
            ->latest('created_at')
            ->paginate(5, ['*'], 'orders_page')
            ->withQueryString();

        // ---------------------------------------------------------------------
        // 4. FIFO REFILL QUEUE (PHYSICAL STATION QUEUE) (paginated, 5 per page)
        // ---------------------------------------------------------------------
        $queueEntries = RefillQueueEntry::with([
            'order.customer.user:id,name,phone',
            'order.containerIntake'
        ])
            ->whereIn('status', ['waiting', 'in_progress'])
            ->orderBy('priority', 'desc')
            ->orderBy('queued_at', 'asc')
            ->paginate(5, ['*'], 'queue_page')
            ->withQueryString();

        // Calculate queue waiting duration for each entry
        $queueEntries->getCollection()->transform(function ($entry, $index) use ($queueEntries) {
            $pos = ($queueEntries->firstItem() ?? 1) + $index;
            $entry->queue_position = '#' . str_pad($pos, 3, '0', STR_PAD_LEFT);
            if ($entry->queued_at) {
                $diffMinutes = $entry->queued_at->diffInMinutes(now());
                $hours = floor($diffMinutes / 60);
                $mins = $diffMinutes % 60;
                $entry->waiting_duration = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
            } else {
                $entry->waiting_duration = 'Just now';
            }
            return $entry;
        });

        // ---------------------------------------------------------------------
        // 5. DRIVER FLEET BOARD
        // ---------------------------------------------------------------------
        $driverBoard = app(\App\Services\DispatchService::class)->getDriverStatuses();

        // ---------------------------------------------------------------------
        // 6. RECENT ACTIVITY FEED
        // ---------------------------------------------------------------------
        $recentHistory = OrderStatusHistory::with([
            'order:id,order_number,customer_id',
            'order.customer:id,user_id',
            'order.customer.user:id,name',
            'changedBy:id,name'
        ])
            ->latest('created_at')
            ->take(6)
            ->get();

        $recentActivities = $recentHistory->map(function ($h) {
            $orderNumber = $h->order?->order_number ?? ('ORD-' . strtoupper(substr($h->order_id, 0, 8)));
            $customerName = $h->order?->customer?->user?->name ?? 'Customer';
            $toStatus = ucwords(str_replace('_', ' ', $h->to_status));
            return [
                'order_number' => $orderNumber,
                'customer_name' => $customerName,
                'status' => $h->to_status,
                'status_label' => $toStatus,
                'changed_by' => $h->changedBy?->name ?? 'System',
                'time_ago' => $h->created_at ? ($h->created_at instanceof \Carbon\Carbon ? $h->created_at->diffForHumans() : \Carbon\Carbon::parse($h->created_at)->diffForHumans()) : 'Recently',
            ];
        });

        // ---------------------------------------------------------------------
        // 7. EQUIPMENT MAINTENANCE & LOW STOCK PREVIEWS (Cached 30-60s)
        // ---------------------------------------------------------------------
        $maintenanceItems = Cache::remember('admin:dashboard:maintenance-preview', now()->addSeconds(60), function () {
            return Maintenance::with(['records' => function ($q) {
                $q->latest('performed_at')->limit(1);
            }])->take(4)->get()->map(function ($item) {
                if (is_string($item)) {
                    return (object) ['equipment_name' => $item, 'schedule_interval_days' => null];
                }

                if (is_array($item)) {
                    return (object) $item;
                }

                return $item;
            })->values();
        });

        $maintenanceItems = collect($maintenanceItems)
            ->map(function ($item) {
                if (is_string($item)) {
                    return (object) ['equipment_name' => $item, 'schedule_interval_days' => null];
                }

                if (is_array($item)) {
                    return (object) $item;
                }

                return $item;
            })
            ->filter(fn ($item) => is_object($item) || is_array($item))
            ->values();

        $lowStockItems = Cache::remember('admin:dashboard:lowstock-preview', now()->addSeconds(30), function () {
            return Inventory::with('product:id,name,type,price')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->take(4)
                ->get();
        });

        // Sidebar counts badge packet
        $sidebarCounts = [
            'pendingOrders' => $kpi['pendingOrders'],
            'waitingInQueue' => $kpi['waitingInQueueCount'],
            'lowStock' => $kpi['lowStockCount'],
        ];

        return view('admin.dashboard', [
            // KPI Data
            'totalOrders' => $kpi['totalOrders'],
            'pendingOrders' => $kpi['pendingOrders'],
            'queueCount' => $kpi['queueOrdersCount'],
            'queueGallons' => $kpi['queueGallons'],
            'todayRevenue' => $kpi['todayRevenue'],
            'activeDrivers' => $kpi['activeDrivers'],
            'deliveringDrivers' => $kpi['deliveringDrivers'],
            'lowStockCount' => $kpi['lowStockCount'],
            'attentionOrders' => $kpi['attentionOrders'],
            'intakeDiscrepancies' => $intakeDiscrepancies,

            // Charts
            'chartLabels' => $kpi['chartLabels'],
            'completedDaily' => $kpi['completedDaily'],
            'pendingDaily' => $kpi['pendingDaily'],
            'inProgressDaily' => $kpi['inProgressDaily'],
            'statusCounts' => $kpi['statusCounts'],

            // Tables & Operational Lists
            'latestOrders' => $latestOrders,
            'queueEntries' => $queueEntries,

            // Payments & Financials
            'todayCash' => $kpi['todayCash'],
            'todayCredit' => $kpi['todayCredit'],
            'todayOnline' => $kpi['todayOnline'],
            'pendingCash' => $kpi['pendingCash'],
            'outstandingCredit' => $kpi['outstandingCredit'],

            // Driver Fleet
            'driverBoard' => $driverBoard,

            // Feeds & Lists
            'recentActivities' => $recentActivities,
            'maintenanceItems' => $maintenanceItems,
            'lowStockItems' => $lowStockItems,
            'sidebarCounts' => $sidebarCounts,
        ]);
    }
}
