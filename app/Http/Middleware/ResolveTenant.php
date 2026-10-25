<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API tenant resolution (contract §3): `X-Tenant: <slug>` header, falling
 * back to config('tenancy.default') for older app builds. Unknown or
 * inactive tenants get a 404 envelope. Also sets the request locale from
 * Accept-Language (or the tenant's default locale).
 */
class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = trim((string) $request->header(config('tenancy.header', 'X-Tenant'), ''));
        $slug = $slug !== '' ? strtolower($slug) : config('tenancy.default');

        $tenant = Tenant::findBySlug($slug);

        if (! $tenant || ! $tenant->isActive()) {
            return response()->json([
                'success' => false,
                'message' => __('platform.errors.tenant_not_found'),
                'code' => 'TENANT_NOT_FOUND',
            ], 404);
        }

        $this->context->set($tenant);

        $locale = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));
        $locales = (array) $tenant->setting('tenant.locales', ['ar', 'en']);
        app()->setLocale(in_array($locale, $locales, true) ? $locale : (string) $tenant->setting('tenant.default_locale', 'en'));

        return $next($request);
    }
}
