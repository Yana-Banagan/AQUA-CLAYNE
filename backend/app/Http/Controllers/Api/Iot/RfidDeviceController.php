<?php

namespace App\Http\Controllers\Api\Iot;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidRegistrationSession;
use App\Services\RfidRegistrationService;
use App\Services\RealtimeEventService;
use App\Services\RfidTransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RfidDeviceController extends Controller
{
    /**
     * Check if there is an active pending registration session assigned to this device.
     */
    public function activeRegistrationSession(Request $request): JsonResponse
    {
        /** @var RfidDevice $device */
        $device = $request->attributes->get('rfid_device');

        $session = RfidRegistrationSession::query()
            ->select([
                'id',
                'customer_container_id',
                'station_device_id',
                'status',
                'expires_at',
                'created_at',
                'command_sent_at',
            ])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where('station_device_id', $device->id)
            ->latest('created_at')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => true,
                'has_session' => false,
                'message' => 'No pending registration session for this scanner.',
            ]);
        }

        if (!$session->command_sent_at) {
            $sentAt = now();
            RfidRegistrationSession::whereKey($session->id)
                ->whereNull('command_sent_at')
                ->update(['command_sent_at' => $sentAt]);
            $session->command_sent_at = $sentAt;
        }

        $session->load([
            'container:id,customer_id,container_code,container_type',
            'container.customer:id,user_id',
            'container.customer.user:id,name',
        ]);

        $container = $session->container;
        $customerUser = $container?->customer?->user;

        return response()->json([
            'success' => true,
            'has_session' => true,
            'session' => [
                'session_id' => $session->id,
                'device_id' => $device->id,
                'container_id' => $container?->id,
                'container_code' => $container?->container_code,
                'container_type' => $container?->container_type,
                'customer_name' => $customerUser?->name ?? 'Customer',
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

        $validator = Validator::make($request->all(), [
            'device_id' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'uid' => ['required', 'string', 'max:64'],
            'scan_id' => ['nullable', 'uuid'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'The RFID registration request is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();

        if ($data['device_id'] !== $device->id) {
            return response()->json([
                'success' => false,
                'error' => 'DEVICE_MISMATCH',
                'message' => 'The submitted device ID does not match the authenticated RFID scanner.',
            ], 403);
        }

        RfidRegistrationSession::whereKey($data['session_id'])
            ->where('station_device_id', $device->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->update([
                'scan_received_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);

        try {
            $result = $registrationService->completeRegistration(
                $device,
                $data['session_id'],
                $data['uid'],
                $data['scan_id'] ?? null
            );
        } catch (QueryException $exception) {
            report($exception);
            $message = 'Database error while registering this RFID tag. Please tap another tag or retry.';
            RfidRegistrationSession::whereKey($data['session_id'])
                ->where('station_device_id', $device->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->update([
                    'scan_received_at' => now(),
                    'failure_code' => 'DATABASE_ERROR',
                    'failure_message' => $message,
                ]);

            return response()->json([
                'success' => false,
                'error' => 'DATABASE_ERROR',
                'message' => $message,
            ], 500);
        }

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
                'id' => $device->id,
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

        $validator = Validator::make($request->all(), [
            'uid' => ['required', 'string', 'max:64'],
            'scan_type' => ['required', 'string', 'in:STATION_INTAKE,REFILL_START,REFILL_COMPLETE,DRIVER_PICKUP,DELIVERY,VERIFICATION'],
            'order_id' => ['nullable', 'uuid'],
            'scan_id' => ['nullable', 'uuid'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'The RFID scan request is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();

        if (RfidRegistrationSession::where('station_device_id', $device->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'REGISTRATION_SESSION_ACTIVE',
                'message' => 'A registration session is active for this scanner. Submit this tag to the registration-scan endpoint.',
            ], 409);
        }

        $result = $transactionService->validateAndRecordScan(
            $device,
            $data['uid'],
            $data['scan_type'],
            $data['order_id'] ?? null,
            null,
            $data['scan_id'] ?? null
        );

        if (!empty($result['transaction_id'])) {
            app(RealtimeEventService::class)->emit(
                'rfid',
                $result['transaction_id'],
                'RFID_SCAN_RESULT',
                $result['success'] ? 'accepted' : 'rejected'
            );
        }

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}

