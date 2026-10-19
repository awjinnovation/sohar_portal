<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use App\Models\MapLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminPolishTest extends AdminPlatformTestCase
{
    public function test_admin_language_switch_is_kept_in_the_session(): void
    {
        $admin = $this->tenantUser();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)->assertSee('bootstrap.rtl.min.css', false)->assertSee('لوحة التحكم');

        $this->actingAs($admin)->from(route('admin.dashboard'))->post(route('admin.locale'), ['locale' => 'en'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas('admin_locale', 'en');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('bootstrap.min.css', false)
            ->assertSee('Wallet &amp; payments', false)
            ->assertSee('Sign out')
            ->assertDontSee('تسجيل الخروج');

        $this->actingAs($admin)->post(route('admin.locale'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_login_errors_follow_the_panel_language(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => bcrypt('secret123')]);

        $this->post(route('admin.login'), ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.']);

        $this->post(route('admin.locale'), ['locale' => 'en']);
        $this->post(route('admin.login'), ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->get(route('admin.login.form'))->assertOk();

        // A user without any panel role.
        $this->post(route('admin.login'), ['email' => 'a@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors(['email' => 'You do not have access to the admin panel.']);
    }

    public function test_dashboard_metrics_come_from_the_controller(): void
    {
        $this->actingAs($this->tenantUser())->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => isset($stats['catalog']['categories']) && ! isset($stats['festival']))
            ->assertViewHas('m', fn ($m) => array_key_exists('revenue_today', $m) && isset($m['series']['labels']))
            ->assertViewHas('walletStats');
        $this->assertFalse(class_exists(\App\View\DashboardMetrics::class));
    }

    public function test_category_list_loads_event_counts_without_n_plus_one(): void
    {
        $admin = $this->tenantUser();
        $queries = function () use ($admin) {
            $count = 0;
            DB::listen(function ($q) use (&$count) {
                if (str_contains($q->sql, '"events"')) {
                    $count++;
                }
            });
            $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk();

            return $count;
        };
        $make = fn (int $i) => Category::create([
            'name' => "C$i", 'name_ar' => "ف$i", 'description' => 'd', 'description_ar' => 'd',
            'icon_name' => 'star', 'color_value' => 0xFF000000, 'display_order' => $i, 'is_active' => true,
        ]);
        foreach (range(1, 6) as $i) {
            $cat = $make($i);
            Event::create([
                'title' => "E$i", 'title_ar' => "ح$i", 'description' => 'd', 'description_ar' => 'd', 'category_id' => $cat->id,
                'start_time' => now(), 'end_time' => now()->addDay(), 'location' => 'x', 'location_ar' => 'x',
                'price' => 1, 'currency' => 'OMR', 'total_tickets' => 10, 'available_tickets' => 10, 'is_active' => true,
            ]);
        }

        $this->assertLessThanOrEqual(1, $queries());
    }

    public function test_announcement_types_match_between_form_controller_and_app(): void
    {
        $admin = $this->tenantUser();
        $html = $this->actingAs($admin)->get(route('admin.announcements.create'))->assertOk()->getContent();
        foreach (Announcement::TYPES as $type) {
            $this->assertStringContainsString('value="'.$type.'"', $html);
        }
        $this->assertStringNotContainsString('value="success"', $html);

        $payload = fn (string $type) => [
            'title' => 'T', 'title_ar' => 'ع', 'content' => 'c', 'content_ar' => 'م', 'type' => $type,
            'priority' => 1, 'is_active' => 1, 'start_datetime' => now()->format('Y-m-d H:i'),
        ];
        foreach (['celebration', 'emergency', 'info', 'warning'] as $type) {
            $this->actingAs($admin)->post(route('admin.announcements.store'), $payload($type))->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload('success'))->assertSessionHasErrors('type');

        // The DB column is an enum of the same set; the edit form offers it too.
        $existing = Announcement::where('type', 'celebration')->firstOrFail();
        $edit = $this->actingAs($admin)->get(route('admin.announcements.edit', $existing))->assertOk()->getContent();
        foreach (Announcement::TYPES as $type) {
            $this->assertStringContainsString('value="'.$type.'"', $edit);
        }
        $this->actingAs($admin)->put(route('admin.announcements.update', $existing), $payload('emergency'))->assertSessionHasNoErrors();
        $this->assertSame('emergency', $existing->fresh()->type);
        $this->actingAs($admin)->put(route('admin.announcements.update', $existing), $payload('alert'))->assertSessionHasErrors('type');
    }

    public function test_map_location_filter_ignores_empty_type_and_flash_is_translatable(): void
    {
        $admin = $this->tenantUser();
        MapLocation::create(['type' => 'parking', 'name' => 'P1', 'name_ar' => 'موقف الاختبار', 'description' => 'd', 'description_ar' => 'd', 'icon' => 'local_parking', 'color' => 0xFF607D8B, 'latitude' => 24, 'longitude' => 56, 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.map-locations.index', ['type' => '', 'search' => '']))->assertOk()->assertSee('موقف الاختبار');

        $this->actingAs($admin)->delete(route('admin.map-locations.destroy', MapLocation::first()))
            ->assertSessionHas('success', 'حُذف الموقع');
        $this->assertSame('Location deleted successfully', __('حُذف الموقع', [], 'en'));
    }

    public function test_logo_needs_chip_is_an_optional_tenant_setting(): void
    {
        $this->getJson('/api/v1/tenant/config')->assertOk()->assertJsonPath('data.branding.logo_needs_chip', null);

        $admin = $this->tenantUser();
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            's' => ['branding' => ['logo_needs_chip' => '0'], 'wallet' => ['promo_expiry_days' => '90']],
        ])->assertSessionHasNoErrors();

        $tenant = $this->tenant->fresh();
        $this->assertFalse($tenant->settings['branding']['logo_needs_chip']);
        $this->assertSame(90, $tenant->setting('wallet.promo_expiry_days'));
        $this->getJson('/api/v1/tenant/config')->assertJsonPath('data.branding.logo_needs_chip', false)
            ->assertJsonPath('data.wallet.promo_expiry_days', 90);

        // Back to auto / never.
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            's' => ['branding' => ['logo_needs_chip' => ''], 'wallet' => ['promo_expiry_days' => '']],
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->tenant->fresh()->setting('branding.logo_needs_chip'));
        $this->assertNull($this->tenant->fresh()->setting('wallet.promo_expiry_days'));
    }

    public function test_database_seeder_can_run_twice(): void
    {
        $this->seed();
        $counts = fn () => [
            Event::withoutTenantScope()->count(), Category::withoutTenantScope()->count(), \App\Models\Vendor::withoutTenantScope()->count(),
            \App\Models\Ticket::withoutTenantScope()->count(), Announcement::withoutTenantScope()->count(), User::count(),
            \App\Models\WalletTransaction::withoutTenantScope()->count(), \App\Models\PrivacyPolicy::withoutTenantScope()->count(),
        ];
        $first = $counts();
        $this->seed();
        $this->assertSame($first, $counts());

        // Generic legal text comes from tenant settings, not hard-coded.
        $policy = \App\Models\PrivacyPolicy::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first();
        $this->assertStringContainsString((string) $this->tenant->fresh()->setting('tenant.support.email'), $policy->content);
        $this->assertStringNotContainsString('{email}', $policy->content);
        $this->assertStringContainsString($this->tenant->fresh()->localizedName('en'), $policy->content);
    }
}
