<?php

namespace App\Http\Middleware;

use Closure;
class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
