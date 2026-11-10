<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use App\Models\Order;
use App\Models\PickupBatch;
use App\Models\PickupBatchItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchService
{
    public const DEFAULT_VEHICLE_CAPACITY = 30; // Gallons per vehicle batch/route

    /**
     * Get operational status and workload summary for the station's two active drivers.
     */
    public function getDriverStatuses(bool $forceFresh = false): array
    {
        if ($forceFresh) {
            \Illuminate\Support\Facades\Cache::forget('dispatch:driver-statuses');
        }

        return \Illuminate\Support\Facades\Cache::remember('dispatch:driver-statuses', now()->addSeconds(15), function () {
            $drivers = Driver::with([
                'user:id,name,phone,status,avatar_path',
                'pickupBatches' => function ($q) {
                    $q->whereIn('status', ['ready', 'in_progress'])->with('items.order:id,order_number');
                },
                'deliveryRoutes' => function ($q) {
                    $q->whereIn('status', ['ready', 'in_progress'])->with('stops.order:id,order_number');
                }
            ])->get();

            $driverBoard = [];

            foreach ($drivers as $driver) {
                $activeBatch = $driver->pickupBatches->first();
                $activeRoute = $driver->deliveryRoutes->first();

                $status = 'AVAILABLE';
                $currentTaskType = null;
                $currentTaskCode = null;
                $currentArea = null;
                $stopsCount = 0;
                $gallonsCount = 0;
                $completedStops = 0;

                if ($driver->user?->status !== 'active') {
                    $status = 'OFFLINE';
                } elseif ($activeRoute) {
                    $status = $activeRoute->status === 'in_progress' ? 'ON_DELIVERY' : 'ASSIGNED';
                    $currentTaskType = 'route';
                    $currentTaskCode = $activeRoute->route_number;
                    $currentArea = $activeRoute->area;
                    $stopsCount = $activeRoute->stops->count();
                    $completedStops = $activeRoute->stops->whereIn('status', ['delivered', 'failed'])->count();
                    $gallonsCount = $activeRoute->total_gallons;
                } elseif ($activeBatch) {
                    $status = $activeBatch->status === 'in_progress' ? 'ON_PICKUP' : 'ASSIGNED';
                    $currentTaskType = 'pickup';
                    $currentTaskCode = $activeBatch->batch_number;
                    $currentArea = $activeBatch->area;
                    $stopsCount = $activeBatch->items->count();
                    $completedStops = $activeBatch->items->whereIn('status', ['picked_up', 'failed'])->count();
                    $gallonsCount = $activeBatch->total_expected_gallons;
                }

                $driverBoard[] = [
                    'id' => $driver->id,
                    'name' => $driver->user?->name ?? 'Driver',
                    'avatar_url' => $driver->user?->avatar_url,
                    'phone' => $driver->user?->phone ?? 'N/A',
                    'vehicle_plate' => $driver->vehicle_plate ?? 'N/A',
                    'license_number' => $driver->license_number ?? 'N/A',
                    'status' => $status,
                    'current_task_type' => $currentTaskType,
                    'current_task_code' => $currentTaskCode,
                    'area' => $currentArea,
                    'stops_count' => $stopsCount,
                    'completed_stops' => $completedStops,
                    'gallons_count' => $gallonsCount,
                    'active_batch' => $activeBatch,
                    'active_route' => $activeRoute,
                ];
            }

            return $driverBoard;
        });
    }

    /**
     * Suggest pickup batches from confirmed orders awaiting gallon collection.
     * Groups orders by geographic area and adheres strictly to vehicle capacity.
     */
    public function suggestPickupBatches(?\Illuminate\Support\Collection $drivers = null): array
    {
        // Unassigned confirmed orders not in any active pickup batch
        $assignedOrderIds = PickupBatchItem::whereHas('batch', function ($q) {
            $q->whereIn('status', ['ready', 'in_progress']);
        })->pluck('order_id')->all();

        $confirmedOrders = Order::query()
            ->where('status', 'confirmed')
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereNotIn('id', $assignedOrderIds)
            ->with(['customer.user', 'address'])
            ->orderBy('created_at')
            ->get();

        if ($confirmedOrders->isEmpty()) {
            return [];
        }

        // Available drivers (reused if passed to avoid duplicate queries)
        if ($drivers === null) {
            $drivers = Driver::whereHas('user', fn($q) => $q->where('status', 'active'))->with('user')->get();
        }
        $driverIndex = 0;

        // Group orders by area
        $groupedByArea = [];
        foreach ($confirmedOrders as $order) {
            $area = $this->extractArea($order->address?->address_line ?? $order->address?->notes ?? 'Lipa');
            $groupedByArea[$area][] = $order;
        }

        $suggestedBatches = [];
        $batchSeq = 1;

        foreach ($groupedByArea as $area => $orders) {
            $currentBatchOrders = [];
            $currentGallons = 0;

            foreach ($orders as $order) {
                $orderGallons = $order->expected_empty_gallons ?: 1;

                if (($currentGallons + $orderGallons) > self::DEFAULT_VEHICLE_CAPACITY && !empty($currentBatchOrders)) {
                    // Seal current suggested batch
                    $assignedDriver = $drivers->isNotEmpty() ? $drivers[$driverIndex % $drivers->count()] : null;
                    $suggestedBatches[] = $this->formatSuggestedBatch($area, $batchSeq++, $currentBatchOrders, $currentGallons, $assignedDriver);
                    if ($drivers->isNotEmpty()) {
                        $driverIndex++;
                    }

                    $currentBatchOrders = [];
                    $currentGallons = 0;
                }

                $currentBatchOrders[] = $order;
                $currentGallons += $orderGallons;
            }

            if (!empty($currentBatchOrders)) {
                $assignedDriver = $drivers->isNotEmpty() ? $drivers[$driverIndex % $drivers->count()] : null;
                $suggestedBatches[] = $this->formatSuggestedBatch($area, $batchSeq++, $currentBatchOrders, $currentGallons, $assignedDriver);
                if ($drivers->isNotEmpty()) {
                    $driverIndex++;
                }
            }
        }

        return $suggestedBatches;
    }

    /**
     * Create an approved pickup batch with sequenced customer stops.
     */
    public function createPickupBatch(array $data, User $actor): PickupBatch
    {
        return DB::transaction(function () use ($data, $actor) {
            $orderIds = $data['order_ids'] ?? [];
            if (empty($orderIds)) {
                throw ValidationException::withMessages(['order_ids' => ['At least one customer order is required for a pickup batch.']]);
            }

            $driverId = $data['driver_id'] ?? null;
            $driver = $driverId ? Driver::findOrFail($driverId) : null;

            // Check if orders are still available
            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            if ($orders->count() !== count($orderIds)) {
                throw ValidationException::withMessages(['order_ids' => ['One or more selected orders could not be found.']]);
            }

            $totalGallons = 0;
            foreach ($orders as $order) {
                if ($order->isStationPickup()) {
                    throw ValidationException::withMessages(['order_ids' => ["Order #{$order->order_number} is a station pickup order and cannot be added to a driver pickup batch."]]);
                }
                if ($order->status !== 'confirmed') {
                    throw ValidationException::withMessages(['order_ids' => ["Order #{$order->order_number} is no longer awaiting pickup."]]);
                }
                $totalGallons += ($order->expected_empty_gallons ?: 1);
            }

            if ($totalGallons > self::DEFAULT_VEHICLE_CAPACITY) {
                throw ValidationException::withMessages(['order_ids' => ["Batch total ({$totalGallons} gallons) exceeds vehicle capacity of " . self::DEFAULT_VEHICLE_CAPACITY . " gallons."]]);
            }

            $datePart = now()->format('Ymd');
            $existingToday = PickupBatch::whereDate('created_at', today())->count();
            $batchNumber = sprintf('PB-%s-%03d', $datePart, $existingToday + 1);

            $area = $data['area'] ?? $this->extractArea($orders->first()->address?->address_line ?? 'General Area');

            $batch = PickupBatch::create([
                'batch_number' => $batchNumber,
                'driver_id' => $driver?->id,
                'area' => $area,
                'status' => 'ready',
                'total_expected_gallons' => $totalGallons,
                'created_by' => $actor->id,
            ]);

            $sequence = 1;
            foreach ($orders as $order) {
                PickupBatchItem::create([
                    'pickup_batch_id' => $batch->id,
                    'order_id' => $order->id,
                    'stop_sequence' => $sequence++,
                    'expected_gallons' => $order->expected_empty_gallons ?: 1,
                    'status' => 'pending',
                ]);

                // Transition order to assigned
                app(OrderWorkflowService::class)->transition($order, 'assigned', $actor, "Assigned to pickup batch {$batchNumber}");

                // Ensure delivery record exists
                Delivery::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $driver?->id,
                        'status' => 'assigned',
                    ]
                );
            }

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'pickup_batch.created',
                'table_name' => 'pickup_batches',
                'record_id' => $batch->id,
            ]);

            \Illuminate\Support\Facades\Cache::forget('dispatch:driver-statuses');
            app(RealtimeEventService::class)->emit('pickup_batch', $batch->id, 'created', 'ready');

            if ($driver?->user) {
                app(NotificationService::class)->sendToUser(
                    $driver->user,
                    'New Pickup Batch Assigned',
                    "Pickup batch {$batchNumber} ({$totalGallons} gallons, {$orders->count()} stops) assigned to you.",
                    ['entity_type' => 'pickup_batch', 'entity_id' => $batch->id]
                );
            }

            return $batch->load(['items.order.customer.user', 'items.order.address', 'driver.user']);
        });
    }

    /**
     * Suggest delivery routes for orders ready for delivery.
     * Groups orders by geographic area and vehicle capacity.
     */
    public function suggestDeliveryRoutes(?\Illuminate\Support\Collection $drivers = null): array
    {
        $assignedOrderIds = DeliveryStop::whereHas('route', function ($q) {
            $q->whereIn('status', ['ready', 'in_progress']);
        })->pluck('order_id')->all();

        $readyOrders = Order::query()
            ->where('status', 'ready_for_delivery')
            ->where('delivery_method', '!=', 'station_pickup')
            ->whereNotIn('id', $assignedOrderIds)
            ->with(['customer.user', 'address', 'containerIntake', 'payment'])
            ->orderBy('created_at')
            ->get();

        if ($readyOrders->isEmpty()) {
            return [];
        }

        // Available drivers (reused if passed to avoid duplicate queries)
        if ($drivers === null) {
            $drivers = Driver::whereHas('user', fn($q) => $q->where('status', 'active'))->with('user')->get();
        }
        $driverIndex = 0;

        $groupedByArea = [];
        foreach ($readyOrders as $order) {
            $area = $this->extractArea($order->address?->address_line ?? $order->address?->notes ?? 'Lipa');
            $groupedByArea[$area][] = $order;
        }

        $suggestedRoutes = [];
        $routeSeq = 1;

        foreach ($groupedByArea as $area => $orders) {
            $currentRouteOrders = [];
            $currentGallons = 0;

            foreach ($orders as $order) {
                $orderGallons = $order->containerIntake?->accepted_count ?? $order->expected_empty_gallons ?? 1;

                if (($currentGallons + $orderGallons) > self::DEFAULT_VEHICLE_CAPACITY && !empty($currentRouteOrders)) {
                    $assignedDriver = $drivers->isNotEmpty() ? $drivers[$driverIndex % $drivers->count()] : null;
                    $suggestedRoutes[] = $this->formatSuggestedRoute($area, $routeSeq++, $currentRouteOrders, $currentGallons, $assignedDriver);
                    if ($drivers->isNotEmpty()) {
                        $driverIndex++;
                    }

                    $currentRouteOrders = [];
                    $currentGallons = 0;
                }

                $currentRouteOrders[] = $order;
                $currentGallons += $orderGallons;
            }

            if (!empty($currentRouteOrders)) {
                $assignedDriver = $drivers->isNotEmpty() ? $drivers[$driverIndex % $drivers->count()] : null;
                $suggestedRoutes[] = $this->formatSuggestedRoute($area, $routeSeq++, $currentRouteOrders, $currentGallons, $assignedDriver);
                if ($drivers->isNotEmpty()) {
                    $driverIndex++;
                }
            }
        }

        return $suggestedRoutes;
    }

    /**
     * Create an approved delivery route with sequenced customer stops.
     */
    public function createDeliveryRoute(array $data, User $actor): DeliveryRoute
    {
        return DB::transaction(function () use ($data, $actor) {
            $orderIds = $data['order_ids'] ?? [];
            if (empty($orderIds)) {
                throw ValidationException::withMessages(['order_ids' => ['At least one order is required for a delivery route.']]);
            }

            $driverId = $data['driver_id'] ?? null;
            $driver = $driverId ? Driver::findOrFail($driverId) : null;

            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            if ($orders->count() !== count($orderIds)) {
                throw ValidationException::withMessages(['order_ids' => ['One or more selected orders could not be found.']]);
            }

            $totalGallons = 0;
            foreach ($orders as $order) {
                if ($order->isStationPickup()) {
                    throw ValidationException::withMessages(['order_ids' => ["Order #{$order->order_number} is a station pickup order and cannot be added to a driver delivery route."]]);
                }
                if ($order->status !== 'ready_for_delivery') {
                    throw ValidationException::withMessages(['order_ids' => ["Order #{$order->order_number} is not ready for delivery."]]);
                }
                $totalGallons += ($order->containerIntake?->accepted_count ?? $order->expected_empty_gallons ?? 1);
            }

            if ($totalGallons > self::DEFAULT_VEHICLE_CAPACITY) {
                throw ValidationException::withMessages(['order_ids' => ["Route total ({$totalGallons} gallons) exceeds vehicle capacity of " . self::DEFAULT_VEHICLE_CAPACITY . " gallons."]]);
            }

            $datePart = now()->format('Ymd');
            $existingToday = DeliveryRoute::whereDate('created_at', today())->count();
            $routeNumber = sprintf('DR-%s-%03d', $datePart, $existingToday + 1);

            $area = $data['area'] ?? $this->extractArea($orders->first()->address?->address_line ?? 'General Area');

            $route = DeliveryRoute::create([
                'route_number' => $routeNumber,
                'driver_id' => $driver?->id,
                'area' => $area,
                'status' => 'ready',
                'total_gallons' => $totalGallons,
                'created_by' => $actor->id,
            ]);

            $sequence = 1;
            foreach ($orders as $order) {
                $delivery = Delivery::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $driver?->id,
                        'status' => 'ready_for_delivery',
                    ]
                );

                if ($driver) {
                    $delivery->update(['driver_id' => $driver->id]);
                }

                DeliveryStop::create([
                    'delivery_route_id' => $route->id,
                    'order_id' => $order->id,
                    'delivery_id' => $delivery->id,
                    'stop_sequence' => $sequence++,
                    'status' => 'assigned',
                ]);
            }

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'delivery_route.created',
                'table_name' => 'delivery_routes',
                'record_id' => $route->id,
            ]);

            \Illuminate\Support\Facades\Cache::forget('dispatch:driver-statuses');
            app(RealtimeEventService::class)->emit('delivery_route', $route->id, 'created', 'ready');

            if ($driver?->user) {
                app(NotificationService::class)->sendToUser(
                    $driver->user,
                    'New Delivery Route Assigned',
                    "Delivery route {$routeNumber} ({$totalGallons} gallons, {$orders->count()} stops) assigned to you.",
                    ['entity_type' => 'delivery_route', 'entity_id' => $route->id]
                );
            }

            return $route->load(['stops.order.customer.user', 'stops.order.address', 'stops.order.payment', 'driver.user']);
        });
    }

    private function extractArea(string $address): string
    {
        $addressLower = strtolower($address);
        if (str_contains($addressLower, 'lipa')) return 'Lipa';
        if (str_contains($addressLower, 'malvar')) return 'Malvar';
        if (str_contains($addressLower, 'tanauan')) return 'Tanauan';
        if (str_contains($addressLower, 'batangas')) return 'Batangas City';
        if (str_contains($addressLower, 'san jose')) return 'San Jose';
        if (str_contains($addressLower, 'sto tomas') || str_contains($addressLower, 'santo tomas')) return 'Santo Tomas';

        return 'Central Area';
    }

    private function formatSuggestedBatch(string $area, int $seq, array $orders, int $totalGallons, ?Driver $driver): array
    {
        return [
            'suggested_code' => sprintf('PB-%s-%03d', now()->format('md'), $seq),
            'area' => $area,
            'total_gallons' => $totalGallons,
            'stops_count' => count($orders),
            'suggested_driver_id' => $driver?->id,
            'suggested_driver_name' => $driver?->user?->name ?? 'Unassigned',
            'orders' => array_map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer_name' => $o->customer?->user?->name ?? 'Customer',
                'phone' => $o->customer?->user?->phone ?? 'N/A',
                'address' => $o->address?->address_line ?? 'N/A',
                'expected_gallons' => $o->expected_empty_gallons ?: 1,
            ], $orders),
        ];
    }

    private function formatSuggestedRoute(string $area, int $seq, array $orders, int $totalGallons, ?Driver $driver): array
    {
        return [
            'suggested_code' => sprintf('DR-%s-%03d', now()->format('md'), $seq),
            'area' => $area,
            'total_gallons' => $totalGallons,
            'stops_count' => count($orders),
            'suggested_driver_id' => $driver?->id,
            'suggested_driver_name' => $driver?->user?->name ?? 'Unassigned',
            'orders' => array_map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer_name' => $o->customer?->user?->name ?? 'Customer',
                'phone' => $o->customer?->user?->phone ?? 'N/A',
                'address' => $o->address?->address_line ?? 'N/A',
                'gallons' => $o->containerIntake?->accepted_count ?? $o->expected_empty_gallons ?? 1,
                'payment_status' => $o->payment?->status ?? 'pending',
                'payment_method' => $o->payment_method ?? 'cash',
            ], $orders),
        ];
    }
}
