<?php

namespace App\Http\Middleware;

use App\Services\Gate\BoxOfficeService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1/gate/sales/* (docs/gate_sales_contract.md §1, §3): runs after
 * AuthenticateGateDevice. Selling needs the tenant's `gate_sales.enabled`,
 * a gate with `can_sell`, and a staff member signed in on the device whose
 * account holds `sell tickets` in this tenant. Errors: SALES_DISABLED,
 * MODULE_DISABLED (tickets), GATE_CANNOT_SELL, GATE_INACTIVE,
 * STAFF_LOGIN_REQUIRED (data.reason no_staff | no_permission).
 */
class EnsureGateSales
{
    public function __construct(private TenantContext $context, private BoxOfficeService $boxOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->attributes->get('gate_device');
        if ($denial = $this->boxOffice->denial($this->context->get(), $device)) {
            throw $denial;
        }
        $request->attributes->set('gate_staff', $device->staff);

        return $next($request);
    }
}
