<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdminPlatformTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** User holding $role in the default tenant (north-batinah). */
    protected function tenantUser(string $role = 'admin', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    protected function otherTenant(string $slug = 'b'): Tenant
    {
        return Tenant::create(['slug' => $slug, 'name' => ['en' => 'B', 'ar' => 'B'], 'status' => 'active']);
    }

    protected function walletWithCash(User $user, int $cashMinor = 10000, ?Tenant $tenant = null): Wallet
    {
        $service = app(WalletService::class);
        $tenant ??= $this->tenant;

        return app(TenantContext::class)->run($tenant, function () use ($service, $user, $tenant, $cashMinor) {
            $wallet = $service->walletFor($user, $tenant);
            if ($cashMinor > 0) {
                $service->credit($wallet, $cashMinor, 'topup', ['bucket' => 'cash', 'idempotency_key' => 'seed-topup-'.$wallet->id]);
            }

            return $wallet->fresh();
        });
    }
}
