<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts an API route to festival staff.
 *
 * Unlike AdminMiddleware (which redirects browser sessions to the admin
 * login), this returns a JSON 403 suitable for the mobile app / API clients.
 * A user counts as staff if they hold the admin, super-admin or staff role,
 * or the explicit "validate-tickets" permission.
 */
class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $authorized = $user
            && ($user->hasAnyRole(['admin', 'super-admin', 'staff'])
                || $user->can('validate-tickets'));

        if (!$authorized) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform ticket validation',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
