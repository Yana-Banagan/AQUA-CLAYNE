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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        // 1. REALTIME KPI STATS (Consolidated & cached for 30s)
        // ------------------------------------------------------------------
        $rfidBundle = Cache::store('file')->remember('admin:rfid:stats', now()->addSeconds(30), function () use ($deviceService) {
            $tagStats = CustomerContainerRfidTag::selectRaw("
                COUNT(*) AS total_customer,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_customer,
                SUM(CASE WHEN status IN ('inactive', 'replaced') THEN 1 ELSE 0 END) AS inactive_customer
            ")->first();

            $totalStationTags = RfidTag::count();
            $totalCustomerTags = (int) ($tagStats?->total_customer ?? 0);
            $totalTags = $totalCustomerTags + $totalStationTags;
            $activeTags = (int) ($tagStats?->active_customer ?? 0) + $totalStationTags;
            $inactiveTags = (int) ($tagStats?->inactive_customer ?? 0);

            $containerCount = CustomerContainer::count();
            $deviceCount = RfidDevice::count();
            $transactionCount = RfidTransaction::count();

            $unassignedContainers = CustomerContainer::where('status', 'active')
                ->whereDoesntHave('activeTag')
                ->count();

            $devicesOnline = $deviceService->getOnlineDevicesCount();
            $devicesOffline = $deviceService->getOfflineDevicesCount();

            $orderStats = Order::selectRaw("
                SUM(CASE WHEN status IN ('received_at_station', 'refilling') THEN expected_empty_gallons ELSE 0 END) AS gallons_at_station,
                SUM(CASE WHEN status IN ('out_for_delivery', 'picked_up') THEN expected_empty_gallons ELSE 0 END) AS gallons_in_delivery
            ")->first();

            $stats = [
                'totalTags' => $totalTags,
                'activeTags' => $activeTags,
                'inactiveTags' => $inactiveTags,
                'unassignedContainers' => $unassignedContainers,
                'devicesOnline' => $devicesOnline,
                'devicesOffline' => $devicesOffline,
                'gallonsAtStation' => (int) ($orderStats?->gallons_at_station ?? 0),
                'gallonsInDelivery' => (int) ($orderStats?->gallons_in_delivery ?? 0),
            ];

            return compact('stats', 'containerCount', 'deviceCount', 'transactionCount');
        });

        $stats = $rfidBundle['stats'];
        $containerCount = $rfidBundle['containerCount'];
        $deviceCount = $rfidBundle['deviceCount'];
        $transactionCount = $rfidBundle['transactionCount'];

        // ------------------------------------------------------------------
        // 2. TAGS QUERY
        // ------------------------------------------------------------------
        $tagSearch = trim((string) $request->get('tag_search'));
        $tagStatus = $request->get('tag_status');

        $tagsQuery = CustomerContainerRfidTag::with([
            'container.customer.user',
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
        $devices = RfidDevice::latest('created_at')->get();

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
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'customer_container_id' => ['required', 'uuid', 'exists:customer_containers,id'],
            'station_device_id' => ['required', 'uuid', 'exists:rfid_devices,id'],
        ]);

        $session = $service->createSession(
            $data['customer_container_id'],
            $data['customer_id'],
            $data['station_device_id'],
            $request->user()
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
                'device_id' => $session->station_device_id,
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
        if ($session->status === 'pending' && $session->expires_at->isPast()) {
            $expired = RfidRegistrationSession::whereKey($session->id)
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);
            if ($expired) {
                $session->status = 'expired';
            } else {
                $session->refresh();
            }
        }
        $session->load([
            'container:id,customer_id,container_code,container_type',
            'container.customer:id,user_id',
            'container.customer.user:id,name,phone',
            'container.activeTag:id,customer_container_id,uid,status',
            'device:id,device_name,status,last_heartbeat_at',
        ]);

        $progress = $session->status;
        if ($session->status === 'pending') {
            if ($session->failure_code) {
                $progress = 'retry_scan';
            } elseif ($session->scan_received_at) {
                $progress = 'processing_scan';
            } elseif (!$session->command_sent_at) {
                $progress = 'waiting_for_device';
            } elseif (!$session->device?->isOnline()) {
                $progress = 'device_disconnected';
            } else {
                $progress = 'waiting_for_tag';
            }
        }

        return response()->json([
            'status' => $session->status,
            'progress' => $progress,
            'progress_message' => $progress === 'retry_scan'
                ? (($session->failure_message ?? 'RFID registration failed.') . ' RFID REGISTRATION FAILED. PLEASE TAP ANOTHER RFID TAG.')
                : match ($progress) {
                'waiting_for_device' => 'Waiting for the selected ESP32 scanner to connect.',
                'device_disconnected' => 'ESP32 connection lost. Waiting for the scanner to reconnect.',
                'waiting_for_tag' => 'WAITING FOR RFID TAG',
                'processing_scan' => 'RFID tag detected. Laravel is validating and saving the registration.',
                'failed' => $session->failure_message ?? 'RFID registration failed.',
                'expired' => 'Registration session expired.',
                'cancelled' => 'Registration session was cancelled.',
                'completed' => 'RFID tag successfully registered.',
                default => 'Validating RFID UID.',
            },
            'is_pending' => $session->isPending(),
            'scanned_uid' => $session->scanned_uid,
            'completed_at' => $session->completed_at?->toIso8601String(),
            'scan_received_at' => $session->scan_received_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'failure_code' => $session->failure_code,
            'failure_message' => $session->failure_message,
            'container' => [
                'id' => $session->container?->id,
                'code' => $session->container?->container_code,
                'type' => $session->container?->container_type,
                'customer_name' => $session->container?->customer?->user?->name,
                'active_tag_uid' => $session->container?->activeTag?->uid,
            ],
            'customer' => [
                'phone' => $session->container?->customer?->user?->phone,
            ],
            'device' => [
                'id' => $session->device?->id,
                'name' => $session->device?->device_name,
            ],
        ]);
    }

    /**
     * Return a privacy-filtered result for a normal verification scan.
     */
    public function scanResult(RfidTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->transaction_type === 'VERIFICATION', 404);

        $transaction->load('customerContainer.customer.user');
        $container = $transaction->customerContainer;
        $customerName = $container?->customer?->user?->name;
        $success = $transaction->scan_result === 'accepted' && $customerName !== null;
        $unregistered = $transaction->scan_result === 'rejected'
            && !$transaction->customer_container_rfid_tag_id
            && !$transaction->rfid_tag_id;

        $message = $success
            ? 'RFID tag verified successfully.'
            : ($unregistered
                ? 'RFID TAG NOT REGISTERED'
                : (!$customerName && $transaction->scan_result === 'accepted'
                    ? 'RFID tag is not linked to an active customer container.'
                    : ($transaction->message ?? 'RFID scan rejected.')));

        return response()->json([
            'scan_id' => $transaction->id,
            'status' => $success ? 'SUCCESS' : 'FAILED',
            'message' => $message,
            'customer_name' => $success ? $customerName : null,
            'gallon_count' => $success
                ? CustomerContainer::where('customer_id', $container->customer_id)
                    ->where('status', 'active')
                    ->count()
                : null,
            'scanned_at' => $transaction->scanned_at?->toIso8601String(),
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
     * List active customers and optionally filter by name, phone, or email.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q'));

        $customers = Customer::with('user')
            ->whereHas('user', function ($uq) {
                $uq->where('role', 'customer')
                    ->where('status', 'active');
            })
            ->when(!$request->boolean('include_all'), function ($customers) {
                    $customers->whereHas('containers', function ($cq) {
                        $cq->where('status', 'active')
                            ->whereDoesntHave('activeTag')
                            ->whereDoesntHave('registrationSessions', function ($sq) {
                                $sq->where('status', 'pending')
                                    ->where('expires_at', '>', now());
                            });
                    });
            })
            ->when($query !== '', function ($customers) use ($query) {
                $customers->whereHas('user', function ($uq) use ($query) {
                    $like = '%' . mb_strtolower($query) . '%';
                    $uq->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->orderBy('created_at')
            ->when($request->boolean('include_all'), fn($customers) => $customers->take(50))
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->user->name ?? 'Unknown',
                'phone' => $c->user->phone ?? 'No phone',
                'email' => $c->user->email ?? '',
            ])->values();

        return response()->json(['customers' => $customers]);
    }

    /**
     * List this customer's containers and indicate which can be registered.
     */
    public function customerContainers(Customer $customer): JsonResponse
    {
        $containers = CustomerContainer::query()
            ->where('customer_id', $customer->id)
            ->with('activeTag:id,customer_container_id,uid,status')
            ->withCount(['registrationSessions as pending_registration_count' => function ($query) {
                $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
            }])
            ->orderBy('created_at')
            ->get()
            ->map(function ($container) {
                $hasPendingSession = $container->pending_registration_count > 0;
                $eligible = $container->status === 'active'
                    && !$container->activeTag
                    && !$hasPendingSession;

                return [
                    'id' => $container->id,
                    'container_code' => $container->container_code,
                    'container_type' => $container->container_type,
                    'capacity' => $container->capacity,
                    'status' => $container->status,
                    'registration_status' => $container->activeTag
                        ? 'Already registered'
                        : ($hasPendingSession
                            ? 'Registration in progress'
                            : ($eligible ? 'Ready for RFID registration' : 'Container inactive')),
                    'active_tag_uid' => $container->activeTag?->uid,
                    'eligible' => $eligible,
                ];
            });

        return response()->json(['containers' => $containers]);
    }
}

