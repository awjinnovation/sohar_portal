<?php

namespace Tests\Feature\Gate;

use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GatePairingCode;
use App\Models\GateScan;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class GateAdminTest extends GateTestCase
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

    private function pages(Gate $gate): array
    {
        return [
            route('admin.gates.index'), route('admin.gates.create'), route('admin.gates.edit', $gate),
            route('admin.gates.dashboard'), route('admin.gates.dashboard.data'), route('admin.gates.devices.index'),
            route('admin.gates.scans.index'),
        ];
    }

    #[Test]
    public function gate_pages_are_permission_gated(): void
    {
        $gate = $this->makeGate();

        foreach (['viewer', 'event-manager', 'staff'] as $role) {
            $user = $this->admin($role);
            foreach ($this->pages($gate) as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs($user)->post(route('admin.gates.devices.pair'), ['gate_id' => $gate->id])->assertForbidden();
        }

        foreach (['admin', 'ticket-manager'] as $role) {
            $user = $this->admin($role);
            foreach ($this->pages($gate) as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }

        $this->get(route('admin.logout'))->assertStatus(405);
        auth()->logout();
        $this->get(route('admin.gates.dashboard'))->assertRedirect();
    }

    #[Test]
    public function the_sidebar_shows_the_gates_group_only_with_the_permission(): void
    {
        $this->actingAs($this->admin('ticket-manager'))->get(route('admin.gates.index'))->assertOk()
            ->assertSee(route('admin.gates.dashboard'))->assertSee(route('admin.gates.devices.index'))->assertSee(route('admin.gates.scans.index'));
        $this->actingAs($this->admin('event-manager'))->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee(route('admin.gates.dashboard'));
    }

    #[Test]
    public function admins_create_update_and_delete_gates(): void
    {
        $admin = $this->admin();
        $event = $this->makeEvent();

        $this->actingAs($admin)->post(route('admin.gates.store'), [
            'name_ar' => 'بوابة كبار الشخصيات C', 'name_en' => 'VIP Gate C', 'code' => 'c', 'direction' => 'entry',
            'event_id' => $event->id, 'ticket_types' => 'vip, VIP-Gold', 'allow_reentry' => '1', 'active' => '1',
        ])->assertRedirect(route('admin.gates.index'));
        $gate = Gate::sole();
        $this->assertSame(['C', ['ar' => 'بوابة كبار الشخصيات C', 'en' => 'VIP Gate C'], ['vip', 'VIP-Gold'], true, true, $event->id],
            [$gate->code, $gate->name, $gate->ticket_types, $gate->allow_reentry, $gate->active, $gate->event_id]);

        // codes are unique per tenant
        $this->actingAs($admin)->post(route('admin.gates.store'), ['name_ar' => 'x', 'name_en' => 'x', 'code' => 'C', 'direction' => 'entry'])
            ->assertSessionHasErrors('code');
        // an event of another tenant cannot be attached
        $foreign = $this->makeEvent($this->makeTenant('other'));
        $this->actingAs($admin)->post(route('admin.gates.store'), ['name_ar' => 'x', 'name_en' => 'x', 'code' => 'Z', 'direction' => 'entry', 'event_id' => $foreign->id])
            ->assertSessionHasErrors('event_id');

        $this->actingAs($admin)->put(route('admin.gates.update', $gate), [
            'name_ar' => 'بوابة C', 'name_en' => 'Gate C', 'code' => 'C', 'direction' => 'both', 'ticket_types' => '',
        ])->assertRedirect();
        $gate->refresh();
        $this->assertSame(['both', null, false, false], [$gate->direction, $gate->ticket_types, $gate->allow_reentry, $gate->active]);

        // a gate with scans is deactivated instead of deleted; an unused one is deleted
        [, $token] = $this->pairDevice($gate);
        $gate->update(['active' => true]);
        $this->scan($token, 'NOPE');
        $this->actingAs($admin)->delete(route('admin.gates.destroy', $gate))->assertRedirect();
        $this->assertFalse($gate->fresh()->active);
        $spare = $this->makeGate();
        $this->actingAs($admin)->delete(route('admin.gates.destroy', $spare))->assertRedirect();
        $this->assertNull(Gate::find($spare->id));
    }

    #[Test]
    public function the_pair_modal_gets_a_code_and_a_qr_and_devices_can_be_revoked(): void
    {
        $admin = $this->admin();
        $gate = $this->makeGate(['code' => 'A']);

        $res = $this->actingAs($admin)->postJson(route('admin.gates.devices.pair'), ['gate_id' => $gate->id])->assertOk();
        $code = GatePairingCode::sole();
        $res->assertJsonPath('data.code', $code->code)
            ->assertJsonPath('data.gate_id', $gate->id)
            ->assertJsonPath('data.link', 'mawsim-gate://pair?code='.$code->code.'&tenant=north-batinah&api=http%3A%2F%2Flocalhost');
        $this->assertStringContainsString('<svg', $res->json('data.qr_svg'));
        $this->assertGreaterThan(590, $res->json('data.expires_in'));

        // non-JS fallback: redirect, the modal is rendered open with the code
        $this->actingAs($admin)->post(route('admin.gates.devices.pair'), ['gate_id' => $gate->id])->assertRedirect(route('admin.gates.devices.index'));
        $latest = GatePairingCode::latest('id')->first();
        $this->actingAs($admin)->withSession(['gate_pairing_code_id' => $latest->id])->get(route('admin.gates.devices.index'))
            ->assertOk()->assertSee($latest->code)->assertSee('<svg', false);

        // gate of another tenant cannot be used
        $foreignGate = $this->makeGate([], $this->makeTenant('other'));
        $this->actingAs($admin)->postJson(route('admin.gates.devices.pair'), ['gate_id' => $foreignGate->id])->assertStatus(422);

        // pair, list, revoke
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => $code->code, 'device' => ['name' => 'Lane 7', 'device_type' => 'handheld']])->assertCreated();
        $this->actingAsTenant($this->tenant);
        $device = GateDevice::sole();
        $token = $device->tokens()->exists();
        $this->assertTrue($token);
        $this->actingAs($admin)->get(route('admin.gates.devices.index'))->assertOk()->assertSee('Lane 7');

        $this->actingAs($admin)->put(route('admin.gates.devices.update', $device), ['name' => 'Lane 7B', 'gate_id' => $gate->id])->assertRedirect();
        $this->assertSame('Lane 7B', $device->fresh()->name);

        $this->actingAs($admin)->post(route('admin.gates.devices.revoke', $device))->assertRedirect();
        $this->assertTrue($device->fresh()->isRevoked());
        $this->assertNotNull($device->fresh()->revoked_at);

        // devices of another tenant are not reachable
        [$foreignDevice] = $this->pairDevice($foreignGate, \App\Models\Tenant::findBySlug('other'));
        $this->actingAs($admin)->post(route('admin.gates.devices.revoke', $foreignDevice->id))->assertNotFound();
        $this->assertFalse($foreignDevice->fresh()->isRevoked());
    }

    #[Test]
    public function the_live_dashboard_data_aggregates_today(): void
    {
        $admin = $this->admin();
        $a = $this->makeGate(['code' => 'A', 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة A']]);
        $x = $this->makeGate(['code' => 'X', 'direction' => 'exit']);
        [, $ta] = $this->pairDevice($a, null, 'A1');
        [, $tx] = $this->pairDevice($x, null, 'X1');
        $t1 = $this->makeTicket();
        $this->scan($ta, $t1->qr_code);
        $this->scan($ta, $this->makeTicket()->qr_code);
        $this->scan($ta, $t1->qr_code);       // already_used
        $this->scan($ta, 'BOGUS');            // invalid
        $this->scan($tx, $t1->qr_code);       // out

        $data = $this->actingAs($admin)->getJson(route('admin.gates.dashboard.data'))->assertOk()->json('data');
        $this->assertSame(['in' => 2, 'out' => 1, 'rejects' => 2, 'inside' => 1, 'conflicts' => 0, 'devices_online' => 2, 'devices_total' => 2], $data['totals']);
        $gateA = collect($data['gates'])->firstWhere('code', 'A');
        $this->assertSame([2, 0, 2, 1], [$gateA['in'], $gateA['out'], $gateA['rejects'], $gateA['devices_online']]);
        $this->assertEqualsCanonicalizing(['already_used', 'invalid'], array_column($data['reasons'], 'result'));
        $this->assertCount(5, $data['recent']);
        $this->assertSame(5, $data['series']['bucket_minutes']);
        $this->assertSame(count($data['series']['labels']), count($data['series']['datasets'][0]['data']));
        $this->assertSame(2, array_sum(collect($data['series']['datasets'])->firstWhere('gate_id', $a->id)['data']));
        $this->assertSame(2, array_sum($data['series']['rejects']));

        // the page embeds the payload and the AI insights hook
        $this->actingAs($admin)->get(route('admin.gates.dashboard'))->assertOk()
            ->assertSee('aiInsights', false)->assertSee('admin\/gates\/insights', false)->assertSee('gates\/dashboard\/data', false);
        // another day is empty
        $this->actingAs($admin)->getJson(route('admin.gates.dashboard.data', ['date' => '2020-01-01']))->assertOk()
            ->assertJsonPath('data.totals.in', 0)->assertJsonPath('data.is_today', false);
    }

    #[Test]
    public function the_scan_log_filters(): void
    {
        $admin = $this->admin();
        [, $token] = $this->pairDevice($this->makeGate(['code' => 'A']));
        $ticket = $this->makeTicket();
        $this->scan($token, $ticket->qr_code);
        $this->scan($token, 'BOGUS');

        $this->actingAs($admin)->get(route('admin.gates.scans.index'))->assertOk()->assertSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('admin.gates.scans.index', ['result' => 'rejects']))->assertOk()->assertDontSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('admin.gates.scans.index', ['q' => substr($ticket->ticket_number, -6)]))->assertOk()->assertSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('admin.gates.scans.index', ['flag' => 'conflict']))->assertOk()->assertDontSee($ticket->ticket_number);
        $this->actingAs($admin)->get(route('admin.gates.scans.index', ['date' => '2020-01-01']))->assertOk()->assertDontSee($ticket->ticket_number);
        $this->assertSame(2, GateScan::count());
    }

    #[Test]
    public function the_english_panel_is_translated(): void
    {
        $admin = $this->admin();
        $this->makeGate(['code' => 'A', 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة A']]);

        $this->actingAs($admin)->withSession(['admin_locale' => 'en'])->get(route('admin.gates.index'))->assertOk()
            ->assertSee('Live gate dashboard')->assertSee('Add gate')->assertDontSee('إضافة بوابة');
        $this->actingAs($admin)->withSession(['admin_locale' => 'en'])->get(route('admin.gates.devices.index'))->assertOk()
            ->assertSee('Pair new device')->assertSee('Sync queue');
        $this->actingAs($admin)->withSession(['admin_locale' => 'en'])->get(route('admin.gates.dashboard'))->assertOk()
            ->assertSee('Entries per 5 minutes')->assertSee('AI insights');
    }
}
