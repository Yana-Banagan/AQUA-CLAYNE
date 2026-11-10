<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\PickupBatch;
use App\Models\PickupBatchItem;
use App\Services\DispatchService;
use App\Services\SupabaseStorageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AQUA CLAYNE — Admin Deliveries Controller
 *
 * Handles the Deliveries Dashboard page, AJAX data refresh,
 * and individual delivery detail fetching.
 *
 * Driver assignment is handled by DeliveryAssignmentController
 * which delegates to DeliveryService::assign().
 */
class DeliveryController extends Controller
{
    public function status()
    {
        $todayStart = Carbon::today()->startOfDay();
        $tomorrow = $todayStart->copy()->addDay();
        $summary = Delivery::query()->selectRaw("
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'pending' OR driver_id IS NULL) AS pending,
            COUNT(*) FILTER (WHERE status IN ('assigned', 'en_route', 'en_route_for_pickup', 'picked_up', 'at_station', 'out_for_delivery')) AS on_delivery,
            COUNT(*) FILTER (WHERE status = 'assigned') AS assigned,
            COUNT(*) FILTER (WHERE status IN ('en_route', 'en_route_for_pickup', 'picked_up', 'at_station', 'ready_for_delivery', 'out_for_delivery')) AS en_route,
            COUNT(*) FILTER (WHERE status IN ('delivered', 'completed')) AS delivered,
            COUNT(*) FILTER (WHERE status IN ('failed', 'pickup_failed', 'delivery_failed')) AS failed,
            COUNT(*) FILTER (WHERE status IN ('delivered', 'completed') AND ((delivered_at >= ? AND delivered_at < ?) OR (delivered_at IS NULL AND created_at >= ? AND created_at < ?))) AS completed_today,
            MAX(created_at) AS latest_update
        ", [$todayStart, $tomorrow, $todayStart, $tomorrow])->first();

        $unassignedOrders = Order::query()
            ->whereIn('status', ['confirmed', 'ready_for_delivery'])
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereDoesntHave('delivery', fn ($query) => $query->whereNotNull('driver_id'))
            ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS latest_update')
            ->first();

        $pickupOrdersRevision = Order::query()
            ->where('status', 'confirmed')
            ->where('delivery_method', '!=', 'station_pickup')
            ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS latest_update')
            ->first();
        $pickupBatchesRevision = PickupBatch::query()
            ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS latest_update')
            ->first();
        $pickupItemsRevision = PickupBatchItem::query()
            ->selectRaw('COUNT(*) AS total, MAX(updated_at) AS latest_update')
            ->first();

        $stats = [
            'total' => (int) $summary->total,
            'pending' => (int) $summary->pending + (int) $unassignedOrders->total,
            'onDelivery' => (int) $summary->on_delivery,
            'completedToday' => (int) $summary->completed_today,
        ];
        $statusCounts = [
            'pending' => $stats['pending'],
            'assigned' => (int) $summary->assigned,
            'en_route' => (int) $summary->en_route,
            'delivered' => (int) $summary->delivered,
            'failed' => (int) $summary->failed,
        ];

        $mapDeliveries = Delivery::whereIn('status', [
            'assigned', 'en_route', 'en_route_for_pickup', 'picked_up',
            'at_station', 'ready_for_delivery', 'out_for_delivery',
        ])->with(['order.address', 'driver.user'])->get();
        $liveLocations = $mapDeliveries->map(function (Delivery $delivery) {
            $address = $delivery->order?->address;

            return [
                'id' => $delivery->id,
                'order_number' => $delivery->order?->order_number ?? strtoupper(substr($delivery->order_id, 0, 8)),
                'driver_id' => $delivery->driver_id,
                'driver_name' => $delivery->driver?->user?->name ?? 'Unassigned',
                'driver_latitude' => $delivery->driver_latitude,
                'driver_longitude' => $delivery->driver_longitude,
                'destination_latitude' => $address?->latitude,
                'destination_longitude' => $address?->longitude,
                'address' => $address
                    ? collect([$address->address_line, $address->barangay, $address->city, $address->province])->filter()->implode(', ')
                    : 'Station Pickup',
                'status' => ucwords(str_replace('_', ' ', $delivery->status)),
                'location_updated_at' => $delivery->location_updated_at?->toIso8601String(),
                'route_origin_latitude' => $delivery->driver_latitude,
                'route_origin_longitude' => $delivery->driver_longitude,
            ];
        })->values();

        return response()->json([
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'liveLocations' => $liveLocations,
            'revision' => [(string) $summary->latest_update, (string) $unassignedOrders->latest_update],
            'pickupRevision' => [
                (int) $pickupOrdersRevision->total,
                (string) $pickupOrdersRevision->latest_update,
                (int) $pickupBatchesRevision->total,
                (string) $pickupBatchesRevision->latest_update,
                (int) $pickupItemsRevision->total,
                (string) $pickupItemsRevision->latest_update,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    // =========================================================================
    // INDEX — Deliveries Dashboard
    // =========================================================================

    public function index(Request $request, DispatchService $dispatchService)
    {
        $search   = trim((string) $request->get('search', ''));
        $status   = $request->get('status');
        $driverId = $request->get('driver_id');
        $date     = $request->get('date');

        // ------------------------------------------------------------------
        // RECENT DELIVERIES — paginated, server-side filtered
        // ------------------------------------------------------------------
        $query = Delivery::with([
            'order.customer.user:id,name,phone',
            'order.address',
            'order.payment:id,order_id,method,status',
            'driver.user:id,name',
            'statusHistory',
        ]);

        // Server-side search (Delivery ID / Order # / Customer name-phone / Driver name)
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'ilike', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'ilike', "%{$search}%")
                            ->orWhere('id', 'ilike', "%{$search}%")
                            ->orWhereHas('customer.user', function ($uq) use ($search) {
                                $uq->where('name', 'ilike', "%{$search}%")
                                    ->orWhere('phone', 'ilike', "%{$search}%");
                            });
                    })
                    ->orWhereHas('driver.user', function ($dq) use ($search) {
                        $dq->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Status filter — semantic groupings map to actual DB values
        if ($status) {
            match ($status) {
                'pending'   => $query->where(function ($q) {
                    $q->where('status', 'pending')->orWhereNull('driver_id');
                }),
                'assigned'  => $query->where('status', 'assigned'),
                'en_route'  => $query->whereIn('status', [
                    'en_route',
                    'en_route_for_pickup',
                    'picked_up',
                    'at_station',
                    'ready_for_delivery',
                    'out_for_delivery',
                ]),
                'delivered' => $query->whereIn('status', ['delivered', 'completed']),
                'failed'    => $query->whereIn('status', ['failed', 'pickup_failed', 'delivery_failed']),
                default     => $query->where('status', $status),
            };
        }

        // Driver filter
        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        // Date filter (created_at or delivered_at)
        if ($date) {
            $query->where(function ($q) use ($date) {
                $q->whereDate('created_at', $date)
                    ->orWhereDate('delivered_at', $date);
            });
        }

        // Completed delivery history moves to the archive automatically once it
        // is more than 24 hours old. The split is derived from completion time,
        // so it stays current without a scheduled database update.
        $archiveCutoff = Carbon::now()->subDay();
        $completedStatuses = ['delivered', 'completed'];
        $deliveries = (clone $query)
            ->where(function ($q) use ($archiveCutoff, $completedStatuses) {
                $q->whereNotIn('status', $completedStatuses)
                    ->orWhereRaw('COALESCE(delivered_at, created_at) >= ?', [$archiveCutoff]);
            })
            ->orderByRaw('COALESCE(delivered_at, created_at) DESC')
            ->paginate(5, ['*'], 'recent_history_page')
            ->withQueryString()
            ->fragment('tabDeliveryHistory');

        // Archive deliveries only needs order, customer, address, driver (no payment/statusHistory)
        $archivedDeliveries = Delivery::whereIn('status', $completedStatuses)
            ->whereRaw('COALESCE(delivered_at, created_at) < ?', [$archiveCutoff])
            ->with([
                'order:id,order_number,customer_id',
                'order.customer.user:id,name,phone',
                'order.address:id,order_id,address_line1,barangay',
                'driver.user:id,name',
            ])
            ->orderByRaw('COALESCE(delivered_at, created_at) DESC')
            ->paginate(5, ['*'], 'archive_history_page')
            ->withQueryString()
            ->fragment('tabDeliveryHistory');

        // ------------------------------------------------------------------
        // DRIVERS — all for filter dropdown, active-only for assignment
        // Consolidated into a single query to eliminate duplicate roundtrips
        // ------------------------------------------------------------------
        $allDrivers = Driver::with([
            'user:id,name,phone,status,avatar_path',
            'deliveries' => fn($dq) => $dq->whereIn('status', [
                'assigned', 'en_route_for_pickup', 'picked_up', 'out_for_delivery',
            ])->select(['id', 'driver_id', 'status']),
        ])->get(['id', 'user_id', 'vehicle_plate']);

        $activeDrivers = $allDrivers->where('user.status', 'active')->values();

        // ------------------------------------------------------------------
        // KPI STATS & STATUS BREAKDOWN — Consolidated Single Query
        // ------------------------------------------------------------------
        $today = Carbon::today();
        $statsBundle = \Illuminate\Support\Facades\Cache::store('file')->remember('admin:deliveries:stats', now()->addSeconds(30), function () use ($today) {
            $tomorrow = $today->copy()->addDay();
            $unassignedOrdersCount = Order::where('status', 'confirmed')
                ->where('delivery_method', '!=', 'station_pickup')
                ->whereDoesntHave('delivery', fn($q) => $q->whereNotNull('driver_id'))
                ->count();

            $row = Delivery::selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' OR driver_id IS NULL THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('assigned', 'en_route', 'en_route_for_pickup', 'picked_up', 'at_station', 'out_for_delivery') THEN 1 ELSE 0 END) AS on_delivery,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN status IN ('en_route', 'en_route_for_pickup', 'picked_up', 'at_station', 'ready_for_delivery', 'out_for_delivery') THEN 1 ELSE 0 END) AS en_route,
                SUM(CASE WHEN status IN ('delivered', 'completed') THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status IN ('failed', 'pickup_failed', 'delivery_failed') THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status IN ('delivered', 'completed') AND ((delivered_at >= ? AND delivered_at < ?) OR (delivered_at IS NULL AND created_at >= ? AND created_at < ?)) THEN 1 ELSE 0 END) AS completed_today
            ", [$today, $tomorrow, $today, $tomorrow])->first();

            $stats = [
                'total' => (int) ($row?->total ?? 0),
                'pending' => (int) ($row?->pending ?? 0) + $unassignedOrdersCount,
                'onDelivery' => (int) ($row?->on_delivery ?? 0),
                'completedToday' => (int) ($row?->completed_today ?? 0),
            ];

            $statusCounts = [
                'pending' => $stats['pending'],
                'assigned' => (int) ($row?->assigned ?? 0),
                'en_route' => (int) ($row?->en_route ?? 0),
                'delivered' => (int) ($row?->delivered ?? 0),
                'failed' => (int) ($row?->failed ?? 0),
            ];

            return compact('stats', 'statusCounts');
        });

        $stats = $statsBundle['stats'];
        $statusCounts = $statsBundle['statusCounts'];

        // ------------------------------------------------------------------
        // LIVE DELIVERIES — active in-transit panel (≤ 8 cards)
        // ------------------------------------------------------------------
        $liveDeliveries = Delivery::whereIn('status', [
            'assigned', 'en_route', 'en_route_for_pickup',
            'picked_up', 'at_station', 'ready_for_delivery', 'out_for_delivery',
        ])
            ->with(['order.customer.user:id,name,phone', 'order.address', 'driver.user:id,name'])
            ->latest('created_at')
            ->take(8)
            ->get();
        $mapLocations = $liveDeliveries->map(fn ($delivery) => $this->formatLiveDelivery($delivery))->values();

        // ------------------------------------------------------------------
        // ASSIGNABLE ORDERS — confirmed / ready orders with no driver yet
        // Only loads customer, address, and delivery needed by the assign modal dropdown
        // ------------------------------------------------------------------
        $assignableOrders = Order::where('status', 'confirmed')
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereDoesntHave('delivery', fn($q) => $q->whereNotNull('driver_id'))
            ->with([
                'customer.user:id,name',
                'address:id,order_id,address_line',
                'delivery:id,order_id',
            ])
            ->latest('created_at')
            ->take(20)
            ->get(['id', 'order_number', 'customer_id', 'created_at']);

        // ------------------------------------------------------------------
        // SIDEBAR BADGE COUNTS — populated via AdminSharedDataService
        // ------------------------------------------------------------------
        $sharedData = app(\App\Services\AdminSharedDataService::class);
        $sharedData->provide([
            'activeDeliveries' => $stats['onDelivery'],
            'pendingOrders' => $statusCounts['pending'],
        ]);
        $sidebarCounts = $sharedData->getSidebarCounts($request->user());

        // ------------------------------------------------------------------
        // DISPATCH & BATCHING DATA FOR TWO DRIVERS (Reusing active drivers collection)
        // ------------------------------------------------------------------
        $driverBoard = $dispatchService->getDriverStatuses();

        $dispatchBundle = \Illuminate\Support\Facades\Cache::store('file')->remember('admin:deliveries:dispatch-bundle', now()->addSeconds(30), function () use ($dispatchService, $activeDrivers) {
            return [
                'suggestedPickupBatches' => $dispatchService->suggestPickupBatches($activeDrivers),
                'suggestedDeliveryRoutes' => $dispatchService->suggestDeliveryRoutes($activeDrivers),
            ];
        });
        $suggestedPickupBatches = $dispatchBundle['suggestedPickupBatches'];
        $suggestedDeliveryRoutes = $dispatchBundle['suggestedDeliveryRoutes'];

        $suggestedPickupPage = max(1, (int) $request->query('suggested_pickup_page', 1));
        $suggestedPickupBatchesPage = new LengthAwarePaginator(
            array_slice($suggestedPickupBatches, ($suggestedPickupPage - 1) * 5, 5),
            count($suggestedPickupBatches),
            5,
            $suggestedPickupPage,
            [
                'path' => $request->url(),
                'pageName' => 'suggested_pickup_page',
                'query' => $request->query(),
            ],
        );
        $activePickupBatches = PickupBatch::with(['driver.user:id,name', 'items.order.address', 'items.order.customer.user:id,name'])
            ->whereIn('status', ['ready', 'in_progress'])
            ->latest()
            ->paginate(5, ['*'], 'active_pickup_page')
            ->withQueryString();
        $activeDeliveryRoutes = DeliveryRoute::with(['driver.user:id,name', 'stops.order.address', 'stops.order.customer.user:id,name', 'stops.order.payment'])
            ->whereIn('status', ['ready', 'in_progress'])
            ->latest()
            ->get();
        $historyBatches = collect(\Illuminate\Support\Facades\Cache::store('file')->remember('admin:deliveries:history-batches', now()->addSeconds(60), function () {
            return PickupBatch::with(['driver.user:id,name'])
                ->whereIn('status', ['completed', 'cancelled'])
                ->latest()
                ->take(15)
                ->get()
                ->map(fn($b) => (object) [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'area' => $b->area,
                    'total_expected_gallons' => $b->total_expected_gallons,
                    'status' => $b->status,
                    'updated_at' => $b->updated_at,
                    'driver' => $b->driver ? (object) [
                        'user' => $b->driver->user ? (object) ['name' => $b->driver->user->name] : null,
                    ] : null,
                ])
                ->all();
        }));

        $historyRoutes = collect(\Illuminate\Support\Facades\Cache::store('file')->remember('admin:deliveries:history-routes', now()->addSeconds(60), function () {
            return DeliveryRoute::with(['driver.user:id,name'])
                ->whereIn('status', ['completed', 'cancelled'])
                ->latest()
                ->take(15)
                ->get()
                ->map(fn($r) => (object) [
                    'id' => $r->id,
                    'route_number' => $r->route_number,
                    'area' => $r->area,
                    'total_gallons' => $r->total_gallons,
                    'status' => $r->status,
                    'updated_at' => $r->updated_at,
                    'driver' => $r->driver ? (object) [
                        'user' => $r->driver->user ? (object) ['name' => $r->driver->user->name] : null,
                    ] : null,
                ])
                ->all();
        }));

        // ------------------------------------------------------------------
        // JSON RESPONSE — for AJAX / Realtime refresh from admin-deliveries.js
        // ------------------------------------------------------------------
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'stats'      => $stats,
                'statusCounts' => $statusCounts,
                'driverBoard' => $driverBoard,
                'suggestedBatches' => $suggestedPickupBatches,
                'suggestedRoutes' => $suggestedDeliveryRoutes,
            'liveDeliveries' => $liveDeliveries->map(fn($d) => $this->formatLiveDelivery($d)),
                'liveLocations' => $mapLocations,
                'deliveries' => $deliveries->items(),
                'pagination' => [
                    'total'        => $deliveries->total(),
                    'current_page' => $deliveries->currentPage(),
                    'last_page'    => $deliveries->lastPage(),
                    'per_page'     => $deliveries->perPage(),
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // BLADE VIEW RESPONSE
        // ------------------------------------------------------------------
        return view('admin.deliveries.index', compact(
            'deliveries',
            'archivedDeliveries',
            'allDrivers',
            'activeDrivers',
            'stats',
            'statusCounts',
            'liveDeliveries',
            'mapLocations',
            'assignableOrders',
            'driverBoard',
            'suggestedPickupBatches',
            'suggestedPickupBatchesPage',
            'suggestedDeliveryRoutes',
            'activePickupBatches',
            'activeDeliveryRoutes',
            'historyBatches',
            'historyRoutes',
            'search',
            'status',
            'driverId',
            'date',
            'sidebarCounts'
        ));
    }

    /**
     * Approve and create a pickup batch for one of the two drivers.
     */
    public function storeBatch(Request $request, DispatchService $dispatch)
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'required|uuid|exists:orders,id',
            'driver_id' => 'nullable|uuid|exists:drivers,id',
            'area' => 'nullable|string|max:100',
        ]);

        try {
            $batch = $dispatch->createPickupBatch($data, $request->user());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Pickup Batch {$batch->batch_number} created successfully.",
                    'batch' => $batch,
                ]);
            }

