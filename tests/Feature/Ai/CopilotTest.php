<?php

namespace Tests\Feature\Ai;

use App\Models\AiRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class CopilotTest extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function structured(array $data): array
    {
        return $this->textResponse(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function copilot_endpoints_are_permission_gated(): void
    {
        $this->fakeOpenAi([]);
        $viewer = $this->staff('viewer');           // view payments only
        $this->actingAs($viewer)->postJson(route('admin.ai.copilot.event-description'), ['title' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.ai.copilot.translate'), ['from' => 'ar', 'to' => 'en', 'fields' => ['title' => 'س']])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.ai.copilot.shorten'), ['text' => 'long text', 'locale' => 'en'])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.ai.copilot.announcement'), ['brief' => 'Parking B closed'])->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.gates.insights'))->assertForbidden();

        $finance = $this->staff('finance-manager'); // no manage events / announcements
        $this->actingAs($finance)->postJson(route('admin.ai.copilot.event-description'), ['title' => 'X'])->assertForbidden();

        // Guests are redirected to the login.
        auth()->logout();
        $this->post(route('admin.ai.copilot.translate'), [])->assertRedirect();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    #[Test]
    public function event_manager_gets_description_translation_and_shortening(): void
    {
        $manager = $this->staff('event-manager');
        $this->fakeOpenAi([
            $this->structured(['description_ar' => 'وصف عربي', 'description_en' => 'English description']),
            $this->structured(['title' => 'Kite Day', 'description' => 'Fly kites by the sea.']),
            $this->structured(['text' => 'Short.']),
        ]);

        $this->actingAs($manager)->postJson(route('admin.ai.copilot.event-description'), [
            'title' => 'Kite Day', 'title_ar' => 'يوم الطائرات الورقية', 'location' => 'Sohar Corniche', 'start_time' => '2027-01-08T15:00', 'price' => '0',
        ])->assertOk()->assertJsonPath('data.description_ar', 'وصف عربي')->assertJsonPath('data.description', 'English description');
        $input = $this->sent(0)['input'][0]['content'];
        $this->assertStringContainsString('Sohar Corniche', $input);
        $this->assertStringContainsString('"price":"free"', $input);
        $this->assertSame('gpt-test-smart', $this->sent(0)['model']);
        $this->assertSame('json_schema', $this->sent(0)['text']['format']['type']);

        $this->actingAs($manager)->postJson(route('admin.ai.copilot.translate'), [
            'from' => 'ar', 'to' => 'en', 'fields' => ['title' => 'يوم الطائرات الورقية', 'description' => 'طيّر طائرتك', 'bad key!' => 'x'],
        ])->assertOk()->assertJsonPath('data.fields', ['title' => 'Kite Day', 'description' => 'Fly kites by the sea.']);
        $this->assertSame(['title', 'description'], $this->sent(1)['text']['format']['schema']['required']);

        $this->actingAs($manager)->postJson(route('admin.ai.copilot.shorten'), ['text' => str_repeat('Long text. ', 30), 'locale' => 'en'])
            ->assertOk()->assertJsonPath('data.text', 'Short.');

        $this->actingAs($manager)->postJson(route('admin.ai.copilot.translate'), ['from' => 'ar', 'to' => 'ar', 'fields' => ['title' => 'x']])->assertStatus(422);

        $this->assertSame(['copilot.describe', 'copilot.translate', 'copilot.shorten'], AiRequest::orderBy('id')->pluck('feature')->all());
        $this->assertSame($manager->id, (int) AiRequest::value('user_id'));
    }

    #[Test]
    public function announcement_draft_needs_manage_announcements_and_respects_budget(): void
    {
        $content = $this->staff('content-manager');
        $this->fakeOpenAi([$this->structured(['title_ar' => 'إغلاق موقف B', 'title_en' => 'Parking B closed', 'content_ar' => 'يرجى استخدام موقف C.', 'content_en' => 'Please use Parking C.'])]);
        $this->actingAs($content)->postJson(route('admin.ai.copilot.announcement'), ['brief' => 'Parking B closed tonight, use C', 'type' => 'warning'])
            ->assertOk()->assertJsonPath('data.title', 'Parking B closed')->assertJsonPath('data.content_ar', 'يرجى استخدام موقف C.');

        $this->tenant->putSetting('ai.monthly_token_budget', 10)->save();
        $this->actingAs($content)->postJson(route('admin.ai.copilot.announcement'), ['brief' => 'Another brief here'])
            ->assertStatus(429)->assertJsonPath('code', 'AI_BUDGET_EXCEEDED');

        $this->tenant->putSetting('ai.monthly_token_budget', 2000000)->putSetting('ai.copilot_enabled', false)->save();
        $this->actingAs($content)->postJson(route('admin.ai.copilot.announcement'), ['brief' => 'Another brief here'])
            ->assertStatus(403)->assertJsonPath('code', 'AI_DISABLED');
    }

    #[Test]
    public function copilot_buttons_and_briefing_card_render_for_permitted_users(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.events.create'))->assertOk()
            ->assertSee('data-ai-copilot="event"', false)->assertSee('admin-ai.js', false)->assertSee(__('ai.admin.write_description', [], 'ar'));
        $this->actingAs($admin)->get(route('admin.announcements.create'))->assertOk()->assertSee('data-ai-copilot="announcement"', false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('data-ai-briefing', false);
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('id="sec-ai"', false)->assertSee('s[ai][monthly_token_budget]', false);

        // No key configured → no copilot UI.
        config(['services.openai.key' => '']);
        $this->actingAs($admin)->get(route('admin.events.create'))->assertOk()->assertDontSee('data-ai-copilot', false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-ai-briefing', false);

        // The API key is never rendered.
        config(['services.openai.key' => self::KEY]);
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertDontSee(self::KEY, false);
    }

    #[Test]
    public function tenant_admin_can_save_ai_settings(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk();
        $res = $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'],
            's' => [
                'modules' => ['ai_assistant' => '1', 'ai_plan' => '0'],
                'ai' => [
                    'assistant_name' => ['ar' => 'اسأل صحار', 'en' => 'Ask Sohar'],
                    'suggestions' => json_encode([['en' => 'Parking?', 'ar' => 'المواقف؟']]),
                    'monthly_token_budget' => '500000',
                    'copilot_enabled' => '0',
                    'venue_info' => ['en' => 'Gates open 16:00', 'ar' => 'تفتح البوابات 16:00'],
                ],
            ],
        ]);
        $res->assertSessionHasNoErrors();
        $t = $this->tenant->fresh();
        $this->assertTrue($t->moduleEnabled('ai_assistant'));
        $this->assertFalse($t->moduleEnabled('ai_plan'));
        $this->assertSame('Ask Sohar', $t->setting('ai.assistant_name.en'));
        $this->assertSame(500000, $t->setting('ai.monthly_token_budget'));
        $this->assertFalse($t->setting('ai.copilot_enabled'));
        $this->assertSame([['en' => 'Parking?', 'ar' => 'المواقف؟']], $t->setting('ai.suggestions'));
        $this->assertSame('Gates open 16:00', $t->setting('ai.venue_info.en'));

        $this->actingAs($admin)->put(route('admin.branding.update'), ['name' => ['en' => 'N', 'ar' => 'ش'], 's' => ['ai' => ['suggestions' => '[{"fr": "x"}]']]])
            ->assertSessionHasErrors('s.ai.suggestions');
    }

    #[Test]
    public function daily_briefing_is_cached_per_tenant_and_day_and_can_be_regenerated(): void
    {
        $viewer = $this->staff('viewer');
        $this->fakeOpenAi([
            $this->structured(['headline' => 'Quiet day so far.', 'bullets' => ['No ticket sales yet.'], 'actions' => ['Review pending refunds']]),
            $this->structured(['headline' => 'Fresh one.', 'bullets' => [], 'actions' => []]),
        ]);

        $this->actingAs($viewer)->getJson(route('admin.ai.briefing'))->assertOk()
            ->assertJsonPath('data.headline', 'Quiet day so far.')->assertJsonPath('data.cached', false)
            ->assertJsonStructure(['data' => ['date', 'bullets', 'actions', 'figures' => ['yesterday', 'today_so_far', 'refunds_pending'], 'generated_at']]);
        $this->actingAs($viewer)->getJson(route('admin.ai.briefing'))->assertOk()->assertJsonPath('data.cached', true);
        $this->assertSame(1, AiRequest::where('feature', 'briefing')->count());
        $this->assertStringContainsString('DATA:', $this->sent(0)['input'][0]['content']);

        $this->actingAs($viewer)->postJson(route('admin.ai.briefing.regenerate'))->assertOk()->assertJsonPath('data.headline', 'Fresh one.');
        $this->assertSame(2, AiRequest::where('feature', 'briefing')->count());

        $this->actingAs($this->staff('event-manager'))->getJson(route('admin.ai.briefing'))->assertOk(); // has view payments
        $this->actingAs($this->staff('content-manager'))->getJson(route('admin.ai.briefing'))->assertForbidden();
    }

    #[Test]
    public function gate_insights_aggregate_scans_and_send_no_personal_data(): void
    {
        if (! Schema::hasTable('gate_scans')) {
            $this->markTestSkipped('gate_scans table not present');
        }
        $admin = $this->staff('admin');
        $tid = $this->tenant->id;
        $gateA = DB::table('gates')->insertGetId(['tenant_id' => $tid, 'name' => json_encode(['en' => 'North', 'ar' => 'الشمالية']), 'code' => 'A', 'direction' => 'entry', 'allow_reentry' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $gateB = DB::table('gates')->insertGetId(['tenant_id' => $tid, 'name' => json_encode(['en' => 'South', 'ar' => 'الجنوبية']), 'code' => 'B', 'direction' => 'entry', 'allow_reentry' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->makeUser();
        $event = $this->makeEvent();
        $ticket = $this->makeTicket($user, $event, ['qr_code' => 'TKT-SECRET-QR']);
        // Tenant-local day; 19:xx Muscat = 15:xx UTC.
        $date = '2026-12-22';
        $at = fn (string $local) => \Carbon\Carbon::parse("$date $local", 'Asia/Muscat')->utc();
        $scan = function (int $gate, string $result, string $local, array $extra = []) use ($tid, $at) {
            static $n = 0;
            DB::table('gate_scans')->insert(array_merge([
                'tenant_id' => $tid, 'gate_id' => $gate, 'code_hash' => hash('sha256', 'code'.$n), 'result' => $result, 'direction' => 'in',
                'source' => 'camera', 'scanned_at' => $at($local), 'received_at' => $at($local), 'offline' => false, 'idempotency_key' => 'k'.(++$n), 'conflict' => false,
            ], $extra));
        };
        foreach (range(1, 6) as $i) {
            $scan($gateB, 'valid', '19:'.sprintf('%02d', $i * 5));
        }
        $scan($gateA, 'valid', '18:10', ['ticket_id' => $ticket->id]);
        $scan($gateB, 'valid', '18:20', ['ticket_id' => $ticket->id, 'conflict' => true, 'offline' => true]);   // same ticket, two gates
        foreach (range(1, 3) as $i) {
            $scan($gateA, 'invalid', '20:0'.$i, ['code_hash' => hash('sha256', 'forged')]);                // repeated invalid code
        }
        $scan($gateA, 'already_used', '20:30');
        // Another tenant's scans never count.
        $other = $this->makeTenant('other-org');
        DB::table('gate_scans')->insert(['tenant_id' => $other->id, 'gate_id' => null, 'code_hash' => 'x', 'result' => 'valid', 'direction' => 'in', 'source' => 'camera', 'scanned_at' => $at('19:00'), 'received_at' => $at('19:00'), 'offline' => false, 'idempotency_key' => 'other-1', 'conflict' => false]);

        $this->fakeOpenAi([$this->structured(['summary' => 'Gate B peaked 19:00–20:00.', 'recommendations' => ['Open a second lane at Gate B 19:00–20:00'], 'anomalies' => ['One code was rejected 3 times at Gate A']])]);

        $res = $this->actingAs($admin)->getJson(route('admin.gates.insights', ['date' => $date]))->assertOk()
            ->assertJsonPath('data.date', $date)->assertJsonPath('data.available', true)->assertJsonPath('data.ai', true)
            ->assertJsonPath('data.summary', 'Gate B peaked 19:00–20:00.')
            ->assertJsonPath('data.recommendations.0', 'Open a second lane at Gate B 19:00–20:00')
            ->assertJsonPath('data.aggregates.totals.scans', 12)
            ->assertJsonPath('data.aggregates.totals.admitted', 8)
            ->assertJsonPath('data.aggregates.totals.rejected', 4)
            ->assertJsonPath('data.aggregates.anomalies.repeated_invalid_codes', 1)
            ->assertJsonPath('data.aggregates.anomalies.max_repeats_of_one_invalid_code', 3)
            ->assertJsonPath('data.aggregates.anomalies.tickets_admitted_at_two_gates', 1)
            ->assertJsonPath('data.aggregates.anomalies.offline_conflicts', 1)
            ->assertJsonPath('data.aggregates.peaks.0.gate', 'Gate B (South)')
            ->assertJsonPath('data.aggregates.peaks.0.window', '19:00–20:00')
            ->assertJsonPath('data.aggregates.reject_reasons.invalid', 3);
        $this->assertSame(['18:00', '19:00', '20:00'], array_column($res->json('data.aggregates.by_hour'), 'hour'));

        $sent = $this->sent(0)['input'][0]['content'];
        $this->assertStringNotContainsString('TKT-SECRET-QR', $sent);
        $this->assertStringNotContainsString(hash('sha256', 'forged'), $sent);
        $this->assertStringNotContainsString('"ticket_id"', $sent);

        // Cached; refresh=1 regenerates.
        $this->actingAs($admin)->getJson(route('admin.gates.insights', ['date' => $date]))->assertOk()->assertJsonPath('data.cached', true);
        $this->assertSame(1, AiRequest::where('feature', 'ops_insights')->count());

        // No scans → no model call.
        $this->actingAs($admin)->getJson(route('admin.gates.insights', ['date' => '2026-12-01']))->assertOk()
            ->assertJsonPath('data.ai', false)->assertJsonPath('data.aggregates.totals.scans', 0);
        $this->assertSame(1, AiRequest::where('feature', 'ops_insights')->count());

        // AI off → aggregates still returned, with the reason.
        Cache::flush();
        $this->tenant->putSetting('ai.copilot_enabled', false)->save();
        $this->actingAs($admin)->getJson(route('admin.gates.insights', ['date' => $date]))->assertOk()
            ->assertJsonPath('data.ai', false)->assertJsonPath('data.ai_error.code', 'AI_DISABLED')->assertJsonPath('data.aggregates.totals.scans', 12);
    }
}
