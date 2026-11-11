<?php

namespace App\Http\Middleware;

use App\Models\RfidDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRfidDevice
{
    /**
     * Handle an incoming RFID device request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->header('X-Device-Token');

        if (!$rawToken && $request->bearerToken()) {
            $rawToken = $request->bearerToken();
        }

        if (empty($rawToken)) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Missing X-Device-Token authentication header.',
            ], 401);
        }

        $tokenHash = hash('sha256', trim($rawToken));

        /** @var RfidDevice|null $device */
        $device = RfidDevice::where('device_token_hash', $tokenHash)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_CREDENTIAL',
                'message' => 'Invalid RFID device token.',
            ], 401);
        }

        if (in_array($device->status, ['maintenance', 'offline'], true) && !$request->is('*/heartbeat')) {
            return response()->json([
                'success' => false,
                'error' => 'DEVICE_INACTIVE',
                'message' => "RFID device is currently in {$device->status} state.",
            ], 403);
        }

        // Keep heartbeat fresh on authenticated request
        $device->markHeartbeat($request->ip());

        // Attach device instance to request attributes
        $request->attributes->set('rfid_device', $device);

        return $next($request);
    }
}

