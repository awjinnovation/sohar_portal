<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\PointsTransaction;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function points(): PointsService
    {
        return app(PointsService::class);
    }

    private function buyWithWallet($user, $event, int $qty = 1, string $key = 'buy', ?string $date = null)
    {
        return $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => $date ?? now()->addDay()->format('Y-m-d'),
            'quantity' => $qty, 'method' => 'wallet', 'idempotency_key' => $key,
        ], $user)->assertCreated();
    }

    #[Test]
    public function points_are_earned_on_ticket_purchase_with_tier_multiplier(): void
    {
        $event = $this->makeEvent(null, ['price' => '3.750']);
        $user = $this->makeUser();
        $this->fund($user, 50000);

        $this->buyWithWallet($user, $event, 2, 'b1'); // 7.500 OMR × 10 = 75 (bronze ×1.0)
        $account = $this->points()->accountFor($user, $this->tenant);
        $this->assertSame(75, $account->balance);

        // Silver multiplier (1.25) once lifetime ≥ 1000
        $this->points()->award($account, $this->tenant, 1000, 'platform.points.adjust', null, 'bump');
        $this->buyWithWallet($user, $event, 2, 'b2'); // floor(7.5 × 10 × 1.25) = 93
        $this->assertSame(75 + 1000 + 93, $account->refresh()->balance);

        $this->api('GET', '/api/v1/points', [], $user)->assertOk()
            ->assertJsonPath('data.tier.code', 'silver')
            ->assertJsonPath('data.next_tier.code', 'gold')
            ->assertJsonPath('data.points_to_next_tier', 5000 - $account->lifetime_earned);
        $this->api('GET', '/api/v1/points/history', [], $user)->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function points_are_redeemed_into_promo_wallet_credit(): void
    {
        $user = $this->makeUser();
        $this->points()->award($this->points()->accountFor($user, $this->tenant), $this->tenant, 500, 'platform.points.earn', null, 'seed');

        $this->api('POST', '/api/v1/points/redeem', ['points' => 300, 'idempotency_key' => 'r1'], $user)->assertOk()
            ->assertJsonPath('data.points_redeemed', 300)
            ->assertJsonPath('data.credited_minor', 3000)
            ->assertJsonPath('data.credited', '3.000')
            ->assertJsonPath('data.points_balance', 200)
            ->assertJsonPath('data.wallet_balance_minor', 3000);
        // replay
        $this->api('POST', '/api/v1/points/redeem', ['points' => 300, 'idempotency_key' => 'r1'], $user)->assertOk();

        $wallet = $this->wallet($user);
        $this->assertSame(3000, $wallet->promo_minor);
        $this->assertSame(0, $wallet->cash_minor);
        $this->assertSame(0, $wallet->refundableMinor());

        $this->api('POST', '/api/v1/points/redeem', ['points' => 300, 'idempotency_key' => 'r2'], $user)
            ->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_POINTS');
        $this->api('POST', '/api/v1/points/redeem', ['points' => 50, 'idempotency_key' => 'r3'], $user)
            ->assertStatus(422)->assertJsonPath('code', 'LIMIT_EXCEEDED'); // below min_redeem_points
    }

    #[Test]
    public function expired_points_are_removed_fifo_by_the_daily_command(): void
    {
        $user = $this->makeUser();
        $account = $this->points()->accountFor($user, $this->tenant);
        $this->points()->award($account, $this->tenant, 100, 'platform.points.earn', null, 'old');
        $this->travel(200)->days();
        $this->points()->award($account, $this->tenant, 50, 'platform.points.earn', null, 'new');
        // spend 30: consumed from the oldest lot first
        $this->points()->spend($user, $this->tenant, 30, null, 'spend');

        $this->travel(170)->days(); // first lot (365d) now expired, second not
        $this->artisan('points:expire')->assertSuccessful();

        $this->assertSame(50, $account->refresh()->balance);
        $expire = PointsTransaction::where('type', 'expire')->sole();
        $this->assertSame(-70, $expire->points);

        $this->artisan('points:expire')->assertSuccessful(); // idempotent
        $this->assertSame(50, $account->refresh()->balance);
    }

    #[Test]
    public function points_earned_are_reversed_when_the_payment_is_refunded(): void
    {
        $event = $this->makeEvent(null, ['price' => '5.000']);
        $user = $this->makeUser();
        $this->fund($user, 20000);
        $res = $this->buyWithWallet($user, $event, 2, 'b1'); // 100 points
        $account = $this->points()->accountFor($user, $this->tenant);
        $this->assertSame(100, $account->balance);

        $payment = Payment::where('transaction_id', $res->json('transaction_id'))->firstOrFail();
        app(WalletAdminService::class)->refundPayment($payment, $this->makeUser(), 'cancelled show');
        app(WalletAdminService::class)->refundPayment($payment->refresh(), $this->makeUser()); // idempotent

        $this->assertSame(0, $account->refresh()->balance);
        $this->assertSame(-100, PointsTransaction::where('type', 'adjust')->sole()->points);
        $this->assertSame(20000, $this->wallet($user)->balance_minor);
        $this->assertSame('refunded', $payment->refresh()->status);
        $this->assertSame(2, \App\Models\Ticket::where('status', 'cancelled')->count());
    }

    #[Test]
    public function first_gate_check_in_awards_points_when_enabled(): void
    {
        $this->seedRoles();
        $this->tenant->putSetting('points.earn_on_checkin', true)->putSetting('points.checkin_points', 25)->save();
        $event = $this->makeEvent(null, ['price' => '1.000']);
        $user = $this->makeUser();
        $this->fund($user, 5000);
        // A ticket for the current gate day (the gate rejects other dates).
        $res = $this->buyWithWallet($user, $event, 1, 'b1', \App\Services\Gate\GateDay::today()); // +10
        $ticket = \App\Models\Ticket::where('transaction_id', $res->json('transaction_id'))->sole();

        $staff = $this->makeUser();
        $this->grantRole($staff, 'staff');
        $this->api('POST', "/api/v1/tickets/{$ticket->id}/mark-used", [], $staff)->assertOk();
        $this->api('POST', "/api/v1/tickets/{$ticket->id}/mark-used", [], $staff)->assertStatus(400);

        $this->assertSame(35, $this->points()->accountFor($user, $this->tenant)->balance);
    }
}
