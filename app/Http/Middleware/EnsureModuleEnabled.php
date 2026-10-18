<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `module:wallet` (any of several: `module:vendors,deposits`).
 */
class EnsureModuleEnabled
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $tenant = $this->context->get();

        foreach ($modules as $module) {
            if ($tenant && $tenant->moduleEnabled($module)) {
                return $next($request);
            }
        }

        throw ApiException::moduleDisabled(implode(',', $modules));
    }
}
