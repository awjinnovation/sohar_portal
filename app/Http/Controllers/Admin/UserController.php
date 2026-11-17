<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Panel users of the current tenant.
 *
 * Users are global identities; what a tenant controls is the set of roles a
 * user holds *in this tenant* (spatie teams, team = tenant; the team context
 * is set by AdminMiddleware). Platform super-admins additionally see every
 * user (?scope=all) and may grant/revoke users.is_super_admin.
 *
 * Staff who only use the app (gate scanners, vendor staff) sign in there
 * with their phone number and a one-time code, so a user is added by phone,
 * by e-mail + password (panel sign-in), or both. A phone number that already
 * has an account (the employee signed in to the app before) gets the roles
 * on that account instead of a second one.
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
        $phone = $this->normalizedPhone($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => ['nullable', 'string', $this->phoneRule($phone)],
            'email' => ['nullable', 'required_without:phone_number', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'required_with:email', 'string', 'min:8', 'confirmed'],
            'roles' => 'required|array',
            'roles.*' => ['string', Rule::in($roleNames)],
            'is_super_admin' => 'sometimes|boolean',
        ], [
            'email.required_without' => __('أدخل رقم الهاتف أو البريد الإلكتروني'),
            'password.required_with' => __('أدخل كلمة مرور للدخول إلى لوحة التحكم بالبريد الإلكتروني'),
        ]);

        // The employee already has an account (signed in to the app before):
        // give it the roles in this tenant, without touching its identity.
        $existing = is_string($phone) ? User::where('phone_number', $phone)->first() : null;
        if ($existing !== null) {
            if (filled($validated['email'] ?? null) || ($existing->isSuperAdmin() && ! $request->user()->isSuperAdmin())) {
                throw ValidationException::withMessages(['phone_number' => __('رقم الهاتف مسجل لحساب آخر')]);
            }
            $existing->assignRole($validated['roles']);

            return redirect()->route('admin.users.index')
                ->with('success', __('الرقم مسجل في التطبيق باسم :name، فأُضيفت الأدوار إلى حسابه', ['name' => $existing->name]));
        }

        $user = DB::transaction(function () use ($validated, $request, $phone) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone_number' => $phone ?: null,
                // Phone-only staff sign in with a one-time code, never a password.
                'password' => Hash::make($validated['password'] ?? Str::random(32)),
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
        $phone = null;
        if ($canEditIdentity) {
            $phone = $this->normalizedPhone($request);
            $rules += [
                'name' => 'required|string|max:255',
                'phone_number' => ['nullable', 'string', $this->phoneRule($phone), Rule::unique('users', 'phone_number')->ignore($user->id)],
                // Phone-only (OTP) users may have no e-mail.
                'email' => ['nullable', 'required_without:phone_number', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => 'nullable|string|min:8|confirmed',
            ];
        }
        $validated = $request->validate($rules, [
            'phone_number.unique' => __('رقم الهاتف مسجل لحساب آخر'),
            'email.required_without' => __('أدخل رقم الهاتف أو البريد الإلكتروني'),
        ]);

        if ($user->is($actor) && ! $actor->isSuperAdmin() && ! in_array('admin', $validated['roles'], true) && $actor->hasRole('admin')) {
            return back()->withInput()->with('error', 'لا يمكنك إزالة دور المدير من حسابك');
        }

        DB::transaction(function () use ($validated, $user, $actor, $request, $canEditIdentity, $phone) {
            if ($canEditIdentity) {
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'phone_number' => $phone ?: null,
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

    /**
     * The form's phone number as the app stores it (`96891234567`), put back
     * on the request so the other rules see it: null when left empty, false
     * when it is not a valid mobile number ([phoneRule] reports that).
     */
    private function normalizedPhone(Request $request): string|false|null
    {
        $input = trim((string) $request->input('phone_number', ''));
        if ($input === '') {
            $request->merge(['phone_number' => null]);

            return null;
        }
        $phone = PhoneNumber::normalize($this->currentTenant(), $input);
        if ($phone !== null) {
            $request->merge(['phone_number' => $phone]);
        }

        return $phone ?? false;
    }

    /** Fails when [normalizedPhone] could not read the number. */
    private function phoneRule(string|false|null $phone): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($phone) {
            if ($phone === false) {
                $fail(__('رقم الهاتف غير صحيح، أدخل رقم جوال من :n أرقام', ['n' => PhoneNumber::localLength($this->currentTenant())]));
            }
        };
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
