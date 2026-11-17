<?php

namespace Tests\Feature\Gate;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Ticket type colours at the gate (docs/ticket_types_contract.md v1.2): the
 * manifest describes the types of its tickets, and the gate statistics and
 * the live dashboard count entries per type, each with its colour.
 */
class GateTicketTypeColorsTest extends GateTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    private function general(Event $event): EventTicketType
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $event->id)->where('key', 'general')->firstOrFail();
    }

    private function addType(Event $event, array $attrs): EventTicketType
    {
        return $this->inTenant($this->tenant, fn () => EventTicketType::create(array_merge([
            'event_id' => $event->id, 'name' => ['en' => ucfirst($attrs['key']), 'ar' => 'نوع '.$attrs['key']], 'category' => 'other',
            'price_minor' => 500, 'admits' => 1, 'per_order_max' => 10, 'visibility' => 'public', 'is_active' => true, 'sort' => 5,
        ], $attrs)));
    }

    private function typed(Event $event, EventTicketType $type, array $attrs = []): Ticket
    {
        return $this->makeTicket($event, array_merge(['ticket_type' => $type->key, 'ticket_type_id' => $type->id, 'max_check_ins' => $type->admits], $attrs));
    }

    #[Test]
    public function category_colours_follow_the_printed_ticket_convention(): void
    {
        $this->assertSame('#16A34A', EventTicketType::CATEGORY_COLORS['general']);
        $this->assertSame('#FACC15', EventTicketType::CATEGORY_COLORS['child']);
        // Max-contrast rule: dark text on yellow, white and green, white text on purple / gray.
        $this->assertSame('#111827', EventTicketType::textColorFor('#FACC15'));
        $this->assertSame('#111827', EventTicketType::textColorFor('#FFFFFF'));
        $this->assertSame('#111827', EventTicketType::textColorFor('#16A34A'));
        $this->assertSame('#FFFFFF', EventTicketType::textColorFor('#7C3AED'));
        $this->assertSame('#FFFFFF', EventTicketType::textColorFor('#6B7280'));
        $this->assertSame('#FFFFFF', EventTicketType::textColorFor('not a colour'));
        $this->assertTrue(EventTicketType::needsBorder('#fff'));
        $this->assertFalse(EventTicketType::needsBorder('#FACC15'));
        $this->assertSame('--type-color: #FACC15; --type-text: #111827;', EventTicketType::badgeStyle('#FACC15'));
        $this->assertSame('--type-color: #FFFFFF; --type-text: #111827; --type-ring: inset 0 0 0 1px #D1D5DB;', EventTicketType::badgeStyle('#FFFFFF'));
        $this->assertSame('--type-color: #16A34A; --type-text: #111827;', EventTicketType::badgeStyle(null));

        $event = $this->makeEvent();
        $this->assertSame('#16A34A', $this->general($event)->colorValue());
        $this->assertSame('#FACC15', $this->addType($event, ['key' => 'child', 'category' => 'child'])->colorValue());
        $this->assertSame('#FFFFFF', $this->addType($event, ['key' => 'vip', 'category' => 'vip', 'color' => '#FFFFFF'])->colorValue());

        // The editor offers the wristband swatches.
        $this->actingAs($this->admin('event-manager'))->get(route('admin.events.edit', $event))->assertOk()
            ->assertSee('id="ttSwatches"', false)->assertSee('data-tt-swatch="#FACC15"', false)->assertSee('data-tt-swatch="#FFFFFF"', false)
            ->assertSee('أصفر');
    }

    #[Test]
    public function the_manifest_describes_the_types_of_its_tickets(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $general = $this->general($event);
        $child = $this->addType($event, ['key' => 'child', 'category' => 'child', 'max_age' => 11]);
        $family = $this->addType($event, ['key' => 'family', 'category' => 'family', 'admits' => 4, 'color' => '#FFFFFF', 'requires_id' => true]);
        $this->addType($event, ['key' => 'unused', 'category' => 'vip']); // no ticket: not listed
        $g = $this->typed($event, $general);
        $f = $this->typed($event, $family);
        $legacy = $this->makeTicket($event); // no ticket type
        $this->typed($event, $child);

        $res = $this->gate('GET', '/api/v1/gate/manifest', [], $token)->assertOk();
        $data = $res->json('data');
        $types = collect($data['types'])->keyBy('key');
        $this->assertSame(['child', 'family', 'general'], $types->keys()->sort()->values()->all());
        $this->assertSame([
            'id' => $family->id, 'key' => 'family', 'event_id' => $event->id, 'name' => ['en' => 'Family', 'ar' => 'نوع family'],
            'category' => 'family', 'color' => '#FFFFFF', 'admits' => 4, 'requires_id' => true, 'min_age' => null, 'max_age' => null, 'validity' => 'date',
        ], $types['family']);
        $this->assertSame('#16A34A', $types['general']['color']);
        $this->assertSame(['en' => 'General admission', 'ar' => 'تذكرة عامة'], $types['general']['name']);
        $this->assertSame([11, '#FACC15'], [$types['child']['max_age'], $types['child']['color']]);

        $byHash = collect($data['tickets'])->keyBy('h');
        $h = fn ($t) => hash('sha256', $data['salt'].$t->qr_code);
        $this->assertSame($general->id, $byHash[$h($g)]['y']);
        $this->assertSame([$family->id, 4, 0], [$byHash[$h($f)]['y'], $byHash[$h($f)]['a'], $byHash[$h($f)]['u']]);
        $this->assertArrayNotHasKey('y', $byHash[$h($legacy)]);

        // Changing a type (its colour) changes the version: devices re-download.
        $this->travel(2)->seconds();
        $this->inTenant($this->tenant, fn () => $child->update(['color' => '#DC2626']));
        $again = $this->gate('GET', '/api/v1/gate/manifest', [], $token, ['If-None-Match' => $res->headers->get('ETag')])->assertOk();
        $this->assertNotSame($data['version'], $again->json('data.version'));
        $this->assertSame('#DC2626', collect($again->json('data.types'))->firstWhere('key', 'child')['color']);
        $this->gate('POST', '/api/v1/gate/heartbeat', ['app_version' => '1.0.1', 'queue_size' => 0], $token)
            ->assertJsonPath('data.manifest_version', $again->json('data.version'));
    }

    #[Test]
    public function gate_stats_split_entries_by_ticket_type(): void
    {
        $event = $this->makeEvent();
        $gate = $this->makeGate();
        [, $token] = $this->pairDevice($gate);
        [, $exitToken] = $this->pairDevice($this->makeGate(['direction' => 'exit']), null, 'Exit lane');
        $general = $this->general($event);
        $child = $this->addType($event, ['key' => 'child', 'category' => 'child']);
        $family = $this->addType($event, ['key' => 'family', 'category' => 'family', 'admits' => 4, 'color' => '#FFFFFF']);

        $g1 = $this->typed($event, $general);
        $this->scan($token, $g1->qr_code)->assertJsonPath('data.result', 'valid');
        $this->scan($token, $g1->qr_code)->assertJsonPath('data.result', 'already_used');
        $this->scan($token, $this->typed($event, $general)->qr_code);
        $c1 = $this->typed($event, $child);
        $this->scan($token, $c1->qr_code);
        $this->scan($token, $this->typed($event, $child)->qr_code);
        $this->scan($token, $this->typed($event, $child)->qr_code);
        $f1 = $this->typed($event, $family);
        foreach (range(1, 3) as $_) {
            $this->scan($token, $f1->qr_code)->assertJsonPath('data.result', 'valid'); // 3 of 4 people
        }
        $this->scan($token, $this->makeTicket($event)->qr_code); // legacy ticket without a type
        $this->scan($token, 'BOGUS');                             // no ticket: left out of by_type
        $this->scan($exitToken, $c1->qr_code);                    // other gate

        $res = $this->gate('GET', '/api/v1/gate/stats', [], $token)->assertOk()
            ->assertJsonPath('data.in_today', 9)->assertJsonPath('data.rejects_today', 2);
        $rows = collect($res->json('data.by_type'));
        $this->assertSame(['child', 'family', 'general', 'standard'], $rows->pluck('key')->all(), 'sorted by entries, then name');
        $this->assertSame([
            'type_id' => $child->id, 'key' => 'child', 'name' => ['en' => 'Child', 'ar' => 'نوع child'], 'name_text' => 'Child',
            'category' => 'child', 'color' => '#FACC15', 'in' => 3, 'out' => 0, 'rejects' => 0,
        ], $rows[0]);
        $this->assertSame([3, '#FFFFFF'], [$rows[1]['in'], $rows[1]['color']]);
        $this->assertSame([2, 0, 1], [$rows[2]['in'], $rows[2]['out'], $rows[2]['rejects']]);
        $this->assertSame([
            'type_id' => null, 'key' => 'standard', 'name' => ['en' => 'standard', 'ar' => 'standard'], 'name_text' => 'standard',
            'category' => 'general', 'color' => '#16A34A', 'in' => 1, 'out' => 0, 'rejects' => 0,
        ], $rows[3]);
        $this->assertSame($res->json('data.in_today') - 0, $rows->sum('in'));

        // Arabic names in Arabic; the exit lane only sees its own gate.
        $this->gate('GET', '/api/v1/gate/stats', [], $token, ['Accept-Language' => 'ar'])->assertJsonPath('data.by_type.0.name_text', 'نوع child');
        $exit = $this->gate('GET', '/api/v1/gate/stats', [], $exitToken)->json('data.by_type');
        $this->assertSame([['child', 0, 1, 0]], array_map(fn ($r) => [$r['key'], $r['in'], $r['out'], $r['rejects']], $exit));
        $this->gate('GET', '/api/v1/gate/stats?date=2020-01-01', [], $token)->assertJsonPath('data.by_type', []);
    }

    #[Test]
    public function the_live_dashboard_shows_entries_by_type_per_gate(): void
    {
        $admin = $this->admin();
        $event = $this->makeEvent();
        $other = $this->makeEvent(null, ['title' => 'Night market', 'title_ar' => 'السوق الليلي']);
        $a = $this->makeGate(['code' => 'A']);
        $b = $this->makeGate(['code' => 'B']);
        [, $ta] = $this->pairDevice($a, null, 'A1');
        [, $tb] = $this->pairDevice($b, null, 'B1');
        $child = $this->addType($event, ['key' => 'child', 'category' => 'child']);

        $this->scan($ta, $this->typed($event, $this->general($event))->qr_code);
        $this->scan($ta, $this->typed($event, $child)->qr_code);
        $this->scan($ta, $this->typed($event, $child)->qr_code);
        $this->scan($tb, $this->typed($other, $this->general($other))->qr_code);
        $this->scan($tb, 'BOGUS');

        $data = $this->actingAs($admin)->getJson(route('admin.gates.dashboard.data'))->assertOk()->json('data');
        $rows = collect($data['by_type']);
        $this->assertSame([2, 1, 1], $rows->pluck('in')->all());
        // Event titles in the panel language (Arabic by default).
        $this->assertSame(['child', '#FACC15', 'حفل'], [$rows[0]['key'], $rows[0]['color'], $rows[0]['event']]);
        $this->assertEqualsCanonicalizing(['حفل', 'السوق الليلي'], $rows->where('key', 'general')->pluck('event')->all());
        $this->assertSame(4, $rows->sum('in'));

        $gateA = collect($data['gates'])->firstWhere('code', 'A');
        $gateB = collect($data['gates'])->firstWhere('code', 'B');
        $this->assertSame([['type_id' => $child->id, 'key' => 'child', 'in' => 2], ['type_id' => $this->general($event)->id, 'key' => 'general', 'in' => 1]], $gateA['types']);
        $this->assertSame([['type_id' => $this->general($other)->id, 'key' => 'general', 'in' => 1]], $gateB['types']);

        $this->actingAs($admin)->get(route('admin.gates.dashboard'))->assertOk()
            ->assertSee('id="gTypes"', false)->assertSee('الدخول حسب نوع التذكرة');
        $this->actingAs($admin)->getJson(route('admin.gates.dashboard.data', ['date' => '2020-01-01']))->assertOk()
            ->assertJsonPath('data.by_type', []);
    }
}
