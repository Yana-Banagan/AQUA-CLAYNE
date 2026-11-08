<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            if (!$request->expectsJson()) {
                if ($user && $user->role === 'customer') {
                    return redirect()->route('customer.index')->with('error', 'You do not have administrative access. Redirected to customer portal.');
                }
                if (!$user) {
                    return redirect()->route('admin.login');
                }
                abort(403, 'You are not authorized to access this area.');
            }

            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        if ($user->status !== 'active') {
            if (!$request->expectsJson()) {
                abort(403, 'Your account is inactive.');
            }

            return response()->json([
                'message' => 'Account is inactive.'
            ], 403);
        }

        return $next($request);
    }
}
