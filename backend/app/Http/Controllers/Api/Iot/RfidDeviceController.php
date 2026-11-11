<?php

namespace App\Http\Controllers\Api\Iot;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidRegistrationSession;
use App\Services\RfidRegistrationService;
use App\Services\RfidTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfidDeviceController extends Controller
{
    /**
     * Check if there is an active pending registration session assigned to this device.
     */
    public function activeRegistrationSession(Request $request): JsonResponse
    {
        /** @var RfidDevice $device */
        $device = $request->attributes->get('rfid_device');

        $session = RfidRegistrationSession::with(['container.customer.user'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($device) {
                $q->where('station_device_id', $device->id)
                  ->orWhereNull('station_device_id');
            })
            ->latest('created_at')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => true,
                'has_session' => false,
                'message' => 'No pending registration session for this scanner.',
            ]);
        }

        $container = $session->container;
        $customerUser = $container?->customer?->user;

        return response()->json([
            'success' => true,
            'has_session' => true,
            'session' => [
                'session_id' => $session->id,
                'container_id' => $container?->id,
                'container_code' => $container?->container_code,
                'container_type' => $container?->container_type,
                'customer_name' => $customerUser?->name ?? 'Customer',
                'customer_phone' => $customerUser?->phone ?? '',
                'expires_at' => $session->expires_at->toIso8601String(),
                'expires_in_seconds' => max(0, (int) now()->diffInSeconds($session->expires_at, false)),
            ],
        ]);
    }

    /**
     * Submit an RFID tag scan to complete an active registration session.
     */
    public function registrationScan(Request $request, RfidRegistrationService $registrationService): JsonResponse
    {
        /** @var RfidDevice $device */
        $device = $request->attributes->get('rfid_device');

        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'uid' => ['required', 'string', 'max:64'],
        ]);

        $result = $registrationService->completeRegistration(
            $device,
            $data['session_id'],
            $data['uid']
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * Device periodic heartbeat.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var RfidDevice $device */
        $device = $request->attributes->get('rfid_device');

        $firmwareVersion = $request->input('firmware_version');
        $device->markHeartbeat($request->ip(), $firmwareVersion);

        return response()->json([
            'success' => true,
            'device' => [
                'code' => $device->device_code,
                'name' => $device->device_name,
                'status' => $device->status,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * General operational RFID scan (Station intake, verification, etc.)
     */
    public function scan(Request $request, RfidTransactionService $transactionService): JsonResponse
    {
        /** @var RfidDevice $device */
        $device = $request->attributes->get('rfid_device');

        $data = $request->validate([
            'uid' => ['required', 'string', 'max:64'],
            'scan_type' => ['required', 'string', 'in:STATION_INTAKE,REFILL_START,REFILL_COMPLETE,DRIVER_PICKUP,DELIVERY,VERIFICATION'],
            'order_id' => ['nullable', 'uuid'],
        ]);

        $result = $transactionService->validateAndRecordScan(
            $device,
            $data['uid'],
            $data['scan_type'],
            $data['order_id'] ?? null
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}

