<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the permission editor itself: only the super admin may
 * view or change anyone's permissions.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only the Super Admin can manage user permissions.',
            ], 403);
        }

        return $next($request);
    }
}
