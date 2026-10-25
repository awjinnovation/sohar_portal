<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\Ticket;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\BankMuscatCrypto;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class TopupAndGatewayTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function startTopup($user, int $amount = 10000, string $key = 'top-1')
    {
        return $this->api('POST', '/api/v1/wallet/topups', ['amount_minor' => $amount, 'idempotency_key' => $key], $user);
    }

    private function fakeCompleteUrl(string $paymentUrl): string
    {
        $page = $this->get($paymentUrl)->assertOk()->assertSee('Simulate success');
        preg_match('/action="([^"]+)"/', $page->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    #[Test]
    public function fake_gateway_topup_credits_the_wallet_exactly_once(): void
    {
        $user = $this->makeUser();
        $res = $this->startTopup($user)->assertCreated()->assertJsonPath('data.status', 'pending');
        $topupId = $res->json('data.topup_id');
        $url = $res->json('data.payment_url');
        $this->assertStringContainsString('signature=', $url);

        $complete = $this->fakeCompleteUrl($url);
        $this->post($complete, ['result' => 'success'])->assertRedirect();
        // Callback fires twice (redirect + webhook retry) — still one credit.
        $this->post($complete, ['result' => 'success']);
        $payment = Payment::where('payment_type', 'topup')->firstOrFail();
        app(PaymentFulfillmentService::class)->complete($payment);

        $this->assertSame(10000, $this->wallet($user)->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'topup')->count());
        $this->api('GET', "/api/v1/wallet/topups/$topupId", [], $user)
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.amount', '10.000');
    }

    #[Test]
    public function failed_topup_does_not_credit(): void
    {
        $user = $this->makeUser();
        $res = $this->startTopup($user)->assertCreated();
        $this->post($this->fakeCompleteUrl($res->json('data.payment_url')), ['result' => 'failure'])->assertRedirect();

        $this->assertSame(0, $this->wallet($user)->balance_minor);
        $this->assertSame('failed', WalletTopup::first()->status);
        $this->assertSame('failed', Payment::first()->status);
    }

    #[Test]
    public function topup_initialization_is_idempotent_and_limited(): void
    {
        $user = $this->makeUser();
        $a = $this->startTopup($user, 5000, 'same')->assertCreated();
        $b = $this->startTopup($user, 5000, 'same')->assertOk();
        $this->assertSame($a->json('data.topup_id'), $b->json('data.topup_id'));
        $this->assertSame(1, WalletTopup::count());

        $this->startTopup($user, 10, 'too-small')->assertStatus(422)->assertJsonPath('code', 'LIMIT_EXCEEDED');
        $this->startTopup($user, 999999999, 'too-big')->assertStatus(422)->assertJsonPath('code', 'LIMIT_EXCEEDED');
    }

    #[Test]
    public function payment_link_requires_a_valid_signature(): void
    {
        $user = $this->makeUser();
        $res = $this->startTopup($user)->assertCreated();
        $tx = $res->json('data.transaction_id');

        $this->get("/payment/redirect/$tx")->assertForbidden();
        $this->post("/payment/fake/$tx", ['result' => 'success'])->assertForbidden();
        $this->assertSame(0, $this->wallet($user)->balance_minor);
    }

    #[Test]
    public function topup_bonus_is_credited_as_promo(): void
    {
        $this->tenant->putSetting('wallet.topup_bonus', [['min_minor' => 10000, 'bonus_percent' => 5], ['min_minor' => 50000, 'bonus_percent' => 10]])->save();
        $user = $this->makeUser();
        $res = $this->startTopup($user, 20000)->assertCreated();
        $this->post($this->fakeCompleteUrl($res->json('data.payment_url')), ['result' => 'success']);

        $wallet = $this->wallet($user);
        $this->assertSame(20000, $wallet->cash_minor);
        $this->assertSame(1000, $wallet->promo_minor);
        $this->assertSame(1000, WalletTopup::first()->bonus_minor);
    }

    #[Test]
    public function gateway_ticket_purchase_issues_tickets_once_via_bank_muscat_callback(): void
    {
        config(['payments.driver' => 'bankmuscat', 'services.bank_muscat.working_key' => str_repeat('k', 32)]);
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();

        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 2,
        ], $user)->assertCreated();
        $txId = $res->json('transaction_id');

        $enc = BankMuscatCrypto::encrypt(http_build_query([
            'order_id' => $txId, 'order_status' => 'Success', 'currency' => 'OMR', 'amount' => '5.000', 'payment_mode' => 'Credit Card',
        ]), str_repeat('k', 32));

        // Browser redirect and server webhook both arrive.
        $this->post("/payment/success?transaction_id=$txId", ['encResp' => $enc])->assertOk()->assertSee($txId);
        $this->postJson('/api/v1/payments/webhook', ['encResp' => $enc])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('completed', Payment::first()->status);
        $this->assertSame(2, Ticket::where('transaction_id', $txId)->count());

        // Tampered amount is rejected and does not complete anything.
        $res2 = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
        ], $user)->assertCreated();
        $bad = BankMuscatCrypto::encrypt(http_build_query([
            'order_id' => $res2->json('transaction_id'), 'order_status' => 'Success', 'currency' => 'OMR', 'amount' => '0.100',
        ]), str_repeat('k', 32));
        $this->postJson('/api/v1/payments/webhook', ['encResp' => $bad])->assertStatus(400);
        $this->assertSame('pending', Payment::where('transaction_id', $res2->json('transaction_id'))->value('status'));

        // Garbage payload (wrong key) is rejected.
        $forged = BankMuscatCrypto::encrypt('order_id='.$res2->json('transaction_id').'&order_status=Success&currency=OMR&amount=2.500', str_repeat('x', 32));
        $this->postJson('/api/v1/payments/webhook', ['encResp' => $forged])->assertStatus(400);
        $this->assertSame('pending', Payment::where('transaction_id', $res2->json('transaction_id'))->value('status'));
    }

    #[Test]
    public function gateway_checkout_with_deposit_funds_and_holds_it_in_the_wallet(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.000', 'deposit_minor' => 500]);
        $user = $this->makeUser();
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 2,
        ], $user)->assertCreated()->assertJsonPath('amount_minor', 3000);

        $this->post($this->fakeCompleteUrl($res->json('payment_url')), ['result' => 'success']);

        $wallet = $this->wallet($user);
        $this->assertSame(1000, $wallet->balance_minor);
        $this->assertSame(1000, $wallet->held_minor);
        $this->assertSame(0, $wallet->availableMinor());
        $this->assertSame(2, Ticket::count());
    }

    #[Test]
    public function webhook_resolves_the_payment_tenant_and_enforces_its_own_key(): void
    {
        config(['payments.driver' => 'bankmuscat', 'services.bank_muscat.working_key' => str_repeat('p', 32)]);
        $shared = $this->makeTenant('shared'); // uses the platform key
        $own = $this->makeTenant('own');
        $own->gateway_credentials = ['bank_muscat' => ['working_key' => str_repeat('o', 32)]];
        $own->save();

        $user = $this->makeUser();
        $init = function ($tenant) use ($user) {
            $event = $this->makeEvent($tenant, ['price' => '1.000']);

            return $this->api('POST', '/api/v1/payments/initialize', [
                'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
            ], $user, $tenant)->assertCreated()->json('transaction_id');
        };
        $payload = fn ($tx) => http_build_query(['order_id' => $tx, 'order_status' => 'Success', 'currency' => 'OMR', 'amount' => '1.000']);

        // Payment of the 2nd tenant sharing the platform key: no hint needed.
        $tx1 = $init($shared);
        $this->postJson('/api/v1/payments/webhook', ['encResp' => BankMuscatCrypto::encrypt($payload($tx1), str_repeat('p', 32))])->assertOk();
        $this->assertSame('completed', Payment::withoutTenantScope()->where('transaction_id', $tx1)->value('status'));

        // Tenant with its own key: a response encrypted with the platform key is rejected.
        $tx2 = $init($own);
        $this->postJson('/api/v1/payments/webhook', ['encResp' => BankMuscatCrypto::encrypt($payload($tx2), str_repeat('p', 32))])->assertStatus(400);
        $this->assertSame('pending', Payment::withoutTenantScope()->where('transaction_id', $tx2)->value('status'));
        $this->postJson('/api/v1/payments/webhook', ['encResp' => BankMuscatCrypto::encrypt($payload($tx2), str_repeat('o', 32))])->assertOk();
        $this->assertSame('completed', Payment::withoutTenantScope()->where('transaction_id', $tx2)->value('status'));
    }
}
