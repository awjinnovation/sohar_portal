<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendOtpSms;
use App\Models\BoxOfficeOrder;
use App\Models\GateScan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\OpsRoomMetrics;
use App\Support\OpsSignals;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/** Admin → Operations room (wall screen): access, and the figures of each panel. */
class OpsRoomTest extends GateTestCase
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

    private function payload(): array
    {
        return $this->inTenant($this->tenant, fn () => app(OpsRoomMetrics::class)->payload($this->tenant));
    }

    #[Test]
    public function the_ops_room_needs_the_gates_or_tickets_permission(): void
    {
        foreach (['viewer', 'content-manager', 'finance-manager', 'staff'] as $role) {
            $user = $this->admin($role);
            $this->actingAs($user)->get(route('admin.ops-room'))->assertForbidden();
            $this->actingAs($user)->getJson(route('admin.ops-room.data'))->assertForbidden();
        }

        // `manage gates` or `manage tickets` (event managers have the latter).
        $this->actingAs($this->admin('event-manager'))->get(route('admin.ops-room'))->assertOk();
        $this->actingAs($this->admin('ticket-manager'))->get(route('admin.ops-room'))->assertOk()
            ->assertSee('غرفة العمليات التقنية')->assertSee('ops-room.js')->assertDontSee('id="sidebar"', false);
        $this->actingAs($this->admin('ticket-manager'))->getJson(route('admin.ops-room.data'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['data' => ['tickets' => ['today', 'channels', 'series'], 'sms' => ['pending', 'status'], 'orders' => ['checkout', 'links'], 'gates' => ['list'], 'system' => ['worker', 'scheduler']]]);

        auth()->logout();
        $this->get(route('admin.ops-room'))->assertRedirect();
    }

    #[Test]
    public function the_sidebar_links_the_ops_room_only_with_the_permission(): void
    {
        $this->actingAs($this->admin('ticket-manager'))->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.ops-room'));
        $this->actingAs($this->admin('event-manager'))->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.ops-room'));
        $this->actingAs($this->admin('content-manager'))->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.ops-room'));
    }

    #[Test]
    public function gates_are_ranked_by_entries_in_the_last_15_minutes(): void
    {
        $a = $this->makeGate(['code' => 'A', 'sort_order' => 1]);
        $b = $this->makeGate(['code' => 'B', 'sort_order' => 2]);
        $this->makeGate(['code' => 'C', 'sort_order' => 3, 'active' => false]);
        [, $ta] = $this->pairDevice($a, null, 'A1');
        [, $tb] = $this->pairDevice($b, null, 'B1');

        // A: one entry 30 minutes ago and one now; B: two entries now and a reject.
        $this->scan($ta, $this->makeTicket()->qr_code)->assertOk();
        GateScan::withoutTenantScope()->latest('id')->first()->forceFill(['scanned_at' => now()->subMinutes(30)])->save();
        $this->scan($ta, $this->makeTicket()->qr_code)->assertOk();
        $this->scan($tb, $this->makeTicket()->qr_code)->assertOk();
        $this->scan($tb, $this->makeTicket()->qr_code)->assertOk();
        $this->scan($tb, 'BOGUS');

        $g = $this->payload()['gates'];
        $this->assertSame(['B', 'A', 'C'], array_column($g['list'], 'code'));
        $this->assertSame([2, 1, 0], array_column($g['list'], 'in_15m'));
        $this->assertSame([2, 2, 0], array_column($g['list'], 'in_today'));
        $this->assertSame([1, 0, 0], array_column($g['list'], 'rejects_today'));
        $this->assertSame([1, 1, 0], array_column($g['list'], 'devices_online'));
        $this->assertFalse($g['list'][2]['active']);
        $this->assertSame(2, array_sum($g['list'][0]['series']));
        $this->assertSame(['in' => 4, 'out' => 0, 'rejects' => 1, 'inside' => 4, 'in_15m' => 3], array_intersect_key($g, array_flip(['in', 'out', 'rejects', 'inside', 'in_15m'])));
        $this->assertSame([2, 2], [$g['devices_online'], $g['devices_total']]);
    }

    #[Test]
    public function tickets_are_counted_per_channel(): void
    {
        $online = $this->makeTicket(null, ['transaction_id' => 'TX-OPS-1']);
        $this->makeTicket(null, ['transaction_id' => 'TX-OPS-2']);
        $this->makeTicket(); // issued by hand: no payment
        $this->inTenant($this->tenant, function () use ($online) {
            foreach (['TX-OPS-1' => 'gateway', 'TX-OPS-2' => 'wallet'] as $tx => $method) {
                Payment::create([
                    'user_id' => $online->user_id, 'transaction_id' => $tx, 'payment_type' => 'ticket', 'method' => $method,
                    'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now(), 'fulfilled_at' => now(),
                ]);
            }
        });

        $t = $this->payload()['tickets'];
        $this->assertSame([3, 3, 3, 3], [$t['today'], $t['people'], $t['last_15m'], $t['last_hour']]);
        $this->assertSame(['online' => 1, 'link' => 0, 'card' => 0, 'wallet' => 1, 'other' => 1], array_column($t['channels'], 'count', 'key'));
        $this->assertCount(24, $t['series']['counts']);
        $this->assertSame(3, $t['series']['counts'][23]);
    }

    #[Test]
    public function the_sms_queue_shows_what_waits_and_what_was_sent(): void
    {
        config(['queue.default' => 'database']);
        SendOtpSms::dispatch('96891234567', 'Code 1234');
        SendOtpSms::dispatch('96891234568', 'Code 5678');

        config(['services.ismartsms.user_id' => 'u', 'services.ismartsms.password' => 'p', 'services.ismartsms.url' => 'https://sms.test/send']);
        Http::fakeSequence()->push('OK', 200)->push('ERROR', 500);
        (new SendOtpSms('96891234569', 'Code 1'))->handle();
        (new SendOtpSms('96891234560', 'Code 2'))->handle();

        $sms = $this->payload()['sms'];
        $this->assertSame((new SendOtpSms('', ''))->queue ?: 'default', $sms['queue']);
        $this->assertSame([2, 0, 0], [$sms['pending'], $sms['reserved'], $sms['delayed']]);
        $this->assertSame([1, 1, 1], [$sms['sent_hour'], $sms['sent_today'], $sms['failed_today']]);
        $this->assertSame(0, $sms['last_wait']);
        $this->assertNotNull($sms['oldest_wait']);
        // No worker heartbeat yet: no signal, so not reported as stopped.
        $this->assertSame('ok', $sms['status']);

        // Jobs waiting and a worker silent for 10 minutes: the queue is stuck.
        OpsSignals::put('beat:worker', time() - 600);
        $p = $this->payload();
        $this->assertSame('stopped', $p['system']['worker']['status']);
        $this->assertSame('critical', $p['sms']['status']);

        OpsSignals::put('beat:worker', time());
        OpsSignals::put('beat:scheduler', time() - 30);
        $p = $this->payload();
        $this->assertSame(['running', 'running'], [$p['system']['worker']['status'], $p['system']['scheduler']['status']]);
        $this->assertSame('ok', $p['sms']['status']);
    }

    #[Test]
    public function orders_waiting_count_open_links_and_payments_to_review(): void
    {
        $gate = $this->makeGate();
        $user = $this->makeUser();
        $this->inTenant($this->tenant, function () use ($gate, $user) {
            foreach ([['BO-1', 'pending', now()->addMinutes(10)], ['BO-2', 'pending', now()->subMinute()], ['BO-3', 'paid', now()->addMinutes(10)]] as [$ref, $status, $expires]) {
                BoxOfficeOrder::create([
                    'gate_id' => $gate->id, 'reference' => $ref, 'buyer_phone' => '96891234567', 'booking_date' => now()->toDateString(),
                    'quantity' => 1, 'unit_price_minor' => 500, 'total_minor' => 500, 'currency' => 'OMR', 'status' => $status,
                    'public_token' => str_pad($ref, 32, 'x'), 'expires_at' => $expires, 'idempotency_key' => $ref,
                ]);
            }
            Payment::create([
                'user_id' => $user->id, 'transaction_id' => 'TX-OPS-PENDING', 'payment_type' => 'ticket', 'method' => 'gateway',
                'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'pending',
            ]);
            Payment::create([
                'user_id' => $user->id, 'transaction_id' => 'TX-OPS-REVIEW', 'payment_type' => 'ticket', 'method' => 'gateway',
                'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now(), 'fulfilled_at' => now(), 'needs_review' => true,
            ]);
        });

        $o = $this->payload()['orders'];
        $this->assertSame([1, 1, 0, 1], [$o['checkout'], $o['links'], $o['unissued'], $o['review']]);
        $this->assertSame('warn', $o['status']);

        // Paid more than 2 minutes ago and still no tickets: needs attention.
        $this->inTenant($this->tenant, fn () => Payment::create([
            'user_id' => $user->id, 'transaction_id' => 'TX-OPS-UNISSUED', 'payment_type' => 'ticket', 'method' => 'gateway',
            'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now()->subMinutes(5),
        ])->forceFill(['created_at' => now()->subMinutes(5)])->save());
        $o = $this->payload()['orders'];
        $this->assertSame(1, $o['unissued']);
        $this->assertSame('critical', $o['status']);
    }
}
