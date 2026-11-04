<?php

namespace Tests\Feature\Satisfaction;

use App\Http\Controllers\Admin\SatisfactionController;
use App\Models\SatisfactionResponse;
use App\Models\User;
use App\Support\TenantTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Admin report «راضي» (docs/radi_satisfaction_contract.md §4) and tenant settings (§1). */
class SatisfactionAdminTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->tenant->putSetting('modules.radi', true)->save();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    private function response(array $attrs): SatisfactionResponse
    {
        static $n = 0;
        $n++;

        return $this->inTenant($this->tenant, fn () => SatisfactionResponse::create(array_merge([
            'score' => 4, 'locale' => 'ar', 'response_date' => TenantTime::today($this->tenant), 'dedupe_key' => 'test-'.$n,
        ], $attrs)));
    }

    private function seedAnswers(): array
    {
        $event = $this->makeEvent(null, ['title' => 'Opening Night', 'title_ar' => 'ليلة الافتتاح']);
        $contact = $this->makeUser('96891234567');
        $contact->forceFill(['name' => 'Salim'])->save();
        $silent = $this->makeUser('96899999999');
        $this->response(['score' => 5, 'event_id' => $event->id, 'aspects' => ['cleanliness' => 5, 'app' => 4], 'comment' => 'Wonderful evening', 'user_id' => $contact->id, 'contact_ok' => true]);
        $this->response(['score' => 2, 'event_id' => $event->id, 'aspects' => ['cleanliness' => 2], 'comment' => '=HYPERLINK("x")', 'user_id' => $silent->id, 'contact_ok' => false]);
        $this->response(['score' => 4]);
        $this->response(['score' => 1, 'comment' => 'Too crowded', 'response_date' => now()->subDays(40)->toDateString()]);

        return [$event, $contact, $silent];
    }

    #[Test]
    public function the_report_shows_kpis_breakdowns_and_comments(): void
    {
        [$event] = $this->seedAnswers();
        $this->actingAs($this->staff('admin'));

        $res = $this->get(route('admin.satisfaction.index'))->assertOk()
            ->assertSee('راضي لقياس رضا الزوار')
            ->assertSee('Wonderful evening')
            ->assertSee('ليلة الافتتاح')
            ->assertSee('Salim')
            ->assertSee('96891234567')
            ->assertDontSee('96899999999')     // no consent, no identity
            ->assertDontSee('Too crowded')      // outside the default 30 days
            ->assertSee('satChart', false);

        $kpis = $res->viewData('kpis');
        $this->assertSame(3, $kpis['responses']);
        $this->assertSame(3.7, $kpis['average']);
        $this->assertSame(67, $kpis['csat']);
        $this->assertSame(2, $kpis['comments']);
        $this->assertSame(1, $kpis['contact']);
        $this->assertSame([5 => 1, 4 => 1, 3 => 0, 2 => 1, 1 => 0], $res->viewData('distribution'));
        $aspects = collect($res->viewData('aspects'))->keyBy('key');
        $this->assertSame(3.5, $aspects['cleanliness']['average']);
        $this->assertSame(2, $aspects['cleanliness']['count']);
        $this->assertNull($aspects['organization']['average']);
        $perEvent = collect($res->viewData('perEvent'))->keyBy(fn ($r) => $r['event_id'] ?? 'none');
        $this->assertSame(2, $perEvent[$event->id]['responses']);
        $this->assertSame(1, $perEvent['none']['responses']);
        $this->assertCount(30, $res->viewData('trend')['labels']);

        // Score filter narrows the comments only; event / date filters the whole page.
        $this->get(route('admin.satisfaction.index', ['score' => 2]))->assertOk()->assertSee('HYPERLINK')->assertDontSee('Wonderful evening');
        $res = $this->get(route('admin.satisfaction.index', ['event' => 'none', 'from' => now()->subDays(60)->toDateString()]))->assertOk()->assertSee('Too crowded');
        $this->assertSame(2, $res->viewData('kpis')['responses']);
    }

    #[Test]
    public function csv_export_respects_consent_and_neutralizes_formulas(): void
    {
        $this->seedAnswers();
        $this->actingAs($this->staff('viewer'));

        $res = $this->get(route('admin.satisfaction.export'))->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('التقييم العام', $csv);
        $this->assertStringContainsString('Wonderful evening', $csv);
        $this->assertStringContainsString('96891234567', $csv);
        $this->assertStringNotContainsString('96899999999', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Too crowded', $csv);
        $this->assertSame(4, count(array_filter(explode("\n", trim($csv)))), 'header + 3 rows');

        $this->assertSame("'@x", SatisfactionController::cell('@x'));
        $this->assertSame('-3', SatisfactionController::cell('-3'));
    }

    #[Test]
    public function access_follows_the_view_satisfaction_permission(): void
    {
        foreach (['admin', 'viewer', 'event-manager', 'content-manager'] as $role) {
            $this->actingAs($this->staff($role));
            $this->get(route('admin.satisfaction.index'))->assertOk();
            $this->get(route('admin.dashboard'))->assertSee(route('admin.satisfaction.index'), false);
        }
        foreach (['ticket-manager', 'finance-manager'] as $role) {
            $this->actingAs($this->staff($role));
            $this->get(route('admin.satisfaction.index'))->assertForbidden();
            $this->get(route('admin.satisfaction.export'))->assertForbidden();
            $this->get(route('admin.dashboard'))->assertDontSee(route('admin.satisfaction.index'), false);
        }
    }

    #[Test]
    public function a_disabled_module_is_explained_on_the_page(): void
    {
        $this->tenant->putSetting('modules.radi', false)->save();
        $this->actingAs($this->staff('admin'));
        $this->get(route('admin.satisfaction.index'))->assertOk()->assertSee('استبيان الرضا غير مفعّل')->assertSee('لا توجد إجابات في هذه الفترة');
    }

    #[Test]
    public function the_report_is_tenant_scoped(): void
    {
        $other = $this->makeTenant('other-org', ['modules' => ['radi' => true]]);
        $this->inTenant($other, fn () => SatisfactionResponse::create([
            'score' => 1, 'comment' => 'Other tenant secret', 'locale' => 'en', 'response_date' => TenantTime::today($other), 'dedupe_key' => 'x',
        ]));
        $this->actingAs($this->staff('admin'));
        $res = $this->get(route('admin.satisfaction.index'))->assertOk()->assertDontSee('Other tenant secret');
        $this->assertSame(0, $res->viewData('kpis')['responses']);
    }

    #[Test]
    public function tenant_admins_edit_the_radi_settings(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('sec-radi', false)->assertSee('راضي لقياس رضا الزوار');

        $aspects = [['key' => 'parking', 'label' => ['ar' => 'المواقف', 'en' => 'Parking']], ['key' => 'food', 'label' => ['en' => 'Food']]];
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'],
            's' => ['modules' => ['radi' => '1'], 'radi' => [
                'title' => ['ar' => 'راضي', 'en' => 'Radi'],
                'intro' => ['ar' => 'قيّم تجربتك', 'en' => 'Rate your visit'],
                'external_url' => 'https://radi.example.om',
                'logo_url' => '',
                'aspects' => json_encode($aspects, JSON_UNESCAPED_UNICODE),
            ]],
        ])->assertSessionHasNoErrors();
        $t = $this->tenant->fresh();
        $this->assertSame('راضي', $t->setting('radi.title.ar'));
        $this->assertSame('https://radi.example.om', $t->setting('radi.external_url'));
        $this->assertSameJson($aspects, $t->setting('radi.aspects'));
        $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertJsonPath('data.radi.aspects.1.label.ar', '');

        foreach ([
            [['key' => 'Bad Key', 'label' => ['en' => 'x']]],
            [['key' => 'a', 'label' => ['en' => 'A']], ['key' => 'a', 'label' => ['en' => 'B']]],
            [['key' => 'a', 'label' => ['fr' => 'A']]],
            [['key' => 'a', 'label' => ['en' => '']]],
            ['not-a-row'],
        ] as $bad) {
            $this->actingAs($admin)->put(route('admin.branding.update'), [
                'name' => ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'],
                's' => ['radi' => ['aspects' => json_encode($bad)]],
            ])->assertSessionHasErrors('s.radi.aspects');
        }
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'],
            's' => ['radi' => ['external_url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors('s.radi.external_url');
    }

    #[Test]
    public function the_seeded_tenants_enable_radi(): void
    {
        $nb = \Database\Seeders\TenantSeeder::northBatinah();
        $this->assertTrue($nb['settings']['modules']['radi']);
        $this->assertSame('راضي', $nb['settings']['radi']['title']['ar']);
        $this->assertSame('', $nb['settings']['radi']['logo_url']);
        $this->assertTrue(\Database\Seeders\TenantSeeder::demo()['settings']['modules']['radi']);
    }
}
