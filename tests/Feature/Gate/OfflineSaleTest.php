<?php

namespace Tests\Feature\Gate;

use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\GateDevice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Gate\GateDay;
use App\Services\Gate\OfflineSaleCodes;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/** Gate sales with no connection, synced later (docs/gate_sales_contract.md §10). */
class OfflineSaleTest extends GateTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['auth.otp.test_code' => '123456']);
        $this->tenant->putSetting('gate_sales.enabled', true)->putSetting('gate_sales.offline_enabled', true)->save();
    }

    /** @return array{0: GateDevice, 1: string, 2: \App\Models\Gate, 3: User} */
    private function sellingDevice(string $code = 'A'): array
    {
        $gate = $this->makeGate(['can_sell' => true, 'code' => $code]);
        [$device, $token] = $this->pairDevice($gate);
        $staff = $this->makeUser('96890000001');
        $this->grantRole($staff, 'staff');
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000001'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $token)->assertOk();

        return [$device, $token, $gate, $staff];
    }

    private function event(): Event
    {
        return $this->makeEvent(null, ['price' => '1.000', 'total_tickets' => 100]);
    }

    private function type(Event $event, string $key = 'family', int $admits = 4, int $price = 3000): EventTicketType
    {
        return $this->inTenant($this->tenant, fn () => EventTicketType::create([
            'event_id' => $event->id, 'key' => $key, 'name' => ['en' => Str::headline($key), 'ar' => 'عائلة'], 'category' => 'family',
            'price_minor' => $price, 'admits' => $admits, 'per_order_max' => 10, 'visibility' => 'public', 'is_active' => true, 'sort' => 5,
        ]));
    }

    /** A sale as the device sends it: $n tickets of $type made with the device's sequence from $seq. */
    private function sale(GateDevice $device, string $gateCode, EventTicketType $type, int $n = 2, int $seq = 1, array $extra = []): array
    {
        $date = GateDay::today($this->tenant);
        $tickets = [];
        for ($i = 0; $i < $n; $i++) {
            $tickets[] = [
                'number' => OfflineSaleCodes::number($gateCode, $device->id, $seq + $i),
                'code' => OfflineSaleCodes::make($this->tenant, $gateCode, $device->id, $seq + $i, $type->id, $type->admits, $date),
                'ticket_type_id' => $type->id,
            ];
        }

        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'sold_at' => now()->subMinutes(20)->toIso8601String(),
            'booking_date' => $date,
            'event_id' => $type->event_id,
            'approval_code' => '123456',
            'total_minor' => $type->price_minor * $n,
            'lines' => [['ticket_type_id' => $type->id, 'quantity' => $n, 'unit_price_minor' => $type->price_minor]],
            'tickets' => $tickets,
        ], $extra);
    }

    #[Test]
    public function the_signature_matches_the_gate_app(): void
    {
        // Same vectors as test/gate/offline_sale_test.dart in the app.
        $this->assertSame('UWPDZITQXGJT', OfflineSaleCodes::sign(str_repeat('0f', 32), 'OFF-A-9-0007.12.1.20261118'));
        $this->assertSame('5TIGIS4XB72M', OfflineSaleCodes::sign(hash('sha256', 'k'), 'OFF-EXIT-1-9-0012.5.4.20261118'));

        $code = OfflineSaleCodes::make($this->tenant, 'EXIT-1', 9, 12, 5, 4, '2026-11-18');
        $p = OfflineSaleCodes::verify($this->tenant, strtolower($code));
        $this->assertSame(['OFF-EXIT-1-9-0012', 'EXIT-1', 9, 12, 5, 4, '2026-11-18'], [$p['number'], $p['gate_code'], $p['device_id'], $p['seq'], $p['type_id'], $p['admits'], $p['date']]);
        $this->assertNull(OfflineSaleCodes::verify($this->tenant, str_replace('.4.', '.9.', $code)), 'admits changed');
        $this->assertNull(OfflineSaleCodes::verify($this->otherTenant(), $code), 'another tenant');
        $this->assertNull(OfflineSaleCodes::parse('TKT-2026-ABCDEFGH-XXXXXXXXXXXX'));
    }

    #[Test]
    public function the_session_gives_signing_keys_only_when_offline_sales_are_on(): void
    {
        [, $token] = $this->sellingDevice();
        $sales = $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()->json('data.settings.sales');
        $this->assertTrue($sales['offline_enabled']);
        $today = GateDay::today($this->tenant);
        $this->assertCount(OfflineSaleCodes::KEY_DAYS, $sales['offline_keys']);
        $this->assertSame(OfflineSaleCodes::dayKey($this->tenant, $today), $sales['offline_keys'][$today]);

        $this->tenant->putSetting('gate_sales.offline_enabled', false)->save();
        $sales = $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()->json('data.settings.sales');
        $this->assertFalse($sales['offline_enabled']);
        $this->assertSame([], $sales['offline_keys']);
    }

    #[Test]
    public function a_synced_sale_becomes_a_paid_card_order_with_the_device_tickets(): void
    {
        [$device, $token, , $staff] = $this->sellingDevice();
        $type = $this->type($this->event());
        $sale = $this->sale($device, 'A', $type, 2, 1, ['staff_id' => $staff->id]);

        $res = $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$sale]], $token)->assertOk();
        $this->assertSame('synced', $res->json('data.results.0.status'));
        $this->assertTrue($res->json('data.results.0.created'));
        $this->assertFalse($res->json('data.results.0.needs_review'));

        $order = BoxOfficeOrder::withoutTenantScope()->findOrFail($res->json('data.results.0.order_id'));
        $this->assertTrue($order->offline);
        $this->assertSame([BoxOfficeOrder::STATUS_PAID, BoxOfficeOrder::METHOD_POS, '', 6000, $staff->id], [$order->status, $order->payment_method, $order->buyer_phone, $order->total_minor, $order->staff_user_id]);
        $this->assertStringStartsWith('BO-', $order->reference);
        $this->assertNotNull($order->synced_at);

        $payment = Payment::withoutTenantScope()->findOrFail($order->payment_id);
        $this->assertSame([Payment::METHOD_POS, 'completed', 6000], [$payment->method, $payment->status, (int) $payment->amount_minor]);

        $tickets = Ticket::withoutTenantScope()->where('transaction_id', $payment->transaction_id)->orderBy('ticket_number')->get();
        $this->assertSame(array_column($sale['tickets'], 'number'), $tickets->pluck('ticket_number')->all());
        $this->assertSame(array_column($sale['tickets'], 'code'), $tickets->pluck('qr_code')->all());
        $this->assertSame([4, 4], $tickets->pluck('max_check_ins')->all(), 'family tickets admit 4');
        $this->assertSame('active', $tickets[0]->status);

        // Sent again (the device did not get the answer): same order, nothing new.
        $again = $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$sale]], $token)->assertOk();
        $this->assertSame([$order->id, false], [$again->json('data.results.0.order_id'), $again->json('data.results.0.created')]);
        $this->assertSame(1, BoxOfficeOrder::withoutTenantScope()->where('offline', true)->count());
    }

    #[Test]
    public function the_tickets_scan_once_synced_and_each_entry_is_counted(): void
    {
        [$device, $token] = $this->sellingDevice();
        $type = $this->type($this->event(), 'single', 1, 1000);
        $sale = $this->sale($device, 'A', $type, 1);
        $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$sale]], $token)->assertOk();

        $this->assertSame('valid', $this->scan($token, $sale['tickets'][0]['code'])->json('data.result'));
        $this->assertSame('already_used', $this->scan($token, $sale['tickets'][0]['code'])->json('data.result'));
    }

    #[Test]
    public function a_ticket_scanned_before_its_sale_arrives_is_admitted_and_joined_to_the_sale_later(): void
    {
        [$device, $token] = $this->sellingDevice();
        [, $otherToken] = $this->pairDevice($this->makeGate(['code' => 'B']), null, 'B1');
        $type = $this->type($this->event(), 'single', 1, 1000);
        $sale = $this->sale($device, 'A', $type, 1);

        // Another gate, online, scans it first.
        $this->assertSame('valid', $this->scan($otherToken, $sale['tickets'][0]['code'])->json('data.result'));
        $ticket = Ticket::withoutTenantScope()->where('ticket_number', $sale['tickets'][0]['number'])->firstOrFail();
        $this->assertNull($ticket->transaction_id);

        $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$sale]], $token)->assertOk()->assertJsonPath('data.results.0.status', 'synced');
        $ticket->refresh();
        $this->assertNotNull($ticket->transaction_id, 'the sale attached to the scanned ticket');
        $this->assertSame(1, Ticket::withoutTenantScope()->where('ticket_number', $sale['tickets'][0]['number'])->count());
        $this->assertSame(1, (int) $ticket->check_in_count, 'the earlier entry is kept');
    }

    #[Test]
    public function forged_or_foreign_codes_are_refused_and_the_other_sales_go_on(): void
    {
        [$device, $token] = $this->sellingDevice();
        $type = $this->type($this->event(), 'single', 1, 1000);
        $good = $this->sale($device, 'A', $type, 1, 1);
        $forged = $this->sale($device, 'A', $type, 1, 2);
        $forged['tickets'][0]['code'] = substr($forged['tickets'][0]['code'], 0, -1).'A';
        $otherDevice = $this->sale($device, 'A', $type, 1, 3);
        $otherDevice['tickets'][0]['number'] = OfflineSaleCodes::number('A', $device->id + 50, 3);
        $otherDevice['tickets'][0]['code'] = OfflineSaleCodes::make($this->tenant, 'A', $device->id + 50, 3, $type->id, 1, GateDay::today($this->tenant));

        $res = $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$good, $forged, $otherDevice]], $token)->assertOk();
        $this->assertSame(['synced', 'rejected', 'rejected'], array_column($res->json('data.results'), 'status'));
        $this->assertSame('OFFLINE_CODE_INVALID', $res->json('data.results.1.error'));

        // A forged code is not admitted by a scanner either.
        $this->assertSame('invalid', $this->scan($token, $forged['tickets'][0]['code'])->json('data.result'));
    }

    #[Test]
    public function a_sale_without_approval_code_or_with_another_price_is_flagged_for_review(): void
    {
        [$device, $token] = $this->sellingDevice();
        $type = $this->type($this->event(), 'single', 1, 1000);
        $noCode = $this->sale($device, 'A', $type, 1, 1, ['approval_code' => null]);
        $cheaper = $this->sale($device, 'A', $type, 1, 2);
        $cheaper['lines'][0]['unit_price_minor'] = 800;
        $cheaper['total_minor'] = 800;

        $res = $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$noCode, $cheaper]], $token)->assertOk();
        $this->assertSame([true, true], array_column($res->json('data.results'), 'needs_review'));
        $cheap = BoxOfficeOrder::withoutTenantScope()->findOrFail($res->json('data.results.1.order_id'));
        $this->assertSame([800, 'offline_price_changed'], [$cheap->total_minor, $cheap->meta['review_reason']]);
    }

    #[Test]
    public function offline_scans_of_offline_tickets_sync_by_code(): void
    {
        [$device, $token] = $this->sellingDevice();
        $type = $this->type($this->event(), 'single', 1, 1000);
        $sale = $this->sale($device, 'A', $type, 1);
        $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$sale]], $token)->assertOk();

        $res = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [[
            'code' => $sale['tickets'][0]['code'], 'result_local' => 'valid', 'direction' => 'in',
            'scanned_at' => now()->subMinutes(5)->toIso8601String(), 'idempotency_key' => (string) Str::uuid(),
        ]]], $token)->assertOk();
        $this->assertSame('valid', $res->json('data.results.0.result'));
        $this->assertSame(1, (int) Ticket::withoutTenantScope()->where('ticket_number', $sale['tickets'][0]['number'])->value('check_in_count'));
    }

    #[Test]
    public function the_admin_lists_offline_sales_with_a_badge(): void
    {
        [$device, $token] = $this->sellingDevice();
        $type = $this->type($this->event(), 'single', 1, 1000);
        $res = $this->gate('POST', '/api/v1/gate/sales/offline', ['sales' => [$this->sale($device, 'A', $type, 1)]], $token)->assertOk();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'admin');

        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['date' => GateDay::today($this->tenant)]))->assertOk()->assertSee('data-offline', false);
        $this->actingAs($admin)->get(route('admin.gates.sales.show', $res->json('data.results.0.order_id')))->assertOk()->assertSee('123456');
    }

    private function otherTenant(): \App\Models\Tenant
    {
        return \App\Models\Tenant::where('id', '!=', $this->tenant->id)->first() ?? $this->makeTenant('other-offline');
    }
}
