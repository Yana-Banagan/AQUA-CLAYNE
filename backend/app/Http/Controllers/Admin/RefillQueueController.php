<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderContainerIntake;
use App\Models\RefillQueueEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefillQueueController extends Controller
{
    public function status()
    {
        $stats = RefillQueueEntry::query()->selectRaw("
            COUNT(*) FILTER (WHERE status = 'waiting') AS waiting,
            COUNT(*) FILTER (WHERE status = 'in_progress') AS refilling
        ")->first();

        return response()->json([
            'waiting' => (int) $stats->waiting,
            'refilling' => (int) $stats->refilling,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request)
    {
        $statusFilter = 'in_progress';
        $priorityFilter = $request->get('priority', 'all'); // 'all', 'high', 'normal', 'low'
        $search = trim($request->get('search', ''));
        $dateFilter = $request->get('date');

        // Base Queue Query
        $queueQuery = RefillQueueEntry::with([
            'order.customer.user',
            'order.containerIntake',
        ]);

        // Show only verified orders whose refill process has started.
        $queueQuery->where('status', 'in_progress');

        // Priority Filter
        if ($priorityFilter === 'high') {
            $queueQuery->where('priority', '>', 0);
        } elseif ($priorityFilter === 'normal') {
            $queueQuery->where('priority', 0);
        } elseif ($priorityFilter === 'low') {
            $queueQuery->where('priority', '<', 0);
        }

        // Date Filter
        if (!empty($dateFilter)) {
            $queueQuery->whereDate('queued_at', $dateFilter);
        }

        // Search Filter (Order Number, Customer Name, Phone)
        if (!empty($search)) {
            $queueQuery->where(function ($q) use ($search) {
                $q->whereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'ilike', "%{$search}%")
                        ->orWhere('id', 'ilike', "%{$search}%")
                        ->orWhereHas('customer.user', function ($uq) use ($search) {
                            $uq->where('name', 'ilike', "%{$search}%")
                                ->orWhere('phone', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%");
                        });
                });
            });
        }

        // FIFO ordering: IN_PROGRESS first, then WAITING by PRIORITY DESC, then QUEUE_NUMBER ASC
        $queueEntries = (clone $queueQuery)
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'waiting' THEN 2 ELSE 3 END")
            ->orderBy('priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->paginate(8)
            ->withQueryString();

        // Determine the single next eligible waiting queue entry ID. Only select
        // the ID: the record itself is not rendered here.
        $nextEligibleId = RefillQueueEntry::where('status', 'waiting')
            ->orderBy('priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->value('id');

        // 1. Orders awaiting physical intake (picked_up by driver, arrived at station)
        $awaitingIntakeOrders = Order::with(['customer.user', 'items', 'delivery.driver.user'])
            ->where('status', 'picked_up')
            ->latest()
            ->get();

        // 2. Intake Discrepancies needing resolution (received_at_station with discrepancy or uncompleted intake)
        $intakeDiscrepancies = Order::with(['customer.user', 'containerIntake'])
            ->where('status', 'received_at_station')
            ->whereHas('containerIntake', function ($iq) {
                $iq->whereNull('completed_at')
                   ->orWhereColumn('expected_count', '!=', 'received_count')
                   ->orWhere('rejected_count', '>', 0)
                   ->orWhere('damaged_count', '>', 0);
            })
            ->latest()
            ->get();

        // 3. Summary Statistics. Database-agnostic queries working seamlessly
        // across PostgreSQL, SQLite, and MySQL.
        $waitingEntries = RefillQueueEntry::where('status', 'waiting')->pluck('queued_at');
        $waitingCount = $waitingEntries->count();
        $refillingCount = RefillQueueEntry::where('status', 'in_progress')->count();
        $hasActiveRefill = $refillingCount > 0;
        $readyCount = Order::where('status', 'ready_for_delivery')->count();
        $completedTodayCount = RefillQueueEntry::where('status', 'completed')
            ->whereDate('completed_at', Carbon::today())
            ->count();

        // Gallons waiting
        $totalGallonsWaiting = (int) Order::query()
            ->leftJoin('order_container_intakes', 'order_container_intakes.order_id', '=', 'orders.id')
            ->where('orders.status', 'waiting_in_queue')
            ->sum(DB::raw('COALESCE(order_container_intakes.accepted_count, orders.expected_empty_gallons, 0)'));

        // Average Waiting Duration (in minutes for currently waiting orders)
        $avgWaitMinutes = $waitingCount > 0
            ? (int) round($waitingEntries->avg(fn($q) => $q ? Carbon::parse($q)->diffInSeconds(now()) : 0) / 60)
            : 0;

        $stats = [
            'waiting' => $waitingCount,
            'totalGallons' => $totalGallonsWaiting,
            'refilling' => $refillingCount,
            'ready' => $readyCount,
            'completedToday' => $completedTodayCount,
            'awaitingIntake' => $awaitingIntakeOrders->count(),
            'discrepancies' => $intakeDiscrepancies->count(),
            'avgWaitMinutes' => $avgWaitMinutes,
            'hasActiveRefill' => $hasActiveRefill,
        ];

        // Active layout sidebar badge counts
        $sidebarCounts = [
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'waitingInQueue' => $waitingCount,
            'lowStock' => 0,
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'queue_count' => $queueEntries->total(),
                'next_eligible_id' => $nextEligibleId,
                'has_active_refill' => $hasActiveRefill,
                'entries' => $queueEntries->items(),
            ]);
        }

        return view('admin.refill-queue.index', compact(
            'queueEntries',
            'nextEligibleId',
            'hasActiveRefill',
            'awaitingIntakeOrders',
            'intakeDiscrepancies',
            'stats',
            'statusFilter',
            'priorityFilter',
            'search',
            'dateFilter',
            'sidebarCounts'
        ));
    }
}
