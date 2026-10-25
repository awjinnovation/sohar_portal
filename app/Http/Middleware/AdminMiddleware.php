<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel gate + tenant binding.
 *
 * - Platform super-admins (users.is_super_admin) may work in any tenant;
 *   the current one is kept in the session (tenant switcher).
 * - Tenant staff may enter only tenants in which they hold a role (spatie
 *   teams, team = tenant). Any role other than the app-only roles
 *   (`user`, `vendor-staff`) grants panel access; individual screens are
 *   further guarded by `can:<permission>` route middleware.
 */
class AdminMiddleware
{
    public const SESSION_KEY = 'admin_tenant_id';

    /** Roles that never grant admin-panel access on their own. */
    public const NON_PANEL_ROLES = ['user', 'vendor-staff'];

    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect('/admin/login');
        }

        $allowedIds = $user->isSuperAdmin()
            ? Tenant::pluck('id')->all()
            : $user->panelTenantIds();

        $tenantId = (int) $request->session()->get(self::SESSION_KEY);
        if (! in_array($tenantId, $allowedIds, true)) {
            $default = Tenant::where('slug', config('tenancy.default'))->value('id');
            $tenantId = in_array($default, $allowedIds, true) ? $default : ($allowedIds[0] ?? null);
        }

        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        if (! $tenant) {
            return $this->deny($request);
        }

        $request->session()->put(self::SESSION_KEY, $tenant->id);
        $this->context->set($tenant);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        if (! $user->isSuperAdmin()) {
            $panelRoles = $user->getRoleNames()->diff(self::NON_PANEL_ROLES);
            if ($panelRoles->isEmpty()) {
                return $this->deny($request);
            }
        }

        view()->share('currentTenant', $tenant);
        view()->share('adminTenants', Tenant::whereIn('id', $allowedIds)->orderBy('id')->get());

        return $next($request);
    }

    private function deny(Request $request): Response
    {
        $locale = $request->session()->get(SetAdminLocale::SESSION_KEY);
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        if ($locale) {
            $request->session()->put(SetAdminLocale::SESSION_KEY, $locale);
        }

        return redirect('/admin/login')->withErrors(['email' => __('ليست لديك صلاحية الدخول إلى لوحة التحكم.')]);
    }
}
