<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Ai\BriefingService;
use App\Services\Ai\Concierge\ConciergeTools;
use App\Services\Ai\EventFacts;
use App\Services\Ai\Prompts;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * A tenant used for ticketing only switches the money and vendor modules off
 * in Branding & settings: the admin menu, the dashboard, the restaurants API
 * and the AI assistant follow, like the apps do.
 */
class ModuleSwitchesTest extends TestCase
{
    use PlatformHelpers;
    use RefreshDatabase;

    private const MONEY_AND_VENDORS = ['wallet', 'topup', 'deposits', 'points', 'refunds', 'vendors'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function ticketingOnly(): void
    {
        foreach (self::MONEY_AND_VENDORS as $module) {
            $this->tenant->putSetting("modules.$module", false);
        }
        $this->tenant->save();
    }

    #[Test]
    public function admin_menu_and_dashboard_leave_out_switched_off_modules(): void
    {
        $this->tenant->putSetting('modules.wallet', true)->putSetting('modules.vendors', true)->save();
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee(route('admin.wallets.index'), false)
            ->assertSee(route('admin.restaurants.index'), false)
            ->assertSee('شحن المحافظ اليوم');

        $this->ticketingOnly();
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertDontSee(route('admin.wallets.index'), false)
            ->assertDontSee(route('admin.deposits.index'), false)
            ->assertDontSee(route('admin.points.index'), false)
            ->assertDontSee(route('admin.refund-requests.index'), false)
            ->assertDontSee(route('admin.vendors.index'), false)
            ->assertDontSee(route('admin.restaurants.index'), false)
            ->assertDontSee('شحن المحافظ اليوم')
            ->assertDontSee('أحدث حركات المحافظ')
            ->assertSee(route('admin.tickets.index'), false)
            ->assertSee(route('admin.payments.index'), false);
    }

    #[Test]
    public function restaurants_api_follows_the_vendors_module(): void
    {
        $this->tenant->putSetting('modules.vendors', true)->save();
        $this->getJson('/api/v1/restaurants', $this->tenantHeaders())->assertOk();

        $this->ticketingOnly();
        foreach (['/api/v1/restaurants', '/api/v1/restaurants/featured', '/api/v1/restaurants/1'] as $url) {
            $this->getJson($url, $this->tenantHeaders())->assertStatus(403)->assertJsonPath('code', 'MODULE_DISABLED');
        }
    }

    #[Test]
    public function the_ai_assistant_has_no_wallet_tool_when_the_wallet_is_off(): void
    {
        $concierge = function () {
            $tenant = $this->tenant->fresh();

            return new ConciergeTools($tenant, new EventFacts($tenant, 'en'), $this->admin);
        };
        $tools = fn () => collect($concierge()->definitions())->pluck('name');

        $this->tenant->putSetting('modules.wallet', true)->save();
        $this->assertContains('my_wallet', $tools());

        $this->ticketingOnly();
        $this->assertNotContains('my_wallet', $tools());
        $this->assertContains('my_tickets', $tools());
        $this->assertSame(['error' => 'module_disabled'], $concierge()->execute('my_wallet', []));
    }

    #[Test]
    public function the_ai_briefing_and_prompt_leave_out_switched_off_modules(): void
    {
        $briefing = fn () => app(BriefingService::class)->aggregates($this->tenant->fresh());

        $this->tenant->putSetting('modules.wallet', true)->putSetting('modules.topup', true)
            ->putSetting('modules.refunds', true)->putSetting('modules.vendors', true)->save();
        $on = $briefing();
        $this->assertArrayHasKey('refunds_pending', $on);
        $this->assertArrayHasKey('topups', $on['today_so_far']);
        $this->assertArrayHasKey('vendor_sales', $on['today_so_far']);
        $this->assertStringContainsString('open_wallet', Prompts::concierge($this->tenant->fresh(), 'en', true));

        $this->ticketingOnly();
        $off = $briefing();
        $this->assertArrayNotHasKey('refunds_pending', $off);
        $this->assertArrayNotHasKey('topups', $off['today_so_far']);
        $this->assertArrayNotHasKey('vendor_sales', $off['today_so_far']);
        $this->assertArrayHasKey('ticket_sales', $off['today_so_far']);
        $prompt = Prompts::concierge($this->tenant->fresh(), 'en', true);
        $this->assertStringNotContainsString('open_wallet', $prompt);
        $this->assertStringNotContainsString('top_up', $prompt);
        $this->assertStringContainsString('publishes, tickets, announcements', $prompt);
    }
}
