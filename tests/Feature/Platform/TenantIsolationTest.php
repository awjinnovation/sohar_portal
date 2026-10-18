<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    #[Test]
    public function events_are_isolated_per_tenant(): void
    {
        $b = $this->makeTenant('tenant-b');
        $eventA = $this->makeEvent($this->tenant, ['title' => 'Only in A']);
        $eventB = $this->makeEvent($b, ['title' => 'Only in B']);

        $this->getJson('/api/v1/events', ['X-Tenant' => 'tenant-b'])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Only in B'])
            ->assertJsonMissing(['title' => 'Only in A']);

        $this->getJson("/api/v1/events/{$eventA->id}", ['X-Tenant' => 'tenant-b'])->assertNotFound();
        $this->getJson("/api/v1/events/{$eventB->id}", ['X-Tenant' => 'tenant-b'])->assertOk();

        // No header: backward-compatible default tenant (north-batinah).
        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Only in A'])
            ->assertJsonMissing(['title' => 'Only in B']);
    }

    #[Test]
    public function unknown_or_inactive_tenant_returns_404(): void
    {
        $this->makeTenant('sleepy', [], 'inactive');

        $this->getJson('/api/v1/events', ['X-Tenant' => 'nope'])->assertNotFound()->assertJson(['success' => false, 'code' => 'TENANT_NOT_FOUND']);
        $this->getJson('/api/v1/tenant/config', ['X-Tenant' => 'sleepy'])->assertNotFound();
    }

    #[Test]
    public function wallets_are_per_user_and_tenant(): void
    {
        $b = $this->makeTenant('tenant-b');
        $user = $this->makeUser();
        $this->fund($user, 5000, $this->tenant);

        $this->api('GET', '/api/v1/wallet', [], $user, $this->tenant)->assertOk()->assertJsonPath('data.balance_minor', 5000);
        $this->api('GET', '/api/v1/wallet', [], $user, $b)->assertOk()->assertJsonPath('data.balance_minor', 0);

        $this->assertSame(2, Wallet::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function tenant_a_cannot_read_tenant_b_wallet_transactions_or_payments(): void
    {
        $b = $this->makeTenant('tenant-b');
        $user = $this->makeUser();
        $this->fund($user, 3000, $b);

        $this->api('GET', '/api/v1/wallet/transactions', [], $user, $this->tenant)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->api('GET', '/api/v1/wallet/transactions', [], $user, $b)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function models_created_in_a_tenant_context_get_its_tenant_id(): void
    {
        $b = $this->makeTenant('tenant-b');
        $event = $this->makeEvent($b);

        $this->assertSame($b->id, Event::withoutTenantScope()->find($event->id)->tenant_id);
        $this->assertNull(Event::find($event->id), 'default-tenant scope must hide tenant B rows');
    }
}
