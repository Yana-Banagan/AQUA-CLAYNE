<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderContainerIntake;
use App\Models\RefillQueueEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefillQueueController extends Controller
{
    public function status()
    {
        $stats = Cache::remember('refill-queue:quick-status', now()->addSeconds(5), function () {
            $isPgsql = DB::getDriverName() === 'pgsql';
            if ($isPgsql) {
                return RefillQueueEntry::query()->selectRaw("
                    COUNT(*) FILTER (WHERE status = 'waiting') AS waiting,
                    COUNT(*) FILTER (WHERE status = 'in_progress') AS refilling
                ")->first();
            }

            return RefillQueueEntry::query()->selectRaw("
                SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS refilling
            ")->first();
        });

        return response()->json([
            'waiting' => (int) ($stats->waiting ?? 0),
            'refilling' => (int) ($stats->refilling ?? 0),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request)
    {
        $statusFilter = 'in_progress';
        $priorityFilter = $request->get('priority', 'all'); // 'all', 'high', 'normal', 'low'
        $search = trim($request->get('search', ''));
        $dateFilter = $request->get('date');
        $isAjax = $request->wantsJson() || $request->ajax();

        // Base Queue Query
        $queueQuery = RefillQueueEntry::with([
            'order.customer.user:id,name,phone,email',
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

        // Determine the single next eligible waiting queue entry ID.
        $nextEligibleId = RefillQueueEntry::where('status', 'waiting')
            ->orderBy('priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->value('id');

        // Consolidated Summary Statistics with short 10s caching
        $stats = Cache::remember('refill-queue:summary-stats', now()->addSeconds(10), function () {
            $today = Carbon::today();
            $isPgsql = DB::getDriverName() === 'pgsql';

            if ($isPgsql) {
                $queueRow = RefillQueueEntry::query()->selectRaw("
                    COUNT(*) FILTER (WHERE status = 'waiting') AS waiting_count,
                    COUNT(*) FILTER (WHERE status = 'in_progress') AS refilling_count,
                    COUNT(*) FILTER (WHERE status = 'completed' AND completed_at >= ?) AS completed_today_count,
                    COALESCE(AVG(EXTRACT(EPOCH FROM (NOW() - queued_at))) FILTER (WHERE status = 'waiting'), 0) AS avg_wait_seconds
                ", [$today])->first();
            } else {
                $queueRow = RefillQueueEntry::query()->selectRaw("
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS refilling_count,
                    SUM(CASE WHEN status = 'completed' AND completed_at >= ? THEN 1 ELSE 0 END) AS completed_today_count,
                    0 AS avg_wait_seconds
                ", [$today])->first();
            }

            $waitingCount = (int) ($queueRow->waiting_count ?? 0);
            $refillingCount = (int) ($queueRow->refilling_count ?? 0);
            $completedTodayCount = (int) ($queueRow->completed_today_count ?? 0);
            $avgWaitMinutes = (int) round(((float) ($queueRow->avg_wait_seconds ?? 0)) / 60);

            // If SQLite or avg was 0 and waitingCount > 0, fallback wait calculation
            if ($avgWaitMinutes === 0 && $waitingCount > 0 && !$isPgsql) {
                $waitingEntries = RefillQueueEntry::where('status', 'waiting')->pluck('queued_at');
                $avgWaitMinutes = (int) round($waitingEntries->avg(fn($q) => $q ? Carbon::parse($q)->diffInSeconds(now()) : 0) / 60);
            }

            $readyCount = Order::where('status', 'ready_for_delivery')->count();

            $totalGallonsWaiting = (int) Order::query()
                ->leftJoin('order_container_intakes', 'order_container_intakes.order_id', '=', 'orders.id')
                ->where('orders.status', 'waiting_in_queue')
                ->sum(DB::raw('COALESCE(order_container_intakes.accepted_count, orders.expected_empty_gallons, 0)'));

            $awaitingIntakeCount = Order::where('status', 'picked_up')->count();

            $discrepanciesCount = Order::where('status', 'received_at_station')
                ->whereHas('containerIntake', function ($iq) {
                    $iq->whereNull('completed_at')
                       ->orWhereColumn('expected_count', '!=', 'received_count')
                       ->orWhere('rejected_count', '>', 0)
                       ->orWhere('damaged_count', '>', 0);
                })
                ->count();

            return [
                'waiting' => $waitingCount,
                'totalGallons' => $totalGallonsWaiting,
                'refilling' => $refillingCount,
                'ready' => $readyCount,
                'completedToday' => $completedTodayCount,
                'awaitingIntake' => $awaitingIntakeCount,
                'discrepancies' => $discrepanciesCount,
                'avgWaitMinutes' => $avgWaitMinutes,
                'hasActiveRefill' => $refillingCount > 0,
            ];
        });

        $hasActiveRefill = $stats['hasActiveRefill'];

        // If AJAX request, return immediately without loading heavy relations
        if ($isAjax) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'queue_count' => $queueEntries->total(),
                'next_eligible_id' => $nextEligibleId,
                'has_active_refill' => $hasActiveRefill,
                'entries' => $queueEntries->items(),
            ]);
        }

        // Non-AJAX view requests load awaiting intake and discrepancies
        $awaitingIntakeOrders = Order::with([
            'customer.user:id,name,phone',
            'items:id,order_id,product_id,quantity,unit_price',
            'delivery.driver.user:id,name,phone',
        ])
            ->where('status', 'picked_up')
            ->latest('created_at')
            ->get();

        $intakeDiscrepancies = Order::with([
            'customer.user:id,name,phone',
            'containerIntake',
        ])
            ->where('status', 'received_at_station')
            ->whereHas('containerIntake', function ($iq) {
                $iq->whereNull('completed_at')
                   ->orWhereColumn('expected_count', '!=', 'received_count')
                   ->orWhere('rejected_count', '>', 0)
                   ->orWhere('damaged_count', '>', 0);
            })
            ->latest('created_at')
            ->get();

        $sidebarCounts = [
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'waitingInQueue' => $stats['waiting'],
            'lowStock' => 0,
        ];

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
