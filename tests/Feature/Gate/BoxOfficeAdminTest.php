<?php

namespace Tests\Feature\Gate;

use App\Models\AuditLog;
use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\User;
use App\Services\Gate\BoxOfficeService;
use PHPUnit\Framework\Attributes\Test;

/** Admin → Gates → Gate sales, settings and the gate form switch (docs/gate_sales_contract.md §7). */
class BoxOfficeAdminTest extends GateTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->tenant->putSetting('gate_sales.enabled', true)->save();
    }

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    private function sell(Gate $gate, User $staff, Event $event, int $qty, string $phone = '99112233'): BoxOfficeOrder
    {
        $device = $this->inTenant($this->tenant, fn () => GateDevice::create(['gate_id' => $gate->id, 'name' => 'POS '.$gate->code, 'device_type' => 'android_pos', 'status' => 'active']));

        return $this->inTenant($this->tenant, fn () => app(BoxOfficeService::class)->create($this->tenant, $device->setRelation('gate', $gate), $staff, [
            'event_id' => $event->id, 'items' => [['ticket_type' => null, 'quantity' => $qty]], 'buyer_phone' => $phone,
            'buyer_name' => 'Maryam', 'send_sms' => false, 'locale' => 'ar', 'idempotency_key' => uniqid('k', true),
        ])[0]);
    }

    #[Test]
    public function gate_sales_pages_list_orders_with_totals_per_gate_and_staff(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.500']);
        $gateA = $this->makeGate(['code' => 'A', 'can_sell' => true, 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة الرئيسية أ']]);
        $gateB = $this->makeGate(['code' => 'B', 'can_sell' => true]);
        $salim = User::factory()->create(['name' => 'Salim Staff']);
        $huda = User::factory()->create(['name' => 'Huda Staff']);
        $paid = $this->sell($gateA, $salim, $event, 2);
        $this->inTenant($this->tenant, function () use ($paid) {
            $payment = app(BoxOfficeService::class)->startPayment($paid);
            app(\App\Services\Payments\PaymentFulfillmentService::class)->complete($payment, [], 'fake');
        });
        $paid->refresh();
        $this->assertSame('paid', $paid->status);
        $pending = $this->sell($gateB, $huda, $event, 1, '99887766');

        foreach (['viewer', 'event-manager'] as $role) {
            $user = $this->admin($role);
            $this->actingAs($user)->get(route('admin.gates.sales.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.gates.sales.show', $paid))->assertForbidden();
            $this->actingAs($user)->post(route('admin.gates.sales.cancel', $pending))->assertForbidden();
        }

        $admin = $this->admin('ticket-manager');
        $page = $this->actingAs($admin)->get(route('admin.gates.sales.index'))->assertOk()
            ->assertSee($paid->reference)->assertSee($pending->reference)
            ->assertSee('Salim Staff')->assertSee('Huda Staff')->assertSee('البوابة الرئيسية أ')
            ->assertSee('3.000');
        $this->assertSame(2, substr_count($page->getContent(), 'data-total-row="perGate"'));
        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['status' => 'pending']))->assertOk()
            ->assertSee($pending->reference)->assertDontSee($paid->reference);
        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['q' => '7766']))->assertOk()
            ->assertSee($pending->reference)->assertDontSee($paid->reference);
        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['staff' => $salim->id]))->assertOk()
            ->assertSee($paid->reference)->assertDontSee($pending->reference);

        $this->actingAs($admin)->get(route('admin.gates.sales.show', $paid))->assertOk()
            ->assertSee($paid->reference)->assertSee('/pay/'.$paid->public_token)->assertSee('data-sms-count="0"', false)
            ->assertSee($paid->payment->transaction_id)->assertSee($paid->issuedTickets()->first()->ticket_number);

        // Cancel a pending order from the panel.
        $this->actingAs($admin)->post(route('admin.gates.sales.cancel', $pending))->assertRedirect(route('admin.gates.sales.show', $pending));
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('admin', $pending->fresh()->meta['cancelled_via']);
        $this->assertTrue(AuditLog::where('action', 'box_office.cancelled')->exists());
        $this->actingAs($admin)->from(route('admin.gates.sales.show', $paid))->post(route('admin.gates.sales.cancel', $paid))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('paid', $paid->fresh()->status);

        // English panel.
        $this->actingAs($admin)->withSession(['admin_locale' => 'en'])->get(route('admin.gates.sales.index'))->assertOk()->assertSee('Gate sales')->assertSee('Gate orders');

        // Other tenants' orders are not reachable.
        $other = $this->makeTenant('other-org');
        $foreignGate = $this->makeGate([], $other);
        $foreign = $this->inTenant($other, fn () => BoxOfficeOrder::create([
            'gate_id' => $foreignGate->id, 'reference' => 'BO-000000-0001', 'buyer_phone' => '96899000000', 'event_id' => null,
            'booking_date' => now()->toDateString(), 'quantity' => 1, 'unit_price_minor' => 1, 'total_minor' => 1, 'currency' => 'OMR',
            'public_token' => str_repeat('z', 32), 'expires_at' => now()->addHour(), 'idempotency_key' => 'f',
        ]));
        $this->actingAs($admin)->get(route('admin.gates.sales.show', $foreign))->assertNotFound();
    }

    #[Test]
    public function sales_settings_and_the_gate_switch_are_saved_from_the_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('id="sec-gate-sales"', false);

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            's' => ['gate_sales' => ['enabled' => '0', 'link_ttl_minutes' => '45', 'send_sms' => '0', 'max_quantity' => '6', 'allow_future_dates' => '1'],
                'messages' => ['box_office_sms' => ['ar' => ':app. ادفع عبر الرابط: :url', 'en' => ':app: pay at :url']]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.branding.edit'));
        $settings = BoxOfficeService::settings($this->tenant->fresh());
        $this->assertSame(['enabled' => false, 'link_ttl_minutes' => 45, 'send_sms' => false, 'max_quantity' => 6, 'allow_future_dates' => true], $settings);
        $this->assertSame(':app: pay at :url', $this->tenant->fresh()->setting('messages.box_office_sms.en'));

        $this->actingAs($admin)->put(route('admin.branding.update'), ['name' => ['en' => 'North', 'ar' => 'الشمال'], 's' => ['gate_sales' => ['link_ttl_minutes' => '2']]])
            ->assertSessionHasErrors('s.gate_sales.link_ttl_minutes');

        // Gate form: «بيع التذاكر».
        $this->actingAs($admin)->get(route('admin.gates.create'))->assertOk()->assertSee('name="can_sell"', false);
        $this->actingAs($admin)->post(route('admin.gates.store'), [
            'name_ar' => 'بوابة البيع', 'name_en' => 'Sales gate', 'code' => 'S1', 'direction' => 'entry', 'can_sell' => '1', 'active' => '1',
        ])->assertRedirect(route('admin.gates.index'));
        $gate = Gate::where('code', 'S1')->firstOrFail();
        $this->assertTrue($gate->can_sell);
        $this->actingAs($admin)->get(route('admin.gates.index'))->assertSee('تبيع التذاكر');
        $this->actingAs($admin)->put(route('admin.gates.update', $gate), [
            'name_ar' => 'بوابة البيع', 'name_en' => 'Sales gate', 'code' => 'S1', 'direction' => 'entry', 'active' => '1',
        ])->assertRedirect();
        $this->assertFalse($gate->fresh()->can_sell);
    }
}
