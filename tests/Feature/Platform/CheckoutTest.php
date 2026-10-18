<?php

namespace Tests\Feature\Platform;

use App\Models\Deposit;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function body(int $eventId, array $extra = []): array
    {
        return array_merge([
            'event_id' => $eventId,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'quantity' => 2,
        ], $extra);
    }

    #[Test]
    public function server_computes_the_price_and_ignores_client_amount(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();

        // Legacy app payload incl. a forged amount.
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'payment_type' => 'ticket',
            'payable_id' => $event->id,
            'quantity' => 2,
            'amount' => 0.1,
            'booking_date' => now()->addDay()->format('Y-m-d'),
        ], $user)->assertCreated()->assertJsonPath('status', 'pending');

        $payment = Payment::where('transaction_id', $res->json('transaction_id'))->firstOrFail();
        $this->assertSame(5000, $payment->amount_minor);
        $this->assertSame('5.000', $res->json('amount'));
        $this->assertNotNull($res->json('payment_url'));
        $this->assertSame($res->json('payment_url'), $res->json('checkout_url'));
    }

    #[Test]
    public function quote_endpoint_returns_breakdown_with_ticket_type_pricing_and_deposit(): void
    {
        $event = $this->makeEvent(null, [
            'price' => '2.000',
            'pricing' => [['type' => 'vip', 'price' => '7.250']],
            'deposit_minor' => 500,
        ]);

        $this->getJson('/api/v1/payments/quote?'.http_build_query($this->body($event->id, ['ticket_type' => 'vip'])))
            ->assertOk()
            ->assertJsonPath('data.unit_price_minor', 7250)
            ->assertJsonPath('data.subtotal_minor', 14500)
            ->assertJsonPath('data.deposit_minor', 1000)
            ->assertJsonPath('data.total_minor', 15500)
            ->assertJsonPath('data.total', '15.500')
            ->assertJsonPath('data.currency', 'OMR');
    }

    #[Test]
    public function wallet_checkout_debits_and_issues_tickets(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $this->fund($user, 10000);

        $res = $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'method' => 'wallet', 'idempotency_key' => 'k-1',
        ]), $user)->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonCount(2, 'data.tickets');

        $this->assertSame(5000, $this->wallet($user)->balance_minor);
        $tickets = Ticket::where('transaction_id', $res->json('transaction_id'))->get();
        $this->assertCount(2, $tickets);
        foreach ($tickets as $t) {
            $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}-[A-Z0-9]{12}$/', $t->qr_code);
            $this->assertStringStartsWith($t->ticket_number, $t->qr_code);
            $this->assertSame('2.500', $t->price);
        }

        // Same idempotency key: same payment, no double charge.
        $again = $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'method' => 'wallet', 'idempotency_key' => 'k-1',
        ]), $user)->assertOk();
        $this->assertSame($res->json('transaction_id'), $again->json('transaction_id'));
        $this->assertSame(5000, $this->wallet($user)->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'ticket_purchase')->count());
        $this->assertSame(2, Ticket::count());
    }

    #[Test]
    public function wallet_checkout_fails_with_insufficient_balance_and_leaves_no_trace(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $this->fund($user, 4999);

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'method' => 'wallet', 'idempotency_key' => 'k-2',
        ]), $user)->assertStatus(422)->assertJson(['success' => false, 'code' => 'INSUFFICIENT_BALANCE']);

        $this->assertSame(4999, $this->wallet($user)->balance_minor);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Ticket::count());
    }

    #[Test]
    public function wallet_checkout_spends_promo_first_then_cash(): void
    {
        $event = $this->makeEvent(null, ['price' => '3.000']);
        $user = $this->makeUser();
        $this->fund($user, 5000, null, 'cash');
        $this->fund($user, 2000, null, 'promo');

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'quantity' => 1, 'method' => 'wallet', 'idempotency_key' => 'k-3',
        ]), $user)->assertCreated();

        $wallet = $this->wallet($user);
        $this->assertSame(0, $wallet->promo_minor);
        $this->assertSame(4000, $wallet->cash_minor);
        $tx = WalletTransaction::where('type', 'ticket_purchase')->first();
        $this->assertSame('mixed', $tx->meta['bucket']);
        $this->assertSame(-2000, $tx->promo_delta_minor);
        $this->assertSame(-1000, $tx->cash_delta_minor);

        $this->api('GET', '/api/v1/wallet', [], $user)
            ->assertJsonPath('data.cash_minor', 4000)
            ->assertJsonPath('data.promo_minor', 0)
            ->assertJsonPath('data.refundable_minor', 4000);
    }

    #[Test]
    public function event_deposit_is_held_in_the_wallet_on_wallet_checkout(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.000', 'deposit_minor' => 500, 'deposit_type' => 'wristband']);
        $user = $this->makeUser();
        $this->fund($user, 3000);

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'method' => 'wallet', 'idempotency_key' => 'k-4',
        ]), $user)->assertCreated();

        $wallet = $this->wallet($user);
        $this->assertSame(1000, $wallet->balance_minor);   // 3.000 − 2 × 1.000
        $this->assertSame(1000, $wallet->held_minor);      // 2 × 0.500 deposits
        $this->assertSame(0, $wallet->availableMinor());
        $this->assertSame(2, Deposit::where('status', 'held')->count());
        $this->api('GET', '/api/v1/wallet/deposits', [], $user)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.type', 'wristband');
    }

    #[Test]
    public function sold_out_dates_are_rejected(): void
    {
        $event = $this->makeEvent(null, ['total_tickets' => 1]);
        $user = $this->makeUser();

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, ['quantity' => 2]), $user)
            ->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT');
    }

    #[Test]
    public function event_of_another_tenant_cannot_be_bought(): void
    {
        $other = $this->makeTenant('other');
        $event = $this->makeEvent($other);
        $user = $this->makeUser();

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id), $user)
            ->assertNotFound()->assertJsonPath('code', 'EVENT_NOT_FOUND');
    }

    #[Test]
    public function points_discount_reduces_the_price(): void
    {
        $event = $this->makeEvent(null, ['price' => '5.000']);
        $user = $this->makeUser();
        $this->fund($user, 10000);
        $points = app(\App\Services\Wallet\PointsService::class);
        $points->award($points->accountFor($user, $this->tenant), $this->tenant, 300, 'platform.points.earn', null, 'seed');

        // 200 points × 10 minor = 2.000 OMR discount
        $this->getJson('/api/v1/payments/quote?'.http_build_query($this->body($event->id, ['quantity' => 1, 'points_to_redeem' => 200])))
            ->assertOk()->assertJsonPath('data.points_discount_minor', 2000)->assertJsonPath('data.total_minor', 3000);

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'quantity' => 1, 'method' => 'wallet', 'points_to_redeem' => 200, 'idempotency_key' => 'k-5',
        ]), $user)->assertCreated();

        $this->assertSame(7000, $this->wallet($user)->balance_minor);
        // 100 left + earned floor(3.000 × 10) = 30
        $this->assertSame(130, $points->accountFor($user, $this->tenant)->balance);

        $this->api('POST', '/api/v1/payments/initialize', $this->body($event->id, [
            'quantity' => 1, 'method' => 'wallet', 'points_to_redeem' => 500, 'idempotency_key' => 'k-6',
        ]), $user)->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_POINTS');
    }
}
