<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\PointsAccount;
use App\Models\PointsTransaction;
use App\Models\User;
use App\Services\Wallet\PointsService;

class PointsAdminTest extends AdminPlatformTestCase
{
    private function accountWith(User $user, int $points): PointsAccount
    {
        $service = app(PointsService::class);
        $account = $service->accountFor($user, $this->tenant);
        if ($points > 0) {
            $service->award($account, $this->tenant, $points, 'platform.points.earn', null, 'seed:'.$user->id);
        }

        return $account->fresh();
    }

    public function test_sidebar_shows_points_and_index_lists_accounts_with_search_and_rules(): void
    {
        $admin = $this->tenantUser();
        $salim = User::factory()->create(['name' => 'Salim Points', 'phone_number' => '96890001111']);
        $other = User::factory()->create(['name' => 'Other Person', 'phone_number' => '96890002222']);
        $this->accountWith($salim, 1200); // silver (≥ 1000 lifetime)
        $this->accountWith($other, 10);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.points.index'), false);

        $this->actingAs($admin)->get(route('admin.points.index'))->assertOk()
            ->assertSee('Salim Points')->assertSee('Other Person')
            ->assertSee('فضي')            // tier of Salim
            ->assertSee('قواعد النقاط')   // rules summary
            ->assertSee('365');           // expiry days

        $this->actingAs($admin)->get(route('admin.points.index', ['q' => '0001111']))->assertOk()
            ->assertSee('Salim Points')->assertDontSee('Other Person');
        $this->actingAs($admin)->get(route('admin.points.index', ['tier' => 'bronze']))->assertOk()
            ->assertSee('Other Person')->assertDontSee('Salim Points');
    }

    public function test_history_and_manual_adjustment_are_audited(): void
    {
        $admin = $this->tenantUser();
        $account = $this->accountWith(User::factory()->create(), 100);

        $this->actingAs($admin)->get(route('admin.points.show', $account))->assertOk()->assertSee('سجل النقاط');

        $this->actingAs($admin)->post(route('admin.points.adjust', $account), [
            'direction' => 'credit', 'points' => 50, 'reason' => 'Goodwill',
        ])->assertRedirect(route('admin.points.show', $account))->assertSessionHas('success');
        $account->refresh();
        $this->assertSame(150, $account->balance);
        $this->assertSame(100, $account->lifetime_earned); // tier basis unchanged

        $this->actingAs($admin)->post(route('admin.points.adjust', $account), [
            'direction' => 'debit', 'points' => 30, 'reason' => 'Correction',
        ])->assertSessionHas('success');
        $this->assertSame(120, $account->fresh()->balance);

        $this->assertSame(2, PointsTransaction::where('points_account_id', $account->id)->where('type', 'adjust')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'points.adjustment', 'subject_id' => $account->id, 'actor_id' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.points.show', $account))->assertOk()->assertSee('Goodwill')->assertSee('Correction');

        // Too many points → flashed error, nothing changes; reason required.
        $this->actingAs($admin)->post(route('admin.points.adjust', $account), [
            'direction' => 'debit', 'points' => 1000, 'reason' => 'Too much',
        ])->assertSessionHas('error');
        $this->assertSame(120, $account->fresh()->balance);
        $this->actingAs($admin)->post(route('admin.points.adjust', $account), ['direction' => 'credit', 'points' => 5])
            ->assertSessionHasErrors('reason');
    }

    public function test_points_pages_are_tenant_scoped_and_permission_guarded(): void
    {
        $other = $this->otherTenant();
        $foreign = app(\App\Tenancy\TenantContext::class)->run($other, fn () => app(PointsService::class)->accountFor(User::factory()->create(), $other));

        $this->actingAs($this->tenantUser())->get(route('admin.points.show', $foreign->id))->assertNotFound();
        $this->actingAs($this->tenantUser('event-manager'))->get(route('admin.points.index'))->assertForbidden();
    }
}
