<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

trait PlatformHelpers
{
    protected function makeTenant(string $slug, array $settings = [], string $status = 'active'): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => ['en' => Str::headline($slug), 'ar' => $slug],
            'status' => $status,
            'settings' => $settings,
        ]);
    }

    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }

    protected function makeEvent(?Tenant $tenant = null, array $attrs = []): Event
    {
        $tenant ??= $this->tenant;

        return $this->inTenant($tenant, function () use ($attrs) {
            $category = Category::create([
                'name' => 'Cat', 'name_ar' => 'فئة', 'description' => 'd', 'description_ar' => 'd',
                'icon_name' => 'star', 'color_value' => 0xFF000000, 'display_order' => 1, 'is_active' => true,
            ]);

            return Event::create(array_merge([
                'title' => 'Concert', 'title_ar' => 'حفل', 'description' => 'd', 'description_ar' => 'd',
                'category_id' => $category->id,
                'start_time' => now()->subDay()->setTime(9, 0),
                'end_time' => now()->addDays(5)->setTime(23, 0),
                'location' => 'Hall', 'location_ar' => 'قاعة',
                'price' => '2.500', 'currency' => 'OMR',
                'total_tickets' => 100, 'available_tickets' => 100,
                'is_active' => true, 'is_featured' => false,
            ], $attrs));
        });
    }

    protected function makeUser(?string $phone = null): User
    {
        return User::factory()->create([
            'phone_number' => $phone ?? '9'.random_int(1000000, 9999999),
        ]);
    }

    protected function fund(User $user, int $minor, ?Tenant $tenant = null, string $bucket = 'cash'): Wallet
    {
        $tenant ??= $this->tenant;
        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($user, $tenant);
        $wallets->credit($wallet, $minor, $bucket === 'promo' ? 'adjustment' : 'topup', [
            'bucket' => $bucket,
            'idempotency_key' => 'fund:'.Str::uuid(),
        ]);

        return $wallet->refresh();
    }

    protected function wallet(User $user, ?Tenant $tenant = null): Wallet
    {
        return app(WalletService::class)->walletFor($user, $tenant ?? $this->tenant)->refresh();
    }

    protected function tenantHeaders(?Tenant $tenant = null, array $extra = []): array
    {
        return array_merge(['X-Tenant' => ($tenant ?? $this->tenant)->slug, 'Accept' => 'application/json'], $extra);
    }

    /** JSON request as $user (sanctum) in $tenant. */
    protected function api(string $method, string $uri, array $data = [], ?User $user = null, ?Tenant $tenant = null)
    {
        if ($user) {
            $this->actingAs($user, 'sanctum');
        }

        return $this->json($method, $uri, $data, $this->tenantHeaders($tenant));
    }

    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function grantRole(User $user, string $role, ?Tenant $tenant = null): void
    {
        $this->inTenant($tenant ?? $this->tenant, function () use ($user, $role) {
            $user->unsetRelation('roles');
            $user->assignRole($role);
        });
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
