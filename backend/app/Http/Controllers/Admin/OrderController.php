<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\WalkInOrder;
use App\Services\OrderWorkflowService;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    private const WALK_IN_PRICE_PER_GALLON = 30.00;

    /**
     * Return the live count used by the Orders notification badge.
     */
    public function pendingCount(\App\Services\AdminSharedDataService $sharedData)
    {
        return response()->json([
            'count' => $sharedData->getSidebarCounts()['pendingOrders'],
        ]);
    }

    /**
     * Display a listing of orders with real-time database-backed KPI cards,
     * status dropdown, advanced filters, and pagination.
     */
    public function index(Request $request)
    {
        $status = $request->get('status');
        $search = $request->get('search');
        $paymentMethod = $request->get('payment_method');
        $driverId = $request->get('driver_id');
        $deliveryMethod = $request->get('delivery_method');

        $query = Order::with([
            'customer:id,user_id',
            'customer.user:id,name,phone,email,avatar_path',
            'address:id,customer_id,address_line,barangay,city',
            'items:id,order_id,product_id,quantity,unit_price',
            'items.product:id,name,product_type',
            'delivery:id,order_id,driver_id,status',
            'delivery.driver:id,user_id,vehicle_plate',
            'delivery.driver.user:id,name,phone',
            'containerIntake',
            'payment:id,order_id,status,method,amount',
        ])->latest('created_at');

        // Status Filtering (Supports canonical values and logical groupings)
        if ($status && $status !== 'all') {
            if ($status === 'exceptions' || $status === 'failed') {
                $query->whereIn('status', ['rejected', 'cancelled', 'pickup_failed', 'delivery_failed']);
            } else {
                $query->where('status', strtolower($status));
            }
        }

        // Driver Filtering
        if ($driverId) {
            $query->whereHas('delivery', function ($dq) use ($driverId) {
                $dq->where('driver_id', $driverId);
            });
        }

        // Payment Method Filtering (Cash, Credit, and Online)
        if ($paymentMethod && in_array($paymentMethod, ['cash', 'credit', 'online'])) {
            $query->where('payment_method', $paymentMethod);
        }

        // Delivery Method Filtering (Home Delivery and Station Pickup)
        if ($deliveryMethod && in_array($deliveryMethod, ['home_delivery', 'station_pickup'])) {
            $query->where('delivery_method', $deliveryMethod);
        }

        // Search Filtering (Search by order number, ID, customer name, phone, email, driver, address)
        if ($search) {
            $searchClean = trim($search);
            $query->where(function ($q) use ($searchClean) {
                $q->where('order_number', 'ilike', "%{$searchClean}%")
                  ->orWhere('id', 'ilike', "%{$searchClean}%")
                  ->orWhereHas('customer.user', function ($uq) use ($searchClean) {
                      $uq->where('name', 'ilike', "%{$searchClean}%")
                         ->orWhere('phone', 'ilike', "%{$searchClean}%")
                         ->orWhere('email', 'ilike', "%{$searchClean}%");
                  })
                  ->orWhereHas('delivery.driver.user', function ($duq) use ($searchClean) {
                      $duq->where('name', 'ilike', "%{$searchClean}%");
                  })
                  ->orWhereHas('address', function ($aq) use ($searchClean) {
                      $aq->where('address_line', 'ilike', "%{$searchClean}%");
                  });
            });
        }

        $orders = $query->paginate(5)->withQueryString();
        $assignableOrders = Order::where('status', 'confirmed')
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereDoesntHave('delivery', fn ($q) => $q->whereNotNull('driver_id'))
            ->with([
                'customer.user:id,name,phone',
                'address:id,customer_id,address_line,barangay,city',
                'items:id,order_id,product_id,quantity,unit_price',
                'items.product:id,name,product_type',
                'rfidTransactions' => fn ($query) => $query->with('rfidTag.gallon')->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->latest('created_at')
            ->take(20)
            ->get();
        $walkInOrders = WalkInOrder::latest('created_at')->take(10)->get();
        $walkInPricePerGallon = self::WALK_IN_PRICE_PER_GALLON;
        $todayStart = today()->startOfDay();
        $tomorrow = $todayStart->copy()->addDay();
        $isPgsql = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';

        $orderStatsBundle = \Illuminate\Support\Facades\Cache::store('file')->remember('admin:orders:stats', now()->addSeconds(15), function () use ($todayStart, $tomorrow, $isPgsql) {
            if ($isPgsql) {
                $walkInSummaryRow = WalkInOrder::query()->selectRaw(
                    'COUNT(*) AS total, COALESCE(SUM(amount), 0) AS payments, COUNT(*) FILTER (WHERE created_at >= ? AND created_at < ?) AS today_count, COALESCE(SUM(amount) FILTER (WHERE created_at >= ? AND created_at < ?), 0) AS today_payments',
                    [$todayStart, $tomorrow, $todayStart, $tomorrow]
                )->first();
            } else {
                $walkInSummaryRow = WalkInOrder::query()->selectRaw(
                    'COUNT(*) AS total, COALESCE(SUM(amount), 0) AS payments, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS today_count, COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN amount ELSE 0 END), 0) AS today_payments',
                    [$todayStart, $tomorrow, $todayStart, $tomorrow]
                )->first();
            }
            $walkInSummary = [
                'count' => (int) ($walkInSummaryRow?->total ?? 0),
                'payments' => (float) ($walkInSummaryRow?->payments ?? 0),
                'today_count' => (int) ($walkInSummaryRow?->today_count ?? 0),
                'today_payments' => (float) ($walkInSummaryRow?->today_payments ?? 0),
            ];

            // One grouped query replaces nine round trips to the remote database.
            $orderCountsCollection = Order::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
            $orderCounts = $orderCountsCollection->toArray();
            $stats = [
                'total' => (int) $orderCountsCollection->sum() + $walkInSummary['count'],
                'pending' => (int) ($orderCounts['pending'] ?? 0),
                'confirmed' => (int) ($orderCounts['confirmed'] ?? 0),
                'assigned' => (int) ($orderCounts['assigned'] ?? 0),
                'out_for_delivery' => (int) ($orderCounts['out_for_delivery'] ?? 0),
                'completed' => (int) ($orderCounts['completed'] ?? 0) + $walkInSummary['count'],
                'cancelled' => (int) ($orderCounts['cancelled'] ?? 0),
                'station_refill' => (int) ($orderCounts['received_at_station'] ?? 0) + (int) ($orderCounts['refilling'] ?? 0),
                'exceptions' => (int) ($orderCounts['rejected'] ?? 0) + (int) ($orderCounts['cancelled'] ?? 0) + (int) ($orderCounts['pickup_failed'] ?? 0) + (int) ($orderCounts['delivery_failed'] ?? 0),
            ];

            return compact('walkInSummary', 'orderCounts', 'stats');
        });

        $walkInSummary = $orderStatsBundle['walkInSummary'];
        $orderCounts = is_array($orderStatsBundle['orderCounts'] ?? null)
            ? $orderStatsBundle['orderCounts']
            : (array) ($orderStatsBundle['orderCounts'] ?? []);
        $stats = $orderStatsBundle['stats'];

        // Active Drivers for Assignment Modal and Filter Dropdown
        $activeDrivers = Driver::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })
        ->with('user:id,name,phone,avatar_path')
        ->withCount(['deliveries as active_deliveries_count' => function ($dq) {
            $dq->whereIn('status', ['assigned', 'en_route_for_pickup', 'picked_up', 'out_for_delivery']);
        }])
        ->get()
        ->map(function ($driver) {
            $activeCount = (int) $driver->active_deliveries_count;
            return [
                'id' => $driver->id,
                'name' => $driver->user->name,
                'avatar_url' => $driver->user?->avatar_url,
                'phone' => $driver->user->phone,
                'vehicle_plate' => $driver->vehicle_plate ?: 'Tricycle',
                'active_deliveries' => $activeCount,
                'is_available' => $activeCount < 4,
                'status_text' => $activeCount < 4 ? 'Available' : 'Busy',
            ];
        });
        $assignableDrivers = $activeDrivers->where('is_available', true)->values();

        // Sidebar Navigation Counts via AdminSharedDataService
        $sharedData = app(\App\Services\AdminSharedDataService::class);
        $sharedData->provide([
            'pendingOrders' => $stats['pending'],
        ]);
        $sidebarCounts = $sharedData->getSidebarCounts($request->user());

        return view('admin.orders.index', compact(
            'orders',
            'assignableOrders',
            'walkInOrders',
            'walkInSummary',
            'walkInPricePerGallon',
            'stats',
            'activeDrivers',
            'assignableDrivers',
            'status',
            'search',
            'paymentMethod',
            'driverId',
            'deliveryMethod',
            'sidebarCounts'
        ));
    }

    /** Store a completed counter-sale / walk-in order. */
    public function storeWalkIn(Request $request)
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'full_name' => ['required', 'string', 'max:150'],
            'gallons' => ['required', 'integer', 'min:1', 'max:1000'],
            'payment_method' => ['required', 'in:cash,gcash,paymongo,debt'],
        ]);

        $data['amount'] = round(self::WALK_IN_PRICE_PER_GALLON * $data['gallons'], 2);

        $walkInOrder = WalkInOrder::firstOrCreate(
            ['request_key' => $data['request_key']],
            [
                'full_name' => $data['full_name'],
                'gallons' => $data['gallons'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'created_by' => $request->user()->id,
                'completed_at' => now(),
            ]
        );

        if ($walkInOrder->wasRecentlyCreated) {
            Cache::store('file')->forget('admin:orders:stats');
            Cache::forget('admin:dashboard:station-kpis');

            foreach (['all', 'today', 'week', 'month'] as $range) {
                foreach (['all', 'cash', 'credit', 'debt', 'online', 'gcash', 'paymongo'] as $method) {
                    Cache::store('file')->forget("admin:reports:{$range}:{$method}");
                }
            }
        }

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Walk-in order created successfully.');
    }

    /**
     * Display comprehensive order details including customer info, container intake,
     * driver assignment, payment breakdown, and chronological timeline.
     */
    public function show(Order $order)
    {
        $order->load([
            'customer.user',
            'customer.addresses',
            'address',
            'items.product',
            'delivery.driver.user',
            'delivery.statusHistory',
            'containerIntake.receivedBy',
            'statusHistory.changedBy',
            'payment',
            'rfidTransactions' => fn ($query) => $query->with(['rfidTag.gallon', 'scannedBy'])->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        $assignableOrders = Order::where('status', 'confirmed')
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereDoesntHave('delivery', fn ($q) => $q->whereNotNull('driver_id'))
            ->with([
                'customer.user',
                'address',
                'items.product',
                'rfidTransactions' => fn ($query) => $query->with('rfidTag.gallon')->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->latest('created_at')
            ->take(20)
            ->get();

        // Active Drivers for Quick Assign option inside details view
        $activeDrivers = Driver::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })
        ->with([
            'user',
            'deliveries' => function ($dq) {
                $dq->whereIn('status', ['assigned', 'en_route_for_pickup', 'picked_up', 'out_for_delivery']);
            }
        ])
        ->get()
        ->map(function ($driver) {
            $activeCount = $driver->deliveries->count();
            return [
                'id' => $driver->id,
                'name' => $driver->user->name,
                'avatar_url' => $driver->user?->avatar_url,
                'phone' => $driver->user->phone,
                'vehicle_plate' => $driver->vehicle_plate ?: 'Tricycle',
                'active_deliveries' => $activeCount,
                'is_available' => $activeCount < 4,
                'status_text' => $activeCount < 4 ? 'Available' : 'Busy',
            ];
        });
        $assignableDrivers = $activeDrivers->where('is_available', true)->values();

        // Sidebar Navigation Counts
        $sidebarCounts = [
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'lowStock' => Inventory::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
        ];

        return view('admin.orders.show', compact('order', 'assignableOrders', 'activeDrivers', 'assignableDrivers', 'sidebarCounts'));
    }

    /**
     * Transition an order's canonical status via OrderWorkflowService.
     */
    public function transition(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        $data = $request->validate([
            'status' => 'required|string|in:confirmed,rejected,cancelled,ready_for_pickup,completed',
            'reason' => 'nullable|string|max:1000'
        ]);

        try {
            $workflow->transition(
                $order,
                $data['status'],
                $request->user(),
                $data['reason'] ?? null
            );

            $automaticallyAssigned = 0;
            if ($data['status'] === 'confirmed') {
                try {
                    $automaticallyAssigned = app(DeliveryService::class)->assignAutomaticBatches($request->user());
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $statusFormatted = ucwords(str_replace('_', ' ', $data['status']));

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Order #{$order->order_number} status updated to {$statusFormatted}.",
                    'status' => $data['status'],
                    'automatically_assigned' => $automaticallyAssigned,
                ]);
            }

            return back()->with('success', "Order #{$order->order_number} status updated to {$statusFormatted}.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Cannot perform this transition.',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Failed to transition order status.',
                ], 500);
            }
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    /** Confirm eligible pending orders individually and report row-level failures. */
    public function confirmAll(Request $request, OrderWorkflowService $workflow, DeliveryService $deliveryService)
    {
        $eligible = Order::query()->where('status', 'pending')->orderBy('created_at')->orderBy('id')->pluck('id');
        $result = ['confirmed' => 0, 'failed' => [], 'assignment_failed' => [], 'skipped' => 0, 'automatically_assigned' => 0];

        foreach ($eligible as $id) {
            try {
                $order = Order::find($id);
                if (!$order || $order->status !== 'pending') { $result['skipped']++; continue; }
                $workflow->transition($order, 'confirmed', $request->user());
                $result['confirmed']++;
            } catch (\Throwable $e) {
                $result['failed'][] = ['order_id' => $id, 'reason' => $e->getMessage() ?: 'Order validation failed.'];
            }
        }

        try {
            $result['automatically_assigned'] = $deliveryService->assignAutomaticBatches($request->user());
        } catch (\Throwable $e) {
            $result['assignment_failed'][] = ['order_id' => null, 'reason' => $e->getMessage() ?: 'Automatic driver assignment failed.'];
        }

        if ($result['automatically_assigned'] === 0 && !empty(config('dispatch.primary_driver_email')) && !Driver::query()
            ->whereHas('user', fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [strtolower((string) config('dispatch.primary_driver_email', 'testdriver123@example.com'))])
                ->where('status', 'active'))
            ->exists()) {
            $result['assignment_failed'][] = ['order_id' => null, 'reason' => 'Active driver ' . strtolower((string) config('dispatch.primary_driver_email', 'testdriver123@example.com')) . ' was not found.'];
        }

        return response()->json($result);
    }
}
