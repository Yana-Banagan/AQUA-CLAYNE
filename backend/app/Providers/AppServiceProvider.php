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

        // Provide notification data to every page that uses the admin dashboard
        // layout. Individual controllers no longer need to remember to pass it.
        View::composer('admin.layouts.app', function ($view) {
            $adminAvatarUrl = null;
            try {
                $user = auth()->user();
                $headerData = $user
                    ? Cache::store('file')->remember('admin-header-v2:' . $user->id, now()->addSeconds(15), function () use ($user) {
                        return [
                            'unreadNotifs' => $user->notifications()->where('is_read', false)->count(),
                            'unreadChats' => in_array($user->role, ['admin', 'staff'], true)
                                ? Cache::store('file')->remember('admin-unread-chats', now()->addSeconds(15), fn () => ChatMessage::whereIn('sender_role', ['customer', 'driver'])->whereNull('read_at')->count())
                                : 0,
                            'notifications' => Notification::where('user_id', $user->id)
                                ->latest('created_at')
                                ->take(5)
                                ->get(['id', 'title', 'body', 'is_read', 'created_at'])
                                ->map(fn (Notification $notification) => [
                                    'id' => (string) $notification->id,
                                    'title' => (string) $notification->title,
                                    'body' => (string) $notification->body,
                                    'is_read' => (bool) $notification->is_read,
                                    'time_ago' => $notification->created_at?->diffForHumans() ?? 'Recently',
                                ])
                                ->all(),
                        ];
                    })
                    : ['unreadNotifs' => 0, 'unreadChats' => 0, 'notifications' => []];
                $unreadNotifs = $headerData['unreadNotifs'];
                $unreadChats = $headerData['unreadChats'];
                $headerNotifications = $headerData['notifications'];
                $adminAvatarUrl = auth()->check() && auth()->user()->avatar_path
                    ? app(SupabaseStorageService::class)->signedProfilePhotoUrl(
                        auth()->user()->id,
                        auth()->user()->avatar_path,
                    )
                    : null;

                // Preserve the page-specific operational counts, but always use
                // the signed-in user's unread notification count.
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
