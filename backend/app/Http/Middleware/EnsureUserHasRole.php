<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $roleName = $user->role?->name;

        if ($roleName === null || ! in_array($roleName, $roles, true)) {
            return ApiResponse::error('Forbidden', 403);
        }

        return $next($request);
    }
}
