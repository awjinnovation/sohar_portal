<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts an API route to gate staff of the *current tenant*.
 *
 * Returns a JSON 403 (not a redirect). A user counts as staff if they are a
 * platform super-admin, hold the admin/staff role in this tenant, or have
 * the "validate tickets" permission in this tenant (roles/permissions are
 * team-scoped, team = tenant, set by ResolveTenant).
 */
class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Roles are team (tenant) scoped: never reuse a relation loaded for another tenant.
        $user?->unsetRelation('roles')->unsetRelation('permissions');

        $authorized = $user && (
            $user->isSuperAdmin()
            || $user->hasAnyRole(['admin', 'staff'])
            || $user->checkPermissionTo('validate tickets')
        );

        if (! $authorized) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform ticket validation',
                'error_code' => 'FORBIDDEN',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
