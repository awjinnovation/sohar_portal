<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role definitions are global (team_id NULL) and shared by every tenant, so
 * creating / editing / deleting a role or its permissions is reserved to
 * platform super-admins. Tenant staff with `manage roles` may browse them.
 */
class RoleController extends Controller
{
    use TenantValidation;

    /** Roles that are part of the platform and cannot be edited/deleted. */
    public const PROTECTED_ROLES = ['super-admin', 'admin'];

    public function index()
    {
        $roles = $this->visibleRoles()->with('permissions')->orderBy('id')->paginate(10);
        $registrar = app(PermissionRegistrar::class);
        // Members per role in the current tenant only.
        $tenantUserCounts = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.team_foreign_key'), $this->currentTenantId())
            ->whereIn($registrar->pivotRole, $roles->pluck('id'))
            ->groupBy($registrar->pivotRole)
            ->selectRaw($registrar->pivotRole.' as role_id, count(*) as aggregate')
            ->pluck('aggregate', 'role_id');

        return view('admin.roles.index', compact('roles', 'tenantUserCounts'));
    }

    public function create(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $permissions = $this->groupedPermissions();

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->whereNull('team_id')->where('guard_name', 'web')],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = $this->asGlobal(function () use ($validated) {
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web', 'team_id' => null]);
            if (! empty($validated['permissions'])) {
                $role->givePermissionTo(Permission::whereIn('id', $validated['permissions'])->get());
            }

            return $role;
        });

        return redirect()->route('admin.roles.index')
            ->with('success', 'أُنشئ الدور');
    }

    public function show(Role $role)
    {
        $this->ensureVisible($role);
        $role->load('permissions');
        // Members of this role in the current tenant only.
        $tenantUsers = \App\Models\User::whereIn('id', $this->tenantAssignments($role)
            ->where('model_type', \App\Models\User::class)
            ->select(config('permission.column_names.model_morph_key')))
            ->orderBy('name')
            ->get();

        return view('admin.roles.show', compact('role', 'tenantUsers'));
    }

    public function edit(Request $request, Role $role)
    {
        $this->ensureSuperAdmin($request);
        $this->ensureVisible($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'لا يمكن تعديل هذا الدور');
        }

        $permissions = $this->groupedPermissions();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureSuperAdmin($request);
        $this->ensureVisible($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'لا يمكن تعديل هذا الدور');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')
                ->where(fn ($q) => $role->team_id ? $q->where('team_id', $role->team_id) : $q->whereNull('team_id'))
                ->where('guard_name', $role->guard_name)
                ->ignore($role->id)],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $this->asGlobal(function () use ($role, $validated) {
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions(Permission::whereIn('id', $validated['permissions'] ?? [])->get());
        });

        return redirect()->route('admin.roles.index')
            ->with('success', 'حُدِّث الدور');
    }

    public function destroy(Request $request, Role $role)
    {
        $this->ensureSuperAdmin($request);
        $this->ensureVisible($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'لا يمكن حذف هذا الدور');
        }

        // Assignments across *all* tenants (role definitions are shared).
        $inUse = DB::table(config('permission.table_names.model_has_roles'))
            ->where(app(PermissionRegistrar::class)->pivotRole, $role->id)
            ->exists();
        if ($inUse) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'لا يمكن حذف دور مرتبط بمستخدمين');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'حُذف الدور');
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'تعريفات الأدوار مشتركة بين جميع المنظمات، ويديرها مشرف المنصة وحده.');
    }

    /** Global roles plus (legacy) roles scoped to the current tenant. */
    private function visibleRoles()
    {
        $tenantId = $this->currentTenantId();

        return Role::query()->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $tenantId));
    }

    private function ensureVisible(Role $role): void
    {
        abort_unless($role->team_id === null || (int) $role->team_id === $this->currentTenantId(), 404);
    }

    private function tenantAssignments(Role $role)
    {
        $registrar = app(PermissionRegistrar::class);

        return DB::table(config('permission.table_names.model_has_roles'))
            ->where($registrar->pivotRole, $role->id)
            ->where(config('permission.column_names.team_foreign_key'), $this->currentTenantId());
    }

    private function groupedPermissions()
    {
        return Permission::all()->groupBy(function ($permission) {
            $parts = explode(' ', $permission->name);

            return end($parts);
        });
    }

    /** Run with the spatie team context cleared (global role definitions). */
    private function asGlobal(callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $registrar->forgetCachedPermissions();
        }
    }
}
