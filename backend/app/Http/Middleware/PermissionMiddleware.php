<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces one catalog permission on a route:
 *   ->middleware('perm:users.create')   — dot form
 *   ->middleware('perm:users')          — module name only (view)
 *
 * Runs after the 'admin' middleware, so the user is already an
 * authenticated staff-side account. Super admins always pass.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, 'view');

        $user = $request->user();

        if (!$user || !$user->canAccess($module, $action)) {
            return response()->json([
                'message' => "You do not have permission to perform this action ({$module}.{$action}).",
            ], 403);
        }

        return $next($request);
    }
}
