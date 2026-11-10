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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS URLs when running in production, behind an SSL proxy (like Railway),
        // or when APP_URL is configured with https.
        if (app()->environment('production') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || str_starts_with(config('app.url'), 'https://')) {
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

        // Keep the shared admin shell lightweight. The page header only needs the
        // unread counts; the notification list is refreshed asynchronously via the
        // existing admin status endpoint so we avoid loading the full notification
        // history on every admin request.
        View::composer('admin.layouts.app', function ($view) {
            $adminAvatarUrl = null;
            $unreadNotifs = 0;
            $unreadChats = 0;
            $headerNotifications = [];

            try {
                $user = auth()->user();

                if ($user) {
                    $userId = $user->getAuthIdentifier();

                    $unreadNotifs = Cache::store('file')->remember('admin-header-unread-notifications:' . $userId, now()->addSeconds(15), function () use ($userId) {
                        return Notification::where('user_id', $userId)
                            ->where('is_read', false)
                            ->count();
                    });

                    if (in_array($user->role, ['admin', 'staff'], true)) {
                        $unreadChats = Cache::store('file')->remember('admin-header-unread-chats', now()->addSeconds(15), function () {
                            return ChatMessage::whereIn('sender_role', ['customer', 'driver'])
                                ->whereNull('read_at')
                                ->count();
                        });
                    }

                    $adminAvatarUrl = $user->avatar_path
                        ? app(SupabaseStorageService::class)->signedProfilePhotoUrl($userId, $user->avatar_path)
                        : null;
                }

                $existingCounts = $view->getData()['sidebarCounts'] ?? [];
                $view->with('sidebarCounts', array_merge([
                    'pendingOrders' => 0,
                    'waitingInQueue' => 0,
                    'activeDeliveries' => 0,
                    'lowStock' => 0,
                ], $existingCounts, [
                    'unreadNotifications' => $unreadNotifs,
                    'unreadChats' => $unreadChats,
                ]));
                $view->with('headerNotifications', $headerNotifications);
                $view->with('adminAvatarUrl', $adminAvatarUrl);
            } catch (\Throwable $e) {
                if (!$view->offsetExists('sidebarCounts')) {
                    $view->with('sidebarCounts', [
                        'pendingOrders' => 0,
                        'waitingInQueue' => 0,
                        'activeDeliveries' => 0,
                        'lowStock' => 0,
                        'unreadNotifications' => 0,
                    ]);
                }
                $view->with('headerNotifications', []);
                $view->with('adminAvatarUrl', $adminAvatarUrl);
            }
        });
    }
}
