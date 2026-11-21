<?php

namespace Tests\Feature\Gate;

use App\Models\GateScan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateManifestService;
use App\Services\Gate\OfflineSaleCodes;
use App\Services\Gate\TicketSignature;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** docs/gate_signed_tickets.md (MSR-400, MSR-401). */
class GateSignedTicketsTest extends GateTestCase
{
    private const VECTOR_CODE = 'TKT-2026-000123-AB12CD34EF56.S1.123.25.38.1.A.20261121.NTIODSMBHI2WJIZXO6SGNHLGPK2NQONAWZIKQD2W4UJG7PPLP5AA2BGL77LFWEGR47YIKPSDG5OH2MSEK6QVSDJIIZGDNIAEXJVHGCA';

    protected function tearDown(): void
    {
        TicketSignature::flush();
        parent::tearDown();
    }

    #[Test]
    public function the_contract_test_vector_signs_and_verifies(): void
    {
        config(['app.key' => 'base64:dGVzdC1rZXktZm9yLXNpZ25lZC10aWNrZXRzLTMyYg==']);
        $this->assertSame('75ce4e514030692d0e1e32a5bf20c634e67c792080039dae4303a0696bac4694', bin2hex(TicketSignature::seed(7, 1, '20261121')));
        $this->assertSame('2GFIQo+ioXUCqFwRWI/b0ee4LGx0QGHTLpQMbmiIMNo=', base64_encode(TicketSignature::publicKey(7, 1, '20261121')));

        $tenant = new Tenant(['settings' => []]);
        $tenant->id = 7;
        $p = TicketSignature::verify($tenant, self::VECTOR_CODE);
        $this->assertNotNull($p);
        $this->assertSame(['base' => 'TKT-2026-000123-AB12CD34EF56', 'ticket_id' => 123, 'event_id' => 25, 'type_id' => 38, 'admits' => 1, 'validity' => 'A', 'key_day' => '20261121'],
            array_intersect_key($p, array_flip(['base', 'ticket_id', 'event_id', 'type_id', 'admits', 'validity', 'key_day'])));
        $this->assertNull(TicketSignature::verify($tenant, str_replace('.38.1.A.', '.38.4.A.', self::VECTOR_CODE)), 'admits 1 → 4 breaks the signature');
        $tenant->putSetting('gate.signed_keys_epoch', 2);
        $this->assertNull(TicketSignature::verify($tenant, self::VECTOR_CODE), 'another key generation');

        $this->assertSame('TKT-2026-000123-AB12CD34EF56', TicketSignature::base(self::VECTOR_CODE));
        $this->assertSame('TKT-2026-000123-AB12CD34EF56', TicketSignature::base('TKT-2026-000123-AB12CD34EF56'));
        $this->assertNull(TicketSignature::parse('OFF-A-9-0007.12.1.20261118.K3QZ7M2XPA4D'));
    }

    #[Test]
    public function tickets_carry_their_signed_scan_code_and_offline_sale_codes_stay_as_they_are(): void
    {
        $event = $this->makeEvent();
        $ticket = $this->makeTicket($event, ['booking_date' => null, 'validity' => 'any_day', 'max_check_ins' => 3]);
        $code = $ticket->scanCode();

        $this->assertMatchesRegularExpression('/^[A-Z0-9\-.]+$/', $code, 'QR alphanumeric characters only');
        $p = TicketSignature::verify($this->tenant, $code);
        $this->assertSame([$ticket->qr_code, $ticket->id, $event->id, 3, 'A'], [$p['base'], $p['ticket_id'], $p['event_id'], $p['admits'], $p['validity']]);
        $this->assertSame(str_replace('-', '', GateDay::dateFor(Carbon::parse($ticket->created_at), $this->tenant)), $p['key_day']);
        $this->assertSame($code, $ticket->scanCode(), 'the same QR every time it is shown');

        $dated = $this->makeTicket($event);
        $this->assertSame('D'.str_replace('-', '', GateDay::today()), TicketSignature::parse($dated->scanCode())['validity']);

        $offline = $this->makeTicket($event, ['qr_code' => OfflineSaleCodes::make($this->tenant, 'A', 9, 7, 0, 1, GateDay::today())]);
        $this->assertSame($offline->qr_code, $offline->scanCode());
    }

    #[Test]
    public function the_gate_and_the_ticket_page_accept_the_signed_code(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $ticket = $this->makeTicket($event);

        $this->scan($token, $ticket->scanCode())->assertOk()->assertJsonPath('data.result', 'valid');
        $scan = GateScan::withoutTenantScope()->latest('id')->firstOrFail();
        $this->assertSame($ticket->id, $scan->ticket_id);
        $this->assertSame(app(GateManifestService::class)->hashFor($this->tenant, GateDay::today(), $ticket->qr_code), $scan->code_hash, 'hashed by its base code');
        $this->scan($token, $ticket->qr_code)->assertJsonPath('data.result', 'already_used');

        $this->get('/ticket/'.$ticket->scanCode())->assertOk();
    }

