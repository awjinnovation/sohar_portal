<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;

class AdminPlatformPagesTest extends AdminPlatformTestCase
{
    public function test_tenant_admin_can_open_every_new_section(): void
    {
        $admin = $this->tenantUser();
        $customer = User::factory()->create(['phone_number' => '96890000001']);
        $wallet = $this->walletWithCash($customer, 5000);
        $vendor = Vendor::create(['name' => ['en' => 'Stall', 'ar' => 'كشك'], 'is_active' => true]);
        $payment = Payment::create([
            'user_id' => $customer->id, 'transaction_id' => 'TX-PAGES-1', 'payment_type' => 'ticket',
            'method' => 'gateway', 'amount' => 1.5, 'amount_minor' => 1500, 'currency' => 'OMR', 'status' => 'completed',
        ]);

        $urls = [
            route('admin.dashboard'),
            route('admin.wallets.index'),
            route('admin.wallets.index', ['q' => '96890000001']),
            route('admin.wallets.show', $wallet),
            route('admin.refund-requests.index'),
            route('admin.refund-requests.index', ['status' => 'all']),
            route('admin.deposits.index'),
            route('admin.vendors.index'),
            route('admin.vendors.create'),
            route('admin.vendors.show', $vendor),
            route('admin.vendors.edit', $vendor),
            route('admin.payments.index'),
            route('admin.payments.index', ['q' => 'TX-PAGES', 'status' => 'completed']),
            route('admin.payments.show', $payment),
            route('admin.branding.edit'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_tenants_screen_is_reserved_to_super_admins(): void
    {
        $admin = $this->tenantUser();
        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.tenants.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.tenants.edit', $this->tenant))->assertForbidden();

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.tenants.index'))->assertOk()->assertSee('north-batinah');
        $this->actingAs($super)->get(route('admin.tenants.create'))->assertOk();
        $this->actingAs($super)->get(route('admin.tenants.edit', $this->tenant))->assertOk();
    }

    public function test_layout_uses_tenant_branding(): void
    {
        $this->tenant->name = ['en' => 'Brand Co', 'ar' => 'شركة العلامة'];
        $this->tenant->putSetting('theme.colors.primary', '#123ABC')->save();

        $response = $this->actingAs($this->tenantUser())->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('#123ABC', false)
            ->assertSee('شركة العلامة')
            ->assertSee('images/platform-logo.svg', false)
            ->assertDontSee('صحار')
            ->assertDontSee('sohar_fastival_logo', false);
    }

    public function test_menu_only_lists_permitted_sections(): void
    {
        $viewer = $this->tenantUser('viewer');

        $response = $this->actingAs($viewer)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee(route('admin.payments.index'), false)
            ->assertDontSee(route('admin.wallets.index'), false)
            ->assertDontSee(route('admin.tenants.index'), false)
            ->assertDontSee(route('admin.branding.edit'), false);

        $this->actingAs($viewer)->get(route('admin.wallets.index'))->assertForbidden();
    }

    public function test_tenant_switcher_is_shown_to_users_of_several_tenants(): void
    {
        $this->otherTenant();
        $super = $this->superAdmin();

        $this->actingAs($super)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.tenant.switch'), false)
            ->assertSee(route('admin.tenants.index'), false);
    }
}