            return back()->with('success', "Pickup Batch {$batch->batch_number} created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Failed to create pickup batch.',
                ], 422);
            }
            throw $e;
        }
    }

    /**
     * Approve and create a delivery route for one of the two drivers.
     */
    public function storeRoute(Request $request, DispatchService $dispatch)
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'required|uuid|exists:orders,id',
            'driver_id' => 'nullable|uuid|exists:drivers,id',
            'area' => 'nullable|string|max:100',
        ]);

        try {
            $route = $dispatch->createDeliveryRoute($data, $request->user());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Delivery Route {$route->route_number} created successfully.",
                    'route' => $route,
                ]);
            }

            return back()->with('success', "Delivery Route {$route->route_number} created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Failed to create delivery route.',
                ], 422);
            }
            throw $e;
        }
    }

    /**
     * Update status or reassign pickup batch.
     */
    public function updateBatchStatus(Request $request, PickupBatch $batch)
    {
        $data = $request->validate([
            'status' => 'nullable|in:ready,in_progress,completed,cancelled',
            'driver_id' => 'nullable|uuid|exists:drivers,id',
        ]);

        if (!empty($data['status'])) {
            $batch->status = $data['status'];
            if ($data['status'] === 'in_progress' && !$batch->started_at) {
                $batch->started_at = now();
            } elseif ($data['status'] === 'completed') {
                $batch->completed_at = now();
            }
        }

        if (array_key_exists('driver_id', $data)) {
            $batch->driver_id = $data['driver_id'];
        }

        $batch->save();

        return back()->with('success', "Batch {$batch->batch_number} updated.");
    }

    /**
     * Update status or reassign delivery route.
     */
    public function updateRouteStatus(Request $request, DeliveryRoute $route)
    {
        $data = $request->validate([
            'status' => 'nullable|in:ready,in_progress,completed,cancelled',
            'driver_id' => 'nullable|uuid|exists:drivers,id',
        ]);

        if (!empty($data['status'])) {
            $route->status = $data['status'];
            if ($data['status'] === 'in_progress' && !$route->started_at) {
                $route->started_at = now();
            } elseif ($data['status'] === 'completed') {
                $route->completed_at = now();
            }
        }

        if (array_key_exists('driver_id', $data)) {
            $route->driver_id = $data['driver_id'];
        }

        $route->save();

        return back()->with('success', "Route {$route->route_number} updated.");
    }

    /**
     * Correct or update the destination address and validated GPS coordinates for a delivery order.
     */
    public function updateAddress(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'address_line' => 'required|string|max:500',
            'barangay' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:14.22,14.34',
            'longitude' => 'required|numeric|between:121.37,121.47',
        ]);

        $order = $delivery->order;
        if (!$order) {
            abort(404, 'Associated delivery order could not be found.');
        }

        $address = $order->address;
        if ($address) {
            $address->update([
                'address_line' => $data['address_line'],
                'barangay' => $data['barangay'] ?? $address->barangay,
                'city' => $data['city'] ?? $address->city ?? 'Santa Cruz',
                'province' => $data['province'] ?? $address->province ?? 'Laguna',
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
            ]);
        } else {
            $address = \App\Models\CustomerAddress::create([
                'customer_id' => $order->customer_id,
                'label' => 'Delivery Address',
                'address_line' => $data['address_line'],
                'barangay' => $data['barangay'] ?? null,
                'city' => $data['city'] ?? 'Santa Cruz',
                'province' => $data['province'] ?? 'Laguna',
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
                'is_default' => true,
            ]);
            $order->update(['address_id' => $address->id]);
        }

        app(\App\Services\RealtimeEventService::class)->emit(
            'delivery',
            $delivery->id,
            'address_updated',
            $delivery->status
        );

        $formattedAddress = collect([$address->address_line, $address->barangay, $address->city, $address->province])
            ->filter()
            ->implode(', ');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Delivery destination and coordinates updated successfully. Route updated.',
                'address' => [
                    'id' => $address->id,
                    'full' => $formattedAddress,
                    'latitude' => (float) $address->latitude,
                    'longitude' => (float) $address->longitude,
                ],
            ]);
        }

        return back()->with('success', 'Delivery destination coordinates corrected successfully.');
    }

    // =========================================================================
    // SHOW — Individual Delivery Detail (Blade + JSON)
    // =========================================================================

    public function show(Delivery $delivery, Request $request, SupabaseStorageService $storage)
    {
        $delivery->load([
            'order.customer.user',
            'order.address',
            'order.items.product',
            'order.containerIntake',
            'order.payment',
            'order.rfidTransactions' => fn ($q) => $q->with(['rfidTag.gallon', 'scannedBy'])->orderByDesc('created_at')->orderByDesc('id'),
            'order.statusHistory' => fn($q) => $q->orderBy('created_at', 'asc'),
            'driver.user',
            'statusHistory' => fn($q) => $q->orderBy('created_at', 'asc'),
        ]);

        // Resolve signed URLs from Supabase Storage (never expose raw URLs)
        $proofPhotoSignedUrl = null;
        if ($delivery->proof_photo_url) {
            try {
                $proofPhotoSignedUrl = $storage->signedUrl('delivery-proofs', $delivery->proof_photo_url);
            } catch (\Throwable) {
                // Gracefully degrade — show "No photo" state in the UI
            }
        }

        $signatureSignedUrl = null;
        if ($delivery->signature_url) {
            try {
                $signatureSignedUrl = $storage->signedUrl('delivery-proofs', $delivery->signature_url);
            } catch (\Throwable) {
                // Gracefully degrade
            }
        }

        // Sidebar counts for layout navigation badges
        $sidebarCounts = [
            'pendingOrders'       => Order::where('status', 'pending')->count(),
            'waitingInQueue'      => Order::where('status', 'waiting_in_queue')->count(),
            'activeDeliveries'    => Delivery::whereIn('status', [
                'assigned', 'en_route_for_pickup', 'picked_up', 'at_station', 'out_for_delivery',
            ])->count(),
            'lowStock'            => Inventory::whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
        ];


        // ------------------------------------------------------------------
        // JSON — used by DeliveriesDashboard.viewDelivery() in admin-deliveries.js
        // ------------------------------------------------------------------
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'delivery' => [
                    'id'           => $delivery->id,
                    'order_id'     => $delivery->order_id,
                    'order_number' => $delivery->order?->order_number
                                        ?? strtoupper(substr($delivery->order_id, 0, 8)),
                    'status'       => $delivery->status,
                    'status_label' => ucwords(str_replace('_', ' ', $delivery->status)),
                    'eta'          => $delivery->eta
                                        ? Carbon::parse($delivery->eta)->format('M d, Y g:i A')
                                        : 'Scheduled',
                    'created_at'   => $delivery->created_at
                                        ? Carbon::parse($delivery->created_at)->format('M d, Y g:i A')
                                        : 'N/A',
                    'delivered_at' => $delivery->delivered_at
                                        ? Carbon::parse($delivery->delivered_at)->format('M d, Y g:i A')
                                        : null,
                    'failure_reason' => $delivery->failure_reason,
                    'proof_status' => $delivery->proof_status ?? ($delivery->proof_photo_url ? 'provided' : 'not_provided'),

                    'customer' => [
                        'name'    => $delivery->order?->customer?->user?->name ?? 'Customer',
                        'phone'   => $delivery->order?->customer?->user?->phone ?? 'N/A',
                        'address' => $delivery->order?->address ? [
                            'line1'     => $delivery->order->address->address_line1,
                            'barangay'  => $delivery->order->address->barangay,
                            'city'      => $delivery->order->address->city,
                            'notes'     => $delivery->order->address->delivery_notes,
                            'full'      => trim(
                                $delivery->order->address->address_line1 . ', '
                                . $delivery->order->address->barangay . ', '
                                . $delivery->order->address->city
                            ),
                        ] : null,
                    ],

                    'driver' => $delivery->driver ? [
                        'id'      => $delivery->driver->id,
                        'name'    => $delivery->driver->user?->name    ?? 'Driver',
                        'phone'   => $delivery->driver->user?->phone   ?? 'N/A',
                        'plate'   => $delivery->driver->vehicle_plate   ?? 'N/A',
                        'license' => $delivery->driver->license_number  ?? 'N/A',
                        'status'  => $delivery->driver->user?->status   ?? 'active',
                    ] : null,

                    'payment' => $delivery->order?->payment ? [
                        'method' => $delivery->order->payment->method,
                        'status' => $delivery->order->payment->status,
                        'amount' => number_format((float) $delivery->order->payment->amount, 2),
                    ] : [
                        'method' => $delivery->order?->payment_method ?? 'cash',
                        'status' => 'pending',
                        'amount' => number_format((float) ($delivery->order?->total_amount ?? 0), 2),
                    ],

                    'containers' => [
                        'ownership' => $delivery->order?->container_ownership  ?? 'customer_owned',
                        'expected'  => $delivery->order?->expected_empty_gallons ?? 0,
                        'accepted'  => $delivery->order?->containerIntake?->accepted_count ?? 0,
                        'rejected'  => $delivery->order?->containerIntake?->rejected_count ?? 0,
                    ],

                    'proof_photo_url' => $proofPhotoSignedUrl,
                    'signature_url'   => $signatureSignedUrl,

                    'history' => $delivery->statusHistory->map(fn($h) => [
                        'id'           => $h->id,
                        'status'       => $h->status,
                        'status_label' => ucwords(str_replace('_', ' ', $h->status)),
                        'note'         => $h->note,
                        'time'         => $h->created_at
                                            ? Carbon::parse($h->created_at)->format('g:i A')
                                            : '',
                        'date'         => $h->created_at
                                            ? Carbon::parse($h->created_at)->format('M d, Y')
                                            : '',
                    ]),
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // BLADE VIEW — dedicated delivery detail page
        // ------------------------------------------------------------------
        return view('admin.deliveries.show', compact(
            'delivery',
            'proofPhotoSignedUrl',
            'signatureSignedUrl',
            'sidebarCounts'
        ));
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Format a Delivery model for the Live Deliveries panel JSON response.
     */
    private function formatLiveDelivery(Delivery $d): array
    {
        $address = $d->order?->address
            ? collect([
                $d->order->address->address_line,
                $d->order->address->barangay,
                $d->order->address->city,
                $d->order->address->province,
            ])->filter()->implode(', ')
            : 'Station Pickup';

        return [
            'id'           => $d->id,
            'order_id'     => $d->order_id,
            'order_number' => $d->order?->order_number ?? strtoupper(substr($d->order_id, 0, 8)),
            'customer_name'  => $d->order?->customer?->user?->name ?? 'Customer',
            'customer_phone' => $d->order?->customer?->user?->phone ?? 'N/A',
            'driver_name'    => $d->driver?->user?->name ?? 'Unassigned',
            'driver_plate'   => $d->driver?->vehicle_plate ?? 'N/A',
            'address'        => $address,
            'driver_id'      => $d->driver_id,
            'driver_latitude' => $d->driver_latitude,
            'driver_longitude' => $d->driver_longitude,
            'destination_latitude' => $d->order?->address?->latitude,
            'destination_longitude' => $d->order?->address?->longitude,
            'location_updated_at' => $d->location_updated_at?->toIso8601String(),
            'eta'            => $d->eta
                                    ? Carbon::parse($d->eta)->format('g:i A')
                                    : 'Scheduled',
            'status'       => $d->status,
            'status_label' => ucwords(str_replace('_', ' ', $d->status)),
            'view_url'     => route('admin.deliveries.show', $d->id),
        ];
    }
}
