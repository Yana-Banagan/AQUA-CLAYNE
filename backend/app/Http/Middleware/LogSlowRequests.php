<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function ($query) use (&$queryCount, &$queryTimeMs) {
            $queryCount++;
            $queryTimeMs += (float) $query->time;
        });

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->logIfSlow($request, $startedAt, null, $queryCount, $queryTimeMs);
            throw $exception;
        }

        $this->logIfSlow($request, $startedAt, $response->getStatusCode(), $queryCount, $queryTimeMs);
        return $response;
    }

    private function logIfSlow(Request $request, float $startedAt, ?int $status, int $queryCount, float $queryTimeMs): void
    {
        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        $thresholdMs = max(0, (float) config('app.slow_request_ms', 2500));

        if ($elapsedMs >= $thresholdMs) {
            Log::warning('Slow HTTP request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'time_ms' => round($elapsedMs, 1),
                'query_count' => $queryCount,
                'query_time_ms' => round($queryTimeMs, 1),
                'status' => $status,
            ]);
        }
    }
}
