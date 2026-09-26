<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-system gate. The web system serves staff-side roles (Administrator,
 * Staff, Faculty). Students are mobile-app only.
 *
 * Staff/Faculty accounts that have no dashboard view permission are
 * blocked here as well — they would see nothing usable anyway.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isStudent()) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }

        if ($user->role?->name !== 'Administrator' && !$user->canAccess('dashboard', 'view')) {
            return response()->json(['message' => 'You do not have access to the web system.'], 403);
        }

        return $next($request);
    }
}
