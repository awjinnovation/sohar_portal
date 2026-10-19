<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Panel users of the current tenant.
 *
 * Users are global identities; what a tenant controls is the set of roles a
 * user holds *in this tenant* (spatie teams, team = tenant; the team context
 * is set by AdminMiddleware). Platform super-admins additionally see every
 * user (?scope=all) and may grant/revoke users.is_super_admin.
 */
class UserController extends Controller
{
    use TenantValidation;

    public function index(Request $request)
    {
        $actor = $request->user();
        $scope = $actor->isSuperAdmin() && $request->query('scope') === 'all' ? 'all' : 'tenant';
        $search = trim((string) $request->query('search', ''));

        $users = ($scope === 'all' ? User::query() : $this->tenantMembersQuery())
            ->with('roles')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'scope', 'search'));
    }

    public function create()
    {
        $roles = $this->assignableRoles();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $roleNames = $this->assignableRoles()->pluck('name')->all();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => ['string', Rule::in($roleNames)],
            'is_super_admin' => 'sometimes|boolean',
        ]);

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            if ($request->user()->isSuperAdmin() && $request->has('is_super_admin')) {
                $user->forceFill(['is_super_admin' => $request->boolean('is_super_admin')])->save();
            }

            // Assigned within the current tenant (team) only.
            $user->assignRole($validated['roles']);

            return $user;
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'أُنشئ المستخدم');
    }

    public function show(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $user->load('roles');

        return view('admin.users.show', compact('user'));
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $roles = $this->assignableRoles();
        $userRoles = $user->roles->pluck('name')->toArray();
        $canEditIdentity = $this->canEditIdentity($request->user(), $user);

        return view('admin.users.edit', compact('user', 'roles', 'userRoles', 'canEditIdentity'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $actor = $request->user();
        $roleNames = $this->assignableRoles()->pluck('name')->all();
        $canEditIdentity = $this->canEditIdentity($actor, $user);

        $rules = [
            'roles' => 'required|array',
            'roles.*' => ['string', Rule::in($roleNames)],
            'is_super_admin' => 'sometimes|boolean',
        ];
        if ($canEditIdentity) {
            $rules += [
                'name' => 'required|string|max:255',
                // Phone-only (OTP) users may have no e-mail.
                'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => 'nullable|string|min:8|confirmed',
            ];
        }
        $validated = $request->validate($rules);

        if ($user->is($actor) && ! $actor->isSuperAdmin() && ! in_array('admin', $validated['roles'], true) && $actor->hasRole('admin')) {
            return back()->withInput()->with('error', 'لا يمكنك إزالة دور المدير من حسابك');
        }

        DB::transaction(function () use ($validated, $user, $actor, $request, $canEditIdentity) {
            if ($canEditIdentity) {
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                ]);
                if (! empty($validated['password'])) {
                    $user->update(['password' => Hash::make($validated['password'])]);
                }
            }

            if ($actor->isSuperAdmin() && $request->has('is_super_admin') && ! $user->is($actor)) {
                $user->forceFill(['is_super_admin' => $request->boolean('is_super_admin')])->save();
            }

            // Only touches the role assignments of the current tenant (team).
            $user->syncRoles($validated['roles']);
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'حُدِّث المستخدم');
    }

    /**
     * Super-admins delete the (global) user; tenant admins only revoke the
     * user's roles in the current tenant, since the identity may be used by
     * other tenants (wallets, tickets, roles).
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        if ($user->id == auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'لا يمكنك حذف حسابك');
        }

        if ($request->user()->isSuperAdmin()) {
            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('success', 'حُذف المستخدم');
        }

        $user->syncRoles([]);

        return redirect()->route('admin.users.index')
            ->with('success', 'أُزيلت صلاحيات المستخدم في هذه المنظمة');
    }

    /** Users holding at least one role in the current tenant. */
    private function tenantMembersQuery()
    {
        return User::query()->whereIn('id', DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->where(config('permission.column_names.team_foreign_key'), $this->currentTenantId())
            ->select(config('permission.column_names.model_morph_key')));
    }

    /** Global role definitions (plus any legacy roles of this tenant). */
    private function assignableRoles()
    {
        $tenantId = $this->currentTenantId();

        return Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $tenantId))
            ->orderBy('id')
            ->get();
    }

    /**
     * Tenant staff may only manage users who hold a role in their tenant,
     * and never platform super-admins.
     */
    private function authorizeTarget(Request $request, User $user): void
    {
        $actor = $request->user();
        if ($actor->isSuperAdmin()) {
            return;
        }

        abort_unless($this->tenantMembersQuery()->whereKey($user->id)->exists(), 404);
        abort_if($user->isSuperAdmin(), 403);
    }

    /**
     * Name / e-mail / password are global. A tenant admin may change them
     * only for users that belong to no other tenant (otherwise they could
     * take over the account in another tenant).
     */
    private function canEditIdentity(User $actor, User $user): bool
    {
        if ($actor->isSuperAdmin() || $user->is($actor)) {
            return true;
        }

        $tenantId = $this->currentTenantId();

        return collect($user->tenantIdsWithRoles())->every(fn ($id) => $id === $tenantId)
            && ! $user->wallets()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->exists();
    }
}
