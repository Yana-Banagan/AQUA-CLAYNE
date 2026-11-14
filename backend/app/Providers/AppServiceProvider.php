<?php

namespace App\Providers;

use App\Database\Connectors\TimedPostgresConnector;
use App\Models\Notification;
use App\Models\ChatMessage;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel's default PostgreSQL connector has no connection timeout.
        // Bind a connector that places a short timeout in the libpq DSN.
        $this->app->bind('db.connector.pgsql', fn () => new TimedPostgresConnector());

        // Centralized shared administrative data service (memoized per-request).
        $this->app->scoped(\App\Services\AdminSharedDataService::class, fn () => new \App\Services\AdminSharedDataService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS URLs in production or behind an SSL proxy (like Railway).
        if (app()->environment('production') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }

        $slowQueryThresholdMs = max(0, (float) config('database.slow_query_ms', 500));
        DB::listen(function (QueryExecuted $query) use ($slowQueryThresholdMs) {
            if ($query->time < $slowQueryThresholdMs) {
                return;
            }

            // Bindings can contain customer data, so log only the parameterized SQL.
            Log::warning('Slow database query', [
                'connection' => $query->connectionName,
                'time_ms' => round($query->time, 1),
                'sql' => mb_substr($query->sql, 0, 1200),
            ]);
        });

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip() . '|' . strtolower((string) ($request->input('email') ?? $request->input('username') ?? 'unknown'))));
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('password-otp', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip() . '|' . strtolower((string) $request->input('email', 'unknown'))));
        RateLimiter::for('password-verify', fn (Request $request) => Limit::perMinute(8)
            ->by($request->ip() . '|' . strtolower((string) $request->input('email', 'unknown'))));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip() . '|' . strtolower((string) $request->input('email', 'unknown'))));
        RateLimiter::for('authenticated-write', fn (Request $request) => Limit::perMinute(60)
            ->by((string) optional($request->user())->id));
        RateLimiter::for('payment-checkout', fn (Request $request) => Limit::perHour(10)
            ->by((string) optional($request->user())->id));
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Centralized shared administrative layout data.
        // Uses AdminSharedDataService to retrieve sidebar counters in ONE query
        // with in-memory memoization. Cached avatars avoid remote storage calls.
        View::composer('admin.layouts.app', function ($view) {
            try {
                $sharedData = app(\App\Services\AdminSharedDataService::class);
                $user = auth()->user();

                $existingCounts = $view->getData()['sidebarCounts'] ?? [];
                $sidebarCounts = $sharedData->getSidebarCounts($user, $existingCounts);
                $adminAvatarUrl = request()->routeIs('admin.deliveries.*')
                    ? null
                    : $sharedData->getAdminAvatarUrl($user);

                $view->with('sidebarCounts', $sidebarCounts);
                $view->with('headerNotifications', []);
                $view->with('adminAvatarUrl', $adminAvatarUrl);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Admin layout composer error: ' . $e->getMessage());
                if (!$view->offsetExists('sidebarCounts')) {
                    $view->with('sidebarCounts', \App\Services\AdminSharedDataService::DEFAULT_COUNTS);
                }
                $view->with('headerNotifications', []);
                $view->with('adminAvatarUrl', null);
            }
        });
    }
}