    #[Test]
    public function v1_lists_only_tickets_that_can_still_enter_and_re_entry_gates_keep_todays_used(): void
    {
        $event = $this->makeEvent();
        $active = $this->makeTicket($event);
        $old = $this->makeTicket($event, ['status' => 'used', 'check_in_count' => 1, 'used_at' => now()->subDays(3)]);
        $today = $this->makeTicket($event, ['status' => 'used', 'check_in_count' => 1, 'used_at' => now()]);
        $cancelled = $this->makeTicket($event, ['status' => 'cancelled']);
        $m = app(GateManifestService::class);
        $hashes = fn (array $list) => array_column($list['tickets'], 'h');
        $h = fn ($t) => $m->hashFor($this->tenant, GateDay::today(), $t->qr_code);

        $plain = $this->inTenant($this->tenant, fn () => $m->build($this->tenant, GateDay::today(), $this->makeGate(['event_id' => $event->id])));
        $this->assertEqualsCanonicalizing([$h($active), $h($cancelled)], $hashes($plain));
        $this->assertArrayNotHasKey('i', $plain['tickets'][0]);
        $this->assertArrayNotHasKey('status', $plain);

        $reentry = $this->inTenant($this->tenant, fn () => $m->build($this->tenant, GateDay::today(), $this->makeGate(['event_id' => $event->id, 'allow_reentry' => true])));
        $this->assertEqualsCanonicalizing([$h($active), $h($today), $h($cancelled)], $hashes($reentry), 'used today stays for re-entry; used days ago does not');
        $this->assertNotContains($h($old), $hashes($reentry));

        $exit = $this->inTenant($this->tenant, fn () => $m->build($this->tenant, GateDay::today(), $this->makeGate(['event_id' => $event->id, 'direction' => 'both'])));
        $this->assertContains($h($today), $hashes($exit), 'a visitor who entered today can leave through an offline exit gate');
    }

    #[Test]
    public function v2_adds_ticket_ids_a_status_list_and_a_cursor(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $active = $this->makeTicket($event);
        $used = $this->makeTicket($event, ['status' => 'used', 'check_in_count' => 1]);
        $cancelled = $this->makeTicket($event, ['status' => 'cancelled']);

        $res = $this->gate('GET', '/api/v1/gate/manifest?v=2', [], $token)->assertOk();
        $data = $res->json('data');
        $this->assertSame([$active->id], array_column($data['tickets'], 'i'), 'only tickets that can still enter');
        $this->assertStringEndsWith('-v2"', $res->headers->get('ETag'));
        $this->assertNotNull(Carbon::parse($data['cursor']));

        $bits = base64_decode($data['status']['bits']);
        $code = fn (int $id) => (ord($bits[($id - $data['status']['from']) >> 2]) >> ((($id - $data['status']['from']) & 3) * 2)) & 3;
        $this->assertSame([0, 1, 2], [$code($active->id), $code($used->id), $code($cancelled->id)]);
        $this->assertSame($cancelled->id - $data['status']['from'] + 1, $data['status']['count']);

        // A v1 ETag never answers a v2 request with 304 (and the other way round).
        $v1 = $this->gate('GET', '/api/v1/gate/manifest', [], $token)->assertOk();
        $this->gate('GET', '/api/v1/gate/manifest?v=2', [], $token, ['If-None-Match' => $v1->headers->get('ETag')])->assertOk();
        $this->gate('GET', '/api/v1/gate/manifest?v=2', [], $token, ['If-None-Match' => $res->headers->get('ETag')])->assertStatus(304);
    }

    #[Test]
    public function the_status_list_packs_two_bits_per_ticket_least_significant_first(): void
    {
        $s = GateManifestService::statusBits([10 => 0, 11 => 1, 12 => 2, 13 => 3, 14 => 1]);
        $this->assertSame(['from' => 10, 'count' => 5], array_intersect_key($s, ['from' => 0, 'count' => 0]));
        $this->assertSame([0b11100100, 0b00000001], array_values(unpack('C*', base64_decode($s['bits']))));
        $this->assertSame(['from' => 0, 'count' => 0, 'bits' => ''], GateManifestService::statusBits([]));
    }

