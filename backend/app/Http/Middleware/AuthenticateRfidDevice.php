<?php

namespace App\Http\Middleware;

use App\Models\RfidDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $request->attributes->set('rfid_device', $device);
        $startedAt = microtime(true);
        $response = $next($request);

        $body = json_decode($response->getContent(), true);
        Log::info('RFID device request completed', [
            'endpoint' => '/' . ltrim($request->path(), '/'),
            'device_id' => $device->id,
            'scan_id' => $request->input('scan_id'),
            'session_id' => $request->input('session_id'),
            'uid' => is_string($request->input('uid'))
                ? substr($request->input('uid'), 0, 64)
                : null,
            'http_status' => $response->getStatusCode(),
            'error' => is_array($body) ? ($body['error'] ?? null) : null,
            'error_message' => is_array($body) && is_string($body['message'] ?? null)
                ? substr($body['message'], 0, 200)
                : null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}

