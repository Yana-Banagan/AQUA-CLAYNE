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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the AQUA CLAYNE Admin Station Dashboard.
     */
    public function index()
    {
        $today = Carbon::today();
        $sevenDaysAgo = Carbon::today()->subDays(6);

        // Fetch the status totals once.  The former implementation issued a
        // separate remote PostgreSQL query for nearly every number on this page.
        // On a pooled database connection, that made a dashboard transition take
        // many seconds even when there were only a few orders.
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

        // ---------------------------------------------------------------------
        // 1. KPI CARDS
        // ---------------------------------------------------------------------
        $walkInSummary = WalkInOrder::query()
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(amount) FILTER (WHERE created_at >= ? AND created_at < ?), 0) AS today_amount', [
                $today->copy()->startOfDay(),
                $today->copy()->addDay()->startOfDay(),
            ])
            ->first();
        $walkInOrdersCount = (int) $walkInSummary->total;
        $totalOrders = (int) $orderSummary->total + $walkInOrdersCount;
        $pendingOrders = (int) $orderSummary->pending;

        // Refill Queue KPIs
        $waitingInQueueCount = (int) $orderSummary->waiting_in_queue;
        $refillingCount = (int) $orderSummary->refilling;
        $queueOrdersCount = $waitingInQueueCount + $refillingCount;

        // Gallons in queue (from container intake or expected empty gallons)
        $queueGallons = (int) Order::query()
            ->leftJoin('order_container_intakes', 'order_container_intakes.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['waiting_in_queue', 'refilling'])
            ->sum(DB::raw('COALESCE(order_container_intakes.accepted_count, orders.expected_empty_gallons, 0)'));

        // Revenue (Collected / Completed today)
        $paymentSummary = Payment::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->selectRaw("COALESCE(SUM(amount), 0) AS total,
                COALESCE(SUM(amount) FILTER (WHERE method = 'cash'), 0) AS cash,
                COALESCE(SUM(amount) FILTER (WHERE method = 'credit'), 0) AS credit,
                COALESCE(SUM(amount) FILTER (WHERE method = 'online'), 0) AS online")
            ->first();
        $todayRevenue = (float) $paymentSummary->total;

        // If payments table has no records for today, fall back to orders completed today
        if ($todayRevenue <= 0) {
            $todayRevenue = (float) Order::query()
                ->whereIn('status', ['completed', 'delivered'])
                ->whereDate('updated_at', $today)
                ->sum('total_amount');
        }
        $todayRevenue += (float) $walkInSummary->today_amount;

        // Active & Delivering Drivers
        $activeDrivers = Driver::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->count();
        $deliveringDrivers = Delivery::whereIn('status', ['en_route_for_pickup', 'picked_up', 'out_for_delivery'])
            ->distinct('driver_id')
            ->count('driver_id');

        // Low Stock Count
        $lowStockCount = Inventory::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count();

        // ---------------------------------------------------------------------
        // 2. OPERATIONAL ALERTS
        // ---------------------------------------------------------------------
        // Orders needing attention: pending confirmation, intake discrepancy, failed deliveries
        $attentionOrders = (int) $orderSummary->attention;

        // Intake Discrepancies
        $intakeDiscrepancies = OrderContainerIntake::with('order.customer.user')
            ->whereColumn('expected_count', '!=', 'received_count')
            ->orWhere('rejected_count', '>', 0)
            ->latest('received_at')
            ->take(3)
            ->get();

        // ---------------------------------------------------------------------
        // 3. CHART 1: ORDER OVERVIEW (LAST 7 DAYS)
        // ---------------------------------------------------------------------
        $chartDates = [];
        $chartLabels = [];
        $completedDaily = [];
        $pendingDaily = [];
        $inProgressDaily = [];

        $dailyOrders = Order::query()
            ->whereBetween('created_at', [$sevenDaysAgo->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw("DATE(created_at) AS day,
                COUNT(*) FILTER (WHERE status IN ('completed', 'delivered')) AS completed,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status IN ('confirmed', 'assigned', 'picked_up', 'received_at_station', 'waiting_in_queue', 'refilling', 'ready_for_delivery', 'ready_for_pickup', 'out_for_delivery')) AS in_progress")
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);
        $dailyWalkIns = WalkInOrder::query()
            ->whereBetween('created_at', [$sevenDaysAgo->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

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

        // ---------------------------------------------------------------------
        // 4. CHART 2: ORDERS BY CANONICAL LIFECYCLE STATUS
        // ---------------------------------------------------------------------
        // Keys are canonical database status codes. Keeping the codes here
        // ensures the dashboard list and doughnut use the same color for the
        // same real order status; labels are formatted only in the view.
        $statusCounts = [
            'pending' => (int) $orderSummary->pending,
            'confirmed' => (int) $orderSummary->confirmed,
            'assigned' => (int) $orderSummary->assigned,
            'picked_up' => (int) $orderSummary->picked_up,
            'received_at_station' => (int) $orderSummary->received_at_station,
            'waiting_in_queue' => $waitingInQueueCount,
            'refilling' => $refillingCount,
            'ready_for_delivery' => (int) $orderSummary->ready_for_delivery,
            'out_for_delivery' => (int) $orderSummary->out_for_delivery,
            'delivered' => (int) $orderSummary->delivered,
            'completed' => (int) $orderSummary->completed + $walkInOrdersCount,
            'exceptions' => (int) $orderSummary->exceptions,
        ];

        // ---------------------------------------------------------------------
        // 5. LATEST ORDERS TABLE (paginated, 5 per page)
        // ---------------------------------------------------------------------
        $latestOrders = Order::with([
            'customer.user',
            'items.product',
            'containerIntake',
            'delivery.driver.user'
        ])
        ->latest('created_at')
        ->paginate(5, ['*'], 'orders_page')
        ->withQueryString();

        // ---------------------------------------------------------------------
        // 6. FIFO REFILL QUEUE (PHYSICAL STATION QUEUE) (paginated, 5 per page)
        // ---------------------------------------------------------------------
        $queueEntries = RefillQueueEntry::with([
            'order.customer.user',
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
        // 7. PAYMENTS SUMMARY (ONLINE, CASH & CREDIT)
        // ---------------------------------------------------------------------
        $todayCash = (float) $paymentSummary->cash;
        $todayCash += (float) $walkInSummary->today_amount;

        $todayCredit = (float) $paymentSummary->credit;
        $todayOnline = (float) $paymentSummary->online;

        $pendingCash = (float) Order::where('payment_method', 'cash')
            ->whereIn('status', ['ready_for_delivery', 'ready_for_pickup', 'out_for_delivery', 'delivered'])
            ->whereDoesntHave('payment', function ($q) {
                $q->where('status', 'paid');
            })
            ->sum('total_amount');

        $outstandingCredit = (float) CreditAccount::sum('balance');

        $driverBoard = app(\App\Services\DispatchService::class)->getDriverStatuses();

        // ---------------------------------------------------------------------
        // 8. RECENT ACTIVITY FEED
        // ---------------------------------------------------------------------
        $recentHistory = OrderStatusHistory::with(['order.customer.user', 'changedBy'])
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
        // 9. EQUIPMENT MAINTENANCE PREVIEW
        // ---------------------------------------------------------------------
        $maintenanceItems = Maintenance::with(['records' => function ($q) {
            $q->latest('performed_at')->limit(1);
        }])->take(4)->get();

        // ---------------------------------------------------------------------
        // 10. LOW STOCK ITEMS PREVIEW
        // ---------------------------------------------------------------------
        $lowStockItems = Inventory::with('product')
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->take(4)
            ->get();

        // ---------------------------------------------------------------------
        // 11. RECENT NOTIFICATIONS
        // ---------------------------------------------------------------------
        // Sidebar counts badge packet
        $sidebarCounts = [
            'pendingOrders' => $pendingOrders,
            'waitingInQueue' => $waitingInQueueCount,
            'lowStock' => $lowStockCount,
        ];

        return view('admin.dashboard', [
            // KPI Data
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'queueCount' => $queueOrdersCount,
            'queueGallons' => $queueGallons,
            'todayRevenue' => $todayRevenue,
            'activeDrivers' => $activeDrivers,
            'deliveringDrivers' => $deliveringDrivers,
            'lowStockCount' => $lowStockCount,
            'attentionOrders' => $attentionOrders,
            'intakeDiscrepancies' => $intakeDiscrepancies,

            // Charts
            'chartLabels' => $chartLabels,
            'completedDaily' => $completedDaily,
            'pendingDaily' => $pendingDaily,
            'inProgressDaily' => $inProgressDaily,
            'statusCounts' => $statusCounts,

            // Tables & Operational Lists
            'latestOrders' => $latestOrders,
            'queueEntries' => $queueEntries,

            // Payments & Financials
            'todayCash' => $todayCash,
            'todayCredit' => $todayCredit,
            'todayOnline' => $todayOnline,
            'pendingCash' => $pendingCash,
            'outstandingCredit' => $outstandingCredit,

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
