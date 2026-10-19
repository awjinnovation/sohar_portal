<?php

namespace Tests\Feature\Gate;

use App\Models\GateScan;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateManifestService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class GateScanTest extends GateTestCase
{
    #[Test]
    public function a_valid_ticket_is_admitted_once_and_marked_used(): void
    {
        [$device, $token] = $this->pairDevice($this->makeGate(['code' => 'A', 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة A']]));
        $ticket = $this->makeTicket();

        $res = $this->scan($token, $ticket->qr_code, ['source' => 'hardware'])->assertOk()
            ->assertJsonPath('data.result', 'valid')
            ->assertJsonPath('data.admit', true)
            ->assertJsonPath('data.ticket.number', $ticket->ticket_number)
            ->assertJsonPath('data.ticket.holder_name', 'Salim Al Harthy')
            ->assertJsonPath('data.ticket.type', 'standard')
            ->assertJsonPath('data.ticket.event_title', 'Concert')
            ->assertJsonPath('data.ticket.valid_date', GateDay::today())
            ->assertJsonPath('data.ticket.quantity_label', '1 person')
            ->assertJsonPath('data.counts.in_today', 1)
            ->assertJsonPath('data.counts.out_today', 0)
            ->assertJsonPath('data.message.en', 'Welcome — entry granted')
            ->assertJsonPath('data.message.ar', 'أهلًا بك، الدخول مسموح');
        $this->assertNotNull($res->json('data.ticket.used_at'));

        $ticket->refresh();
        $this->assertSame('used', $ticket->status);
        $this->assertSame(1, $ticket->check_in_count);
        $this->assertStringContainsString('Lane 1', $ticket->validated_by);
        $this->assertSame('Main Gate A', $ticket->validation_location);

        $scan = GateScan::sole();
        $this->assertSame(['valid', 'in', 'hardware', false, false], [$scan->result, $scan->direction, $scan->source, $scan->offline, $scan->conflict]);
        $this->assertSame($device->id, $scan->device_id);
        $this->assertSame($ticket->id, $scan->ticket_id);
        $this->assertSame(app(GateManifestService::class)->hashFor($this->tenant, GateDay::today(), $ticket->qr_code), $scan->code_hash);
        $this->assertSame('hardware', $device->fresh()->scanner_source);

        // second scan → already_used with when / where, localized
        $this->scan($token, $ticket->qr_code, ['scanned_at' => now()->addMinute()->toIso8601String()])->assertOk()
            ->assertJsonPath('data.result', 'already_used')
            ->assertJsonPath('data.admit', false)
            ->assertJsonPath('data.counts.in_today', 1)
            ->assertJson(fn ($json) => $json->where('data.message.en', fn ($m) => str_starts_with($m, 'Ticket already used — used at ') && str_contains($m, '(Main Gate A)'))->etc());
        $this->assertSame(1, $ticket->fresh()->check_in_count);
    }

    #[Test]
    public function every_rejection_reason_has_its_result(): void
    {
        // mid-afternoon in Muscat, so "+3 h" stays on the same gate day
        $this->travelTo(\Carbon\Carbon::parse('2026-10-18 10:00:00', 'UTC'));
        $this->seedRoles();
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent(null, ['title' => 'Other Show']);
        $gate = $this->makeGate(['event_id' => $event->id]);
        [, $token] = $this->pairDevice($gate);
        $vipGate = $this->makeGate(['ticket_types' => ['vip']]);
        [, $vipToken] = $this->pairDevice($vipGate, null, 'VIP lane');

        $expect = function (string $tok, string $code, string $result, ?string $en = null, array $extra = []) {
            $res = $this->scan($tok, $code, $extra)->assertOk()->assertJsonPath('data.result', $result)->assertJsonPath('data.admit', false);
            if ($en) {
                $this->assertStringStartsWith($en, $res->json('data.message.en'));
            }
            $this->assertNotEmpty($res->json('data.message.ar'));

            return $res;
        };

        // invalid: unknown code, and the sequential ticket number is not accepted from a camera
        $expect($token, 'TKT-2026-999999-NOPE', 'invalid', 'Invalid ticket')->assertJsonPath('data.ticket', null);
        $plain = $this->makeTicket($event);
        $expect($token, $plain->ticket_number, 'invalid');

        // cancelled / refunded
        $expect($token, $this->makeTicket($event, ['status' => 'cancelled'])->qr_code, 'cancelled', 'Ticket cancelled');
        $refundedTicket = $this->makeTicket($event, ['status' => 'cancelled', 'transaction_id' => 'TX-REF-1']);
        Payment::create(['user_id' => $refundedTicket->user_id, 'transaction_id' => 'TX-REF-1', 'payment_type' => 'ticket', 'method' => 'gateway',
            'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'refunded']);
        $expect($token, $refundedTicket->qr_code, 'refunded', 'Ticket refunded');

        // wrong event (gate dedicated to another event)
        $expect($token, $this->makeTicket($otherEvent)->qr_code, 'wrong_event', 'This ticket is for another event');

        // wrong gate (VIP gate, standard ticket); a VIP ticket passes
        $expect($vipToken, $this->makeTicket($event)->qr_code, 'wrong_gate', 'Wrong gate — please use another gate — this gate accepts: vip');
        $this->scan($vipToken, $this->makeTicket($event, ['ticket_type' => 'vip'])->qr_code)->assertJsonPath('data.result', 'valid');

        // wrong date: tomorrow's / yesterday's ticket, expired status, ended event
        $tomorrow = now()->addDay();
        $expect($token, $this->makeTicket($event, ['booking_date' => GateDay::dateFor($tomorrow)])->qr_code, 'wrong_date', 'This ticket is not valid today — valid on '.GateDay::dateFor($tomorrow));
        $expect($token, $this->makeTicket($event, ['booking_date' => GateDay::dateFor(now()->subDay())])->qr_code, 'wrong_date');
        $expect($token, $this->makeTicket($event, ['status' => 'expired'])->qr_code, 'wrong_date', 'This ticket is not valid today — the ticket has expired');
        $ended = $this->makeEvent(null, ['start_time' => now()->subDays(2), 'end_time' => now()->subMinutes(30)]);
        [, $anyToken] = $this->pairDevice(null, null, 'Any lane');
        $expect($anyToken, $this->makeTicket($ended, ['booking_date' => null])->qr_code, 'wrong_date', 'This ticket is not valid today — the event has ended');

        // not yet open: the event starts in 3 h (doors open 60 min before, tenant-local time)
        $later = $this->makeEvent(null, ['start_time' => now()->addHours(3), 'end_time' => now()->addHours(8)]);
        $res = $expect($anyToken, $this->makeTicket($later, ['booking_date' => GateDay::dateFor(now()->addHours(3))])->qr_code, 'not_yet_open', 'Gates are not open yet — doors open at ');
        $this->assertStringContainsString(now()->addHours(2)->setTimezone('Asia/Muscat')->format('H:i'), $res->json('data.message.en'));
        // … and inside the early-entry window it is valid
        $soon = $this->makeEvent(null, ['start_time' => now()->addMinutes(30), 'end_time' => now()->addHours(5)]);
        $this->scan($anyToken, $this->makeTicket($soon, ['booking_date' => GateDay::dateFor(now()->addMinutes(30))])->qr_code)->assertJsonPath('data.result', 'valid');

        // nothing was admitted by the rejections
        $this->assertSame(0, Ticket::where('check_in_count', '>', 0)->whereNotIn('ticket_type', ['vip'])->whereNot('event_id', $soon->id)->count());
        $this->assertSame(13, GateScan::count());
    }

    #[Test]
    public function the_same_ticket_at_two_gates_is_admitted_once(): void
    {
        [, $tokenA] = $this->pairDevice($this->makeGate(['code' => 'A']), null, 'A1');
        [, $tokenB] = $this->pairDevice($this->makeGate(['code' => 'B']), null, 'B1');
        $ticket = $this->makeTicket();

        $results = [
            $this->scan($tokenA, $ticket->qr_code)->json('data.result'),
            $this->scan($tokenB, $ticket->qr_code)->json('data.result'),
            $this->scan($tokenA, $ticket->qr_code)->json('data.result'),
        ];
        $this->assertSame(['valid', 'already_used', 'already_used'], $results);
        $this->assertSame(1, $ticket->fresh()->check_in_count);
        $this->assertSame(1, GateScan::where('result', 'valid')->count());
    }

    #[Test]
    public function a_retried_scan_with_the_same_idempotency_key_replays_the_result(): void
    {
        [, $token] = $this->pairDevice();
        [, $other] = $this->pairDevice(null, null, 'Lane 2');
        $ticket = $this->makeTicket();
        $key = (string) Str::uuid();

        $this->scan($token, $ticket->qr_code, ['idempotency_key' => $key])->assertJsonPath('data.result', 'valid')->assertJsonPath('data.replay', false);
        $this->scan($token, $ticket->qr_code, ['idempotency_key' => $key])->assertOk()->assertJsonPath('data.result', 'valid')->assertJsonPath('data.replay', true);
        $this->assertSame(1, GateScan::count());
        $this->assertSame(1, $ticket->fresh()->check_in_count);

        // the key of another device is a conflict, not a replay
        $this->scan($other, $ticket->qr_code, ['idempotency_key' => $key])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->scan($token, $ticket->qr_code, ['idempotency_key' => ''])->assertStatus(422);
    }

    #[Test]
    public function reentry_follows_the_gate_rules(): void
    {
        $noReentry = $this->makeGate(['code' => 'A', 'allow_reentry' => false]);
        $reentry = $this->makeGate(['code' => 'B', 'allow_reentry' => true]);
        $exit = $this->makeGate(['code' => 'X', 'direction' => 'exit']);
        $both = $this->makeGate(['code' => 'D', 'direction' => 'both', 'allow_reentry' => true]);
        [, $a] = $this->pairDevice($noReentry, null, 'A');
        [, $b] = $this->pairDevice($reentry, null, 'B');
        [, $x] = $this->pairDevice($exit, null, 'X');
        [, $d] = $this->pairDevice($both, null, 'D');
        $ticket = $this->makeTicket();

        $this->scan($b, $ticket->qr_code)->assertJsonPath('data.result', 'valid');
        $this->scan($a, $ticket->qr_code)->assertJsonPath('data.result', 'already_used');
        $this->scan($b, $ticket->qr_code)->assertJsonPath('data.result', 'reentry_ok')->assertJsonPath('data.admit', true)
            ->assertJsonPath('data.message.en', 'Re-entry allowed');

        // exits are always let through and counted out (direction forced by the gate)
        $this->scan($x, $ticket->qr_code, ['direction' => 'in'])->assertJsonPath('data.result', 'valid')->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.message.en', 'Exit recorded')->assertJsonPath('data.counts.out_today', 1);
        // a two-way gate takes the direction from the device
        $this->scan($d, $ticket->qr_code, ['direction' => 'out'])->assertJsonPath('data.direction', 'out');
        $this->scan($d, $ticket->qr_code, ['direction' => 'in'])->assertJsonPath('data.result', 'reentry_ok');
        $this->assertSame(1, $ticket->fresh()->check_in_count);

        // with gate.reentry_requires_exit the last movement must be an exit
        $this->tenant->putSetting('gate.reentry_requires_exit', true)->save();
        $this->scan($b, $ticket->qr_code)->assertJsonPath('data.result', 'already_used');
        $this->scan($x, $ticket->qr_code)->assertJsonPath('data.result', 'valid');
        $this->scan($b, $ticket->qr_code)->assertJsonPath('data.result', 'reentry_ok');

        // an unused ticket at a re-entry gate is a normal first entry
        $this->scan($b, $this->makeTicket()->qr_code)->assertJsonPath('data.result', 'valid');
    }

    #[Test]
    public function a_device_never_sees_tickets_of_another_tenant(): void
    {
        $other = $this->makeTenant('other');
        [, $tokenB] = $this->pairDevice(null, $other);
        $ticketA = $this->makeTicket();

        $this->scan($tokenB, $ticketA->qr_code)->assertOk()->assertJsonPath('data.result', 'invalid')->assertJsonPath('data.ticket', null);
        $this->assertSame(0, $ticketA->fresh()->check_in_count);
        $this->assertSame($other->id, GateScan::withoutTenantScope()->sole()->tenant_id);
        $this->assertNull(GateScan::withoutTenantScope()->sole()->ticket_id);
    }

    #[Test]
    public function manual_entry_accepts_the_ticket_number_and_the_public_ticket_url(): void
    {
        [, $token] = $this->pairDevice();
        $t1 = $this->makeTicket();
        $t2 = $this->makeTicket();

        $this->scan($token, strtolower($t1->ticket_number), ['source' => 'manual'])->assertJsonPath('data.result', 'valid');
        $this->scan($token, 'https://events.example/ticket/'.$t2->qr_code)->assertJsonPath('data.result', 'valid');
    }

    #[Test]
    public function a_closed_gate_refuses_scans(): void
    {
        $gate = $this->makeGate();
        [, $token] = $this->pairDevice($gate);
        $gate->update(['active' => false]);

        $this->scan($token, $this->makeTicket()->qr_code)->assertStatus(409)->assertJsonPath('code', 'GATE_INACTIVE');
    }

    #[Test]
    public function the_first_entry_awards_check_in_points_when_enabled(): void
    {
        $this->tenant->putSetting('points.earn_on_checkin', true)->putSetting('points.checkin_points', 50)->save();
        $gate = $this->makeGate(['allow_reentry' => true]);
        [, $token] = $this->pairDevice($gate);
        $ticket = $this->makeTicket();

        $this->scan($token, $ticket->qr_code)->assertJsonPath('data.result', 'valid');
        $this->scan($token, $ticket->qr_code)->assertJsonPath('data.result', 'reentry_ok');

        $account = app(\App\Services\Wallet\PointsService::class)->accountFor($ticket->user, $this->tenant);
        $this->assertSame(50, $account->balance);
    }

    #[Test]
    public function the_legacy_staff_endpoints_use_the_same_rules_and_are_logged(): void
    {
        $this->seedRoles();
        $staff = $this->makeUser();
        $this->grantRole($staff, 'staff');
        $ticket = $this->makeTicket();
        $tomorrow = $this->makeTicket(null, ['booking_date' => GateDay::dateFor(now()->addDay())]);

        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $ticket->qr_code], $staff)->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.ticket_number', $ticket->ticket_number)->assertJsonPath('data.is_valid', true);
        $this->assertSame(0, $ticket->fresh()->check_in_count); // validate only checks
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => 'nope'], $staff)->assertNotFound()->assertJsonPath('error_code', 'INVALID_QR_CODE');
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $tomorrow->qr_code], $staff)->assertStatus(400)
            ->assertJsonPath('error_code', 'WRONG_DATE')->assertJsonPath('result', 'wrong_date');

        $this->api('POST', "/api/v1/tickets/{$ticket->id}/mark-used", ['location' => 'North door'], $staff)->assertOk()
            ->assertJsonPath('data.status', 'used')->assertJsonPath('data.check_in_count', 1)
            ->assertJsonPath('data.validated_by', sprintf('%s (#%d)', $staff->name, $staff->id));
        $this->assertSame('North door', $ticket->fresh()->validation_location);
        $this->api('POST', "/api/v1/tickets/{$ticket->id}/mark-used", [], $staff)->assertStatus(400)->assertJsonPath('error_code', 'INVALID_OPERATION');
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $ticket->qr_code], $staff)->assertStatus(400)
            ->assertJsonPath('error_code', 'TICKET_ALREADY_USED')->assertJsonPath('data.validated_by', sprintf('%s (#%d)', $staff->name, $staff->id));

        // logged without gate / device: invalid, wrong_date, valid (mark-used), already_used ×2
        $this->assertSame(['invalid', 'wrong_date', 'valid', 'already_used', 'already_used'], GateScan::orderBy('id')->pluck('result')->all());
        $this->assertSame(5, GateScan::whereNull('gate_id')->whereNull('device_id')->where('staff_user_id', $staff->id)->count());
    }
}
