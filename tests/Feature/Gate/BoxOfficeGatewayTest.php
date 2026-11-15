<?php

namespace Tests\Feature\Gate;

use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentGatewaySession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakeCardGateways;

/**
 * Gate box office through the real gateway flows (Amwal SmartBox, Thawani,
 * both on): order → /pay/{token} → payment on the chosen gateway →
 * fulfilment → the gate's order poll; expiry / cancel against an open
 * attempt, late captures and their flags (docs/gate_reconciliation.md).
 */
class BoxOfficeGatewayTest extends GateTestCase
{
    use FakeCardGateways;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['auth.otp.test_code' => '123456']);
        $this->setUpCardGateways(['payments.driver' => 'amwal']);
        $this->tenant->putSetting('gate_sales.enabled', true)->save();
    }

    private function sellingDevice(): string
    {
        $gate = $this->makeGate(['can_sell' => true]);
        [, $token] = $this->pairDevice($gate);
        $phone = '9689000'.random_int(1000, 9999);
        $user = $this->makeUser($phone);
        $this->grantRole($user, 'staff');
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => $phone], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => $phone, 'otp' => '123456'], $token)->assertOk();

        return $token;
    }

    private function todayEvent(array $attrs = []): Event
    {
        return $this->makeEvent(null, array_merge(['price' => '1.000', 'total_tickets' => 100], $attrs));
    }

    private function order(string $token, Event $event, array $extra = []): array
    {
        return $this->gate('POST', '/api/v1/gate/sales/orders', array_merge([
            'event_id' => $event->id, 'quantity' => 2, 'buyer_phone' => '99112233', 'buyer_name' => 'Salim',
            'locale' => 'en', 'send_sms' => false, 'idempotency_key' => (string) Str::uuid(),
        ], $extra), $token)->assertCreated()->json('data.order');
    }

    /** /pay/{token} → checkout (optionally a gateway button) → our signed redirect page URL. */
    private function checkout(array $order, ?string $gateway = null): string
    {
        $path = parse_url($order['payment_url'], PHP_URL_PATH);
        $res = $this->post($path.'/checkout', array_filter(['lang' => 'en', 'gateway' => $gateway]))->assertRedirect();

        return $res->headers->get('Location');
    }

    private function payment(array $order): Payment
    {
        return Payment::findOrFail(BoxOfficeOrder::findOrFail($order['id'])->payment_id);
    }

    #[Test]
    public function an_amwal_tenant_sells_through_the_link_and_the_gate_sees_it_paid(): void
    {
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent(['price' => '1.500']));
        $path = parse_url($order['payment_url'], PHP_URL_PATH);

        // One gateway on: the single pay button, no choice.
        $this->get($path)->assertOk()->assertSee('data-pay-button', false)->assertDontSee('data-gateway=', false);

        $redirect = $this->checkout($order);
        $this->assertStringContainsString('/payment/redirect/', $redirect);
        $payment = $this->payment($order);
        $this->assertSame(['amwal', 'pending', 3000], [$payment->gateway, $payment->status, (int) $payment->amount_minor]);

        $this->get($redirect)->assertOk()->assertSee('https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1', false)
            ->assertSee($payment->transaction_id.'_1', false);
        $this->assertSame(1, PaymentGatewaySession::where('payment_id', $payment->id)->where('status', 'open')->count());

        // The gate polls while the visitor pays: still pending.
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertJsonPath('data.order.status', 'pending');

        // SmartBox's signed completeCallback → the order is paid, tickets issued once.
        $this->get($this->amwalReturnUrl($payment, $payment->transaction_id.'_1', '3.000'))
            ->assertRedirect(route('box-office.pay', ['token' => basename($path), 'lang' => 'en']));
        $this->get($this->amwalReturnUrl($payment, $payment->transaction_id.'_1', '3.000'));
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertFalse((bool) $payment->fresh()->needs_review);
        $this->assertSame(2, Ticket::where('transaction_id', $payment->transaction_id)->count());

        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertOk()
            ->assertJsonPath('data.order.status', 'paid')->assertJsonCount(2, 'data.order.tickets')
            ->assertJsonPath('data.order.payment_method', 'link');
        $this->get($path.'?lang=en')->assertOk()->assertSee('data-order-status="paid"', false);
    }

    #[Test]
    public function a_thawani_tenant_sells_through_the_link_and_the_gate_sees_it_paid(): void
    {
        config(['payments.driver' => 'thawani']);
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent());

        $redirect = $this->checkout($order);
        $payment = $this->payment($order);
        $this->assertSame('thawani', $payment->gateway);
        $this->get($redirect)->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_1?key=pk_test_public');

        $this->thawaniPays('checkout_1');
        $this->get('/payment/success?transaction_id='.$payment->transaction_id)->assertRedirect();
        $this->assertSame('completed', $payment->fresh()->status);
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertJsonPath('data.order.status', 'paid')
            ->assertJsonCount(2, 'data.order.tickets');
    }

    #[Test]
    public function with_both_on_the_visitor_picks_a_gateway_and_may_switch(): void
    {
        $this->tenant->putSetting('payments.driver', 'thawani')->putSetting('payments.gateways', ['thawani', 'amwal'])->save();
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent());
        $path = parse_url($order['payment_url'], PHP_URL_PATH);

        $this->get($path.'?lang=en')->assertOk()
            ->assertSeeInOrder(['data-gateway="thawani"', 'data-gateway="amwal"'], false)
            ->assertSee('Pay with Thawani')->assertSee('Pay with Amwal Pay');
        $this->get($path.'?lang=ar')->assertOk()->assertSee('ادفع عبر أموال باي');

        // Amwal first: its SmartBox page opens an attempt.
        $this->get($this->checkout($order, 'amwal'))->assertOk()->assertSee('SmartBox.js', false);
        $amwal = $this->payment($order);
        $this->assertSame('amwal', $amwal->gateway);
        // The same button again reuses the payment.
        $this->checkout($order, 'amwal');
        $this->assertSame($amwal->id, $this->payment($order)->id);

        // Back, then Thawani: Amwal is asked first, its payment cancelled, a Thawani one started.
        $this->get($this->checkout($order, 'thawani'))->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_1?key=pk_test_public');
        $thawani = $this->payment($order);
        $this->assertSame(['thawani', 'cancelled'], [$thawani->gateway, $amwal->fresh()->status]);
        $this->assertSame(1, $this->gatewayCallCount('amwal', 'get_transactions'));

        // A gateway switched off since the page was opened.
        $this->post($path.'/checkout', ['lang' => 'en', 'gateway' => 'bankmuscat'])
            ->assertRedirect(route('box-office.pay', ['token' => basename($path), 'lang' => 'en', 'payment' => 'unavailable']));

        $this->thawaniPays('checkout_1');
        $this->get('/payment/success?transaction_id='.$thawani->transaction_id)->assertRedirect();
        $this->assertSame('paid', BoxOfficeOrder::find($order['id'])->status);

        // The abandoned SmartBox page was paid too: the reconciler finds it, nothing is issued twice, a refund is flagged.
        $this->amwalPays($amwal->transaction_id.'_1', '2.000');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $amwal->refresh();
        $this->assertSame('completed', $amwal->status);
        $this->assertTrue((bool) $amwal->needs_review);
        $this->assertSame('box_office_refund_required', $amwal->review_reason);
        $this->assertTrue($amwal->metadata['box_office_refund_required']);
        $this->assertSame(0, Ticket::where('transaction_id', $amwal->transaction_id)->count());
        $this->assertSame(2, Ticket::count());
        $this->assertSame('paid', BoxOfficeOrder::find($order['id'])->status);
    }

    #[Test]
    public function an_order_that_expires_while_amwal_is_open_is_cancelled_and_a_late_payment_is_flagged(): void
    {
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent());
        $this->get($this->checkout($order))->assertOk();
        $payment = $this->payment($order);

        // The link expires (the gate's poll expires it lazily): the payment is cancelled after asking Amwal.
        $this->travel(31)->minutes();
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertJsonPath('data.order.status', 'expired');
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(1, $this->gatewayCallCount('amwal', 'get_transactions'));
        $this->assertSame('open', PaymentGatewaySession::where('payment_id', $payment->id)->value('status'), 'still watched for a late capture');

        // The SmartBox page was still open and the visitor paid: completed at once, tickets issued (seats free), flagged.
        $this->get($this->amwalReturnUrl($payment, $payment->transaction_id.'_1', '2.000'))->assertRedirect();
        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame(['late_capture', true], [$payment->review_reason, (bool) $payment->needs_review]);
        $this->assertSame(2, Ticket::where('transaction_id', $payment->transaction_id)->count());
        $this->assertSame('paid', BoxOfficeOrder::find($order['id'])->status);
        $this->assertContains('box_office_paid_after_close', PaymentEvent::where('payment_id', $payment->id)->pluck('type')->all());
        $this->assertContains('late_capture', PaymentEvent::where('payment_id', $payment->id)->pluck('type')->all());
    }

    #[Test]
    public function a_late_amwal_payment_without_seats_left_issues_nothing_and_is_flagged_for_refund(): void
    {
        $token = $this->sellingDevice();
        $event = $this->todayEvent(['total_tickets' => 2]);
        $first = $this->order($token, $event);
        $this->get($this->checkout($first))->assertOk();
        $late = $this->payment($first);

        $this->gate('POST', "/api/v1/gate/sales/orders/{$first['id']}/cancel", [], $token)->assertOk();
        $this->assertSame('cancelled', $late->fresh()->status);

        // The seats go to someone else.
        $second = $this->order($token, $event, ['buyer_phone' => '99887766']);
        $this->get($this->checkout($second))->assertOk();
        $paid = $this->payment($second);
        $this->get($this->amwalReturnUrl($paid, $paid->transaction_id.'_1', '2.000'));
        $this->assertSame('paid', BoxOfficeOrder::find($second['id'])->status);

        // Amwal captured the first one anyway (no callback, no notification): the reconciler's late-capture watch finds it.
        $this->amwalPays($late->transaction_id.'_1', '2.000');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $late->refresh();
        $this->assertSame('completed', $late->status);
        $this->assertSame(['box_office_refund_required', true], [$late->review_reason, (bool) $late->needs_review]);
        $this->assertSame('paid_unfulfilled', BoxOfficeOrder::find($first['id'])->status);
        $this->assertSame(0, Ticket::where('transaction_id', $late->transaction_id)->count());
    }

    #[Test]
    public function an_order_closed_before_the_page_was_opened_is_cancelled_at_once_without_a_gateway_call(): void
    {
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent());
        $this->checkout($order); // payment created, SmartBox page never opened
        $payment = $this->payment($order);

        $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/cancel", [], $token)->assertOk();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0, count($this->gatewayCalls));
    }

    #[Test]
    public function card_terminal_sales_are_untouched_by_the_gateway_choice(): void
    {
        $this->tenant->putSetting('payments.gateways', ['thawani', 'amwal'])->putSetting('gate_sales.pos_enabled', true)->save();
        $token = $this->sellingDevice();
        $order = $this->order($token, $this->todayEvent(), ['payment_method' => 'pos']);

        $this->assertNull($order['payment_url']);
        $this->assertSame('pos', $order['payment_method']);
        $model = BoxOfficeOrder::findOrFail($order['id']);
        $this->get('/pay/'.$model->public_token)->assertNotFound();
        $this->post('/pay/'.$model->public_token.'/checkout', ['gateway' => 'amwal'])->assertNotFound();
        $this->assertSame(0, Payment::count());
        $this->assertNotNull(User::where('phone_number', '96899112233')->first());
    }
}
