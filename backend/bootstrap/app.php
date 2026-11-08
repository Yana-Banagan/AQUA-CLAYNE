<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\LogSlowRequests::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'customer/logout',
            'logout',
            'api/*',
        ]);

        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user && $user->role === 'customer') {
                return route('customer.index');
            }
            if ($user && in_array($user->role, ['admin', 'staff'])) {
                return route('admin.dashboard');
            }
            return route('customer.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            if ($request->expectsJson() && str_contains($exception->getMessage(), 'SQLSTATE[08006]')) {
                return response()->json([
                    'message' => 'The service database is temporarily unavailable. Please try again shortly.',
                ], 503);
            }
        });
    })
    ->create();