    #[Test]
    public function changes_since_a_cursor_carry_only_the_changed_tickets(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $a = $this->makeTicket($event);
        $b = $this->makeTicket($event);
        $this->travel(1)->minutes();
        $cursor = $this->gate('GET', '/api/v1/gate/manifest?v=2', [], $token)->json('data.cursor');

        $this->travel(1)->minutes();
        $this->scan($token, $a->scanCode())->assertJsonPath('data.result', 'valid');
        $sold = $this->makeTicket($event);
        $this->travel(1)->minutes();

        $changes = $this->gate('GET', '/api/v1/gate/manifest/changes?since='.urlencode($cursor), [], $token)->assertOk()->json('data');
        $this->assertFalse($changes['full']);
        $byId = collect($changes['tickets'])->keyBy('i');
        $this->assertEqualsCanonicalizing([$a->id, $sold->id], $byId->keys()->all(), 'the untouched ticket is not sent');
        $this->assertSame('used', $byId[$a->id]['s']);
        $this->assertSame('active', $byId[$sold->id]['s']);
        $this->assertArrayHasKey('types', $changes);
        $this->assertNotContains($b->id, $byId->keys()->all());

        // Nothing changed since the new cursor (the changes are older than the 15 s overlap).
        $this->assertSame([], $this->gate('GET', '/api/v1/gate/manifest/changes?since='.urlencode($changes['cursor']), [], $token)->json('data.tickets'));

        // Too old, missing or another day: download the whole list.
        $this->assertTrue($this->gate('GET', '/api/v1/gate/manifest/changes?since='.urlencode(now()->subHours(7)->toIso8601String()), [], $token)->json('data.full'));
        $this->assertTrue($this->gate('GET', '/api/v1/gate/manifest/changes', [], $token)->json('data.full'));
        $tomorrow = Carbon::parse(GateDay::today())->addDay()->toDateString();
        $this->assertTrue($this->gate('GET', "/api/v1/gate/manifest/changes?date=$tomorrow&since=".urlencode($changes['cursor']), [], $token)->json('data.full'));
    }

    #[Test]
    public function the_session_sends_the_mode_and_the_public_keys_only_when_on(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $this->travel(-3)->days();
        $older = $this->makeTicket($event, ['booking_date' => null, 'validity' => 'any_day']);
        $this->travelBack();

        $off = $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()->json('data.settings.signed_check');
        $this->assertSame(['mode' => 'off', 'from' => '18:30', 'to' => '21:00', 'keys' => []], $off);

        $this->tenant->putSetting('gate.signed_check', 'window')->putSetting('gate.signed_check_from', '19:00')->save();
        $on = $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()->json('data.settings.signed_check');
        $this->assertSame(['window', '19:00', '21:00'], [$on['mode'], $on['from'], $on['to']]);
        $first = str_replace('-', '', GateDay::dateFor(Carbon::parse($older->created_at), $this->tenant));
        $last = Carbon::parse(GateDay::today())->addDays(TicketSignature::KEYS_AHEAD)->format('Ymd');
        // JSON object keys (PHP decodes these numeric keys as integers).
        $this->assertSame($first, (string) array_key_first($on['keys']));
        $this->assertSame($last, (string) array_key_last($on['keys']));

        // A gate checks a ticket with the public key alone.
        $p = TicketSignature::parse($older->fresh()->scanCode());
        $this->assertTrue(sodium_crypto_sign_verify_detached(TicketSignature::base32Decode($p['sig']), $p['body'], base64_decode($on['keys'][$p['key_day']])));

        // Field test 2026-11-21: an open app must notice the change. The heartbeat's
        // session_version follows tenant settings (and the key epoch).
        $hb = fn () => $this->gate('POST', '/api/v1/gate/heartbeat', ['app_version' => '15.1.1'], $token)->json('data.session_version');
        $v1 = $hb();
        $this->assertSame($v1, $this->gate('GET', '/api/v1/gate/session', [], $token)->json('data.session_version'));
        $this->assertSame($v1, $hb(), 'unchanged while nothing changes');
        $this->travel(1)->seconds();
        $this->tenant->putSetting('gate.signed_keys_epoch', 2)->save();
        $this->assertNotSame($v1, $hb());
    }

    #[Test]
    public function the_admin_sets_the_mode_and_window_and_changes_the_keys(): void
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'admin');
        $ticket = $this->makeTicket();
        $before = $ticket->scanCode();

        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('id="sec-gate-signed"', false);
        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            's' => ['gate' => ['signed_check' => 'window', 'signed_check_from' => '18:30', 'signed_check_to' => '21:00']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('window', $this->tenant->fresh()->setting('gate.signed_check'));

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'], 's' => ['gate' => ['signed_check' => 'sometimes', 'signed_check_from' => '25:00']],
        ])->assertSessionHasErrors(['s.gate.signed_check', 's.gate.signed_check_from']);

        $this->actingAs($admin)->post(route('admin.branding.signing-keys'))->assertRedirect();
        $tenant = $this->tenant->fresh();
        $this->assertSame(2, TicketSignature::epoch($tenant));
        TicketSignature::flush();
        $after = $ticket->fresh()->scanCode($tenant);
        $this->assertNotSame($before, $after);
        $this->assertNull(TicketSignature::verify($tenant, $before), 'old signatures no longer verify');
        $this->assertNotNull(TicketSignature::verify($tenant, $after));
    }
}
