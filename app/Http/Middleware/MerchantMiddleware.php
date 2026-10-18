<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merchant endpoints (contract §9): vendor staff (role `vendor-staff` +
 * membership of a vendor of the current tenant) or gate `staff` / admins.
 *
 * Resolves the acting vendor (header X-Vendor-Id to pick one when the user
 * belongs to several) into $request->attributes 'vendor' (may be null for
 * staff without a vendor — they can take deposits but not charge).
 */
class MerchantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $this->deny('Unauthenticated', 401);
        }
        // Roles are team (tenant) scoped: never reuse a relation loaded for another tenant.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $vendors = Vendor::where('is_active', true)
            ->whereHas('staff', fn ($q) => $q->where('users.id', $user->id))
            ->get();

        $isVendorStaff = $vendors->isNotEmpty() && ($user->hasAnyRole(['vendor-staff']) || $user->checkPermissionTo('charge wallets'));
        $isStaff = $user->isSuperAdmin() || $user->hasAnyRole(['admin', 'staff']);

        if (! $isVendorStaff && ! $isStaff) {
            return $this->deny('You are not authorized to use merchant features', 403);
        }

        $vendor = null;
        if ($vendors->isNotEmpty()) {
            $wanted = $request->header('X-Vendor-Id');
            $vendor = $wanted ? $vendors->firstWhere('id', (int) $wanted) : $vendors->first();
            if ($wanted && ! $vendor) {
                return $this->deny('You are not a member of this vendor', 403);
            }
        }

        $request->attributes->set('vendor', $vendor);
        $request->attributes->set('merchant_role', $isVendorStaff ? 'vendor-staff' : 'staff');

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'code' => 'FORBIDDEN'], $status);
    }
}
