<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerContainer;
use App\Models\CustomerContainerRfidTag;
use App\Models\Order;
use App\Models\RfidDevice;
use App\Models\RfidRegistrationSession;
use App\Models\RfidTag;
use App\Models\RfidTransaction;
use App\Services\AdminSharedDataService;
use App\Services\RfidDeviceService;
use App\Services\RfidRegistrationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RfidController extends Controller
{
    /**
     * Display the RFID & Gallon Management hub with live stats, tag registry,
     * container tracking, device health, and complete audit history.
     */
    public function index(Request $request, RfidDeviceService $deviceService, AdminSharedDataService $sharedData)
    {
        $activeTab = $request->get('tab', 'tags');
        if (!in_array($activeTab, ['tags', 'containers', 'devices', 'transactions'], true)) {
            $activeTab = 'tags';
        }

        // ------------------------------------------------------------------
        // 1. REALTIME KPI STATS
        // ------------------------------------------------------------------
        $totalCustomerTags = CustomerContainerRfidTag::count();
        $totalStationTags = RfidTag::count();
        $totalTags = $totalCustomerTags + $totalStationTags;

        $activeTags = CustomerContainerRfidTag::where('status', 'active')->count() + $totalStationTags;
        $inactiveTags = CustomerContainerRfidTag::whereIn('status', ['inactive', 'replaced'])->count();
        $containerCount = CustomerContainer::count();
        $deviceCount = RfidDevice::count();
        $transactionCount = RfidTransaction::count();

        $unassignedContainers = CustomerContainer::where('status', 'active')
            ->whereDoesntHave('activeTag')
            ->count();

        $devicesOnline = $deviceService->getOnlineDevicesCount();
        $devicesOffline = $deviceService->getOfflineDevicesCount();

        // Gallons currently at station (refill queue + intake in progress)
        $gallonsAtStation = Order::whereIn('status', ['received_at_station', 'waiting_in_queue', 'refilling'])
            ->sum('expected_empty_gallons');

        // Gallons in delivery transit
        $gallonsInDelivery = Order::whereIn('status', ['out_for_delivery', 'picked_up'])
            ->sum('expected_empty_gallons');

        $stats = [
            'totalTags' => $totalTags,
            'activeTags' => $activeTags,
            'inactiveTags' => $inactiveTags,
            'unassignedContainers' => $unassignedContainers,
            'devicesOnline' => $devicesOnline,
            'devicesOffline' => $devicesOffline,
            'gallonsAtStation' => (int) $gallonsAtStation,
            'gallonsInDelivery' => (int) $gallonsInDelivery,
        ];

        // ------------------------------------------------------------------
        // 2. TAGS QUERY
        // ------------------------------------------------------------------
        $tagSearch = trim((string) $request->get('tag_search'));
        $tagStatus = $request->get('tag_status');

        $tagsQuery = CustomerContainerRfidTag::with([
            'container.customer.user',
            'transactions' => fn($q) => $q->latest('scanned_at')->limit(1),
        ])->latest('registered_at');

        if ($tagStatus && $tagStatus !== 'all') {
            $tagsQuery->where('status', $tagStatus);
        }

        if ($tagSearch) {
            $tagsQuery->where(function ($q) use ($tagSearch) {
                $q->where('uid', 'ilike', "%{$tagSearch}%")
                  ->orWhereHas('container', function ($cq) use ($tagSearch) {
                      $cq->where('container_code', 'ilike', "%{$tagSearch}%")
                         ->orWhereHas('customer.user', function ($uq) use ($tagSearch) {
                             $uq->where('name', 'ilike', "%{$tagSearch}%")
                                ->orWhere('phone', 'ilike', "%{$tagSearch}%");
                         });
                  });
            });
        }
        $tags = $activeTab === 'tags' ? $tagsQuery->paginate(10, ['*'], 'tags_page')->withQueryString() : collect();

        // ------------------------------------------------------------------
        // 3. CONTAINERS QUERY
        // ------------------------------------------------------------------
        $containerSearch = trim((string) $request->get('container_search'));
        $containerStatus = $request->get('container_status');

        $containersQuery = CustomerContainer::with([
            'customer.user',
            'activeTag',
        ])->latest('created_at');

        if ($containerStatus && $containerStatus !== 'all') {
            $containersQuery->where('status', $containerStatus);
        }

        if ($containerSearch) {
            $containersQuery->where(function ($q) use ($containerSearch) {
                $q->where('container_code', 'ilike', "%{$containerSearch}%")
                  ->orWhere('container_type', 'ilike', "%{$containerSearch}%")
                  ->orWhereHas('customer.user', function ($uq) use ($containerSearch) {
                      $uq->where('name', 'ilike', "%{$containerSearch}%")
                         ->orWhere('phone', 'ilike', "%{$containerSearch}%");
                  })
                  ->orWhereHas('activeTag', function ($tq) use ($containerSearch) {
                      $tq->where('uid', 'ilike', "%{$containerSearch}%");
                  });
            });
        }
        $containers = $activeTab === 'containers' ? $containersQuery->paginate(10, ['*'], 'containers_page')->withQueryString() : collect();

        // ------------------------------------------------------------------
        // 4. DEVICES QUERY
        // ------------------------------------------------------------------
        $devices = $activeTab === 'devices' ? RfidDevice::latest('created_at')->get() : collect();

        // ------------------------------------------------------------------
        // 5. TRANSACTIONS QUERY
        // ------------------------------------------------------------------
        $txSearch = trim((string) $request->get('tx_search'));
        $txType = $request->get('tx_type');
        $txResult = $request->get('tx_result');
        $txDateFrom = $request->get('tx_from');
        $txDateTo = $request->get('tx_to');

        $transactionsQuery = RfidTransaction::with([
            'customerContainer.customer.user',
            'customerContainerTag',
            'rfidTag.gallon',
            'device',
            'order',
            'scannedBy',
        ])->orderByDesc('scanned_at')->orderByDesc('id');

        if ($txType && $txType !== 'all') {
            $transactionsQuery->where('transaction_type', strtoupper($txType));
        }

        if ($txResult && $txResult !== 'all') {
            $transactionsQuery->where('scan_result', $txResult);
        }

        if ($txDateFrom) {
            $transactionsQuery->where('scanned_at', '>=', Carbon::parse($txDateFrom)->startOfDay());
        }
        if ($txDateTo) {
            $transactionsQuery->where('scanned_at', '<=', Carbon::parse($txDateTo)->endOfDay());
        }

        if ($txSearch) {
            $transactionsQuery->where(function ($q) use ($txSearch) {
                $q->where('message', 'ilike', "%{$txSearch}%")
                  ->orWhereHas('customerContainerTag', function ($tq) use ($txSearch) {
                      $tq->where('uid', 'ilike', "%{$txSearch}%");
                  })
                  ->orWhereHas('customerContainer', function ($cq) use ($txSearch) {
                      $cq->where('container_code', 'ilike', "%{$txSearch}%")
                         ->orWhereHas('customer.user', function ($uq) use ($txSearch) {
                             $uq->where('name', 'ilike', "%{$txSearch}%");
                         });
                  });
            });
        }
        $transactions = $activeTab === 'transactions' ? $transactionsQuery->paginate(15, ['*'], 'tx_page')->withQueryString() : collect();

        $sidebarCounts = $sharedData->getSidebarCounts($request->user());

        return view('admin.rfid.index', compact(
            'activeTab',
            'stats',
            'containerCount',
            'deviceCount',
            'transactionCount',
            'tags',
            'containers',
            'devices',
            'transactions',
            'tagSearch',
            'tagStatus',
            'containerSearch',
            'containerStatus',
            'txSearch',
            'txType',
            'txResult',
            'txDateFrom',
            'txDateTo',
            'sidebarCounts'
        ));
    }

    /**
     * Start a new temporary registration session for a customer container.
     */
    public function createSession(Request $request, RfidRegistrationService $service): JsonResponse
    {
        $data = $request->validate([
            'customer_container_id' => ['required', 'uuid'],
            'station_device_id' => ['nullable', 'uuid'],
        ]);

        $session = $service->createSession(
            $data['customer_container_id'],
            $request->user(),
            $data['station_device_id'] ?? null
        );

        $session->load(['container.customer.user', 'device']);

        return response()->json([
            'success' => true,
            'message' => 'Registration session started. Tap physical RFID tag on reader now.',
            'session' => [
                'id' => $session->id,
                'container_id' => $session->customer_container_id,
                'container_code' => $session->container?->container_code,
                'customer_name' => $session->container?->customer?->user?->name,
                'device_name' => $session->device?->device_name ?? 'Any Online Scanner',
                'expires_at' => $session->expires_at->toIso8601String(),
                'expires_in_seconds' => max(0, (int) now()->diffInSeconds($session->expires_at, false)),
            ],
        ]);
    }

    /**
     * Poll the status of an active registration session.
     */
    public function checkSessionStatus(RfidRegistrationSession $session): JsonResponse
    {
        $session->load(['container.customer.user', 'container.activeTag']);

        $isExpired = $session->expires_at->isPast();
        if ($session->status === 'pending' && $isExpired) {
            $session->status = 'expired';
            $session->save();
        }

        return response()->json([
            'status' => $session->status,
            'is_pending' => $session->isPending(),
            'scanned_uid' => $session->scanned_uid,
            'completed_at' => $session->completed_at?->toIso8601String(),
            'container' => [
                'code' => $session->container?->container_code,
                'customer_name' => $session->container?->customer?->user?->name,
                'active_tag_uid' => $session->container?->activeTag?->uid,
            ],
        ]);
    }

    /**
     * Cancel a pending registration session.
     */
    public function cancelSession(RfidRegistrationSession $session, RfidRegistrationService $service): JsonResponse
    {
        $service->cancelSession($session, auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Registration session cancelled.',
        ]);
    }

    /**
     * Store a new customer container.
     */
    public function storeContainer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'container_type' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Generate human-friendly sequential container code
        $count = CustomerContainer::where('customer_id', $data['customer_id'])->count() + 1;
        $customer = Customer::with('user')->findOrFail($data['customer_id']);
        $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $customer->user->name ?? 'CC'), 0, 3));
        $code = "AC-{$initials}-" . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

        // Ensure unique
        while (CustomerContainer::where('container_code', $code)->exists()) {
            $code = "AC-{$initials}-" . strtoupper(Str::random(4));
        }

        $container = CustomerContainer::create([
            'customer_id' => $data['customer_id'],
            'container_code' => $code,
            'container_type' => $data['container_type'],
            'capacity' => $data['capacity'] ?? 5.00,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer container created successfully.',
            'container' => [
                'id' => $container->id,
                'container_code' => $container->container_code,
                'container_type' => $container->container_type,
                'capacity' => $container->capacity,
            ],
        ]);
    }

    /**
     * Replace an existing RFID tag with a new physical tag.
     */
    public function replaceTag(Request $request, CustomerContainerRfidTag $tag, RfidRegistrationService $service): JsonResponse
    {
        $data = $request->validate([
            'new_uid' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $result = $service->replaceTag($tag->id, $data['new_uid'], $data['reason'], $request->user());

        return response()->json($result);
    }

    /**
     * Deactivate an RFID tag.
     */
    public function deactivateTag(Request $request, CustomerContainerRfidTag $tag, RfidRegistrationService $service): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $deactivatedTag = $service->deactivateTag($tag->id, $data['reason'], $request->user());

        return response()->json([
            'success' => true,
            'message' => "RFID Tag {$deactivatedTag->uid} deactivated successfully.",
        ]);
    }

    /**
     * Register a new ESP32 RFID hardware device.
     */
    public function storeDevice(Request $request, RfidDeviceService $service): JsonResponse
    {
        $data = $request->validate([
            'device_code' => ['required', 'string', 'max:50', 'unique:rfid_devices,device_code'],
            'device_name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:150'],
            'firmware_version' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $service->registerDevice($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'RFID device registered successfully. Copy the device authentication token now; it will not be shown again.',
            'device' => $result['device'],
            'plain_token' => $result['plain_token'],
        ]);
    }

    /**
     * Rotate the security token for an existing RFID hardware device.
     */
    public function rotateDeviceToken(Request $request, RfidDevice $device, RfidDeviceService $service): JsonResponse
    {
        $newToken = $service->rotateToken($device, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Security token rotated for device {$device->device_code}. Update the ESP32 configuration with the new token.",
            'plain_token' => $newToken,
        ]);
    }

    /**
     * Toggle active/maintenance status for an RFID device.
     */
    public function toggleDeviceStatus(Request $request, RfidDevice $device): JsonResponse
    {
        $newStatus = $device->status === 'online' ? 'maintenance' : 'online';
        $device->status = $newStatus;
        $device->save();

        return response()->json([
            'success' => true,
            'message' => "Device status updated to {$newStatus}.",
            'status' => $newStatus,
        ]);
    }

    /**
     * Autocomplete endpoint to search active customers.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q'));
        if (strlen($query) < 2) {
            return response()->json(['customers' => []]);
        }

        $customers = Customer::with('user')
            ->whereHas('user', function ($uq) use ($query) {
                $uq->where('name', 'ilike', "%{$query}%")
                   ->orWhere('phone', 'ilike', "%{$query}%")
                   ->orWhere('email', 'ilike', "%{$query}%");
            })
            ->take(10)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->user->name ?? 'Unknown',
                'phone' => $c->user->phone ?? 'No phone',
                'email' => $c->user->email ?? '',
            ]);

        return response()->json(['customers' => $customers]);
    }

    /**
     * Get containers belonging to a customer (with their active RFID tag info).
     */
    public function customerContainers(Customer $customer): JsonResponse
    {
        $containers = CustomerContainer::with('activeTag')
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'container_code' => $c->container_code,
                'container_type' => $c->container_type,
                'capacity' => $c->capacity,
                'has_active_tag' => (bool) $c->activeTag,
                'active_tag_uid' => $c->activeTag?->uid,
            ]);

        return response()->json(['containers' => $containers]);
    }
}

