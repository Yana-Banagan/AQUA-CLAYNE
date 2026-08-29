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
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Account is inactive.'
            ], 403);
        }

        return $next($request);
    }
}
