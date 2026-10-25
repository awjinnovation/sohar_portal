<?php

namespace Tests\Feature\Platform;

use App\Exceptions\ApiException;
use App\Models\WalletTransaction;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function svc(): WalletService
    {
        return app(WalletService::class);
    }

    #[Test]
    public function debit_with_the_same_idempotency_key_is_applied_once(): void
    {
        $user = $this->makeUser();
        $wallet = $this->fund($user, 10000);

        // Simulates the client retrying / two concurrent requests with the same key.
        $results = collect(range(1, 5))->map(fn () => $this->svc()->debit($wallet, 3000, 'vendor_payment', ['idempotency_key' => 'pay-123']));

        $this->assertCount(1, $results->pluck('id')->unique());
        $this->assertSame(7000, $wallet->refresh()->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'vendor_payment')->count());
        $this->assertSame(7000, $results->first()->balance_after_minor);
    }

    #[Test]
    public function reusing_a_key_for_a_different_operation_is_a_conflict(): void
    {
        $wallet = $this->fund($this->makeUser(), 10000);
        $this->svc()->debit($wallet, 1000, 'vendor_payment', ['idempotency_key' => 'k']);

        $this->expectExceptionObject(ApiException::idempotencyConflict());
        $this->svc()->debit($wallet, 2000, 'vendor_payment', ['idempotency_key' => 'k']);
    }

    #[Test]
    public function ledger_is_consistent_with_wallet_balance(): void
    {
        $wallet = $this->fund($this->makeUser(), 10000);
        $this->svc()->debit($wallet, 1234, 'vendor_payment');
        $this->svc()->credit($wallet, 500, 'adjustment', ['bucket' => 'promo']);
        $this->svc()->debit($wallet, 700, 'vendor_payment');

        $wallet->refresh();
        $last = WalletTransaction::where('wallet_id', $wallet->id)->orderByDesc('id')->first();
        $this->assertSame($wallet->balance_minor, $last->balance_after_minor);
        $this->assertSame($wallet->balance_minor, (int) WalletTransaction::where('wallet_id', $wallet->id)->sum(\DB::raw('cash_delta_minor + promo_delta_minor')));
        $this->assertSame(10000 - 1234 + 500 - 700, $wallet->balance_minor);
        // promo consumed first by the second debit
        $this->assertSame(0, $wallet->promo_minor);
    }

    #[Test]
    public function insufficient_balance_and_frozen_wallets_are_rejected(): void
    {
        $wallet = $this->fund($this->makeUser(), 1000);

        try {
            $this->svc()->debit($wallet, 1001, 'vendor_payment');
            $this->fail('expected INSUFFICIENT_BALANCE');
        } catch (ApiException $e) {
            $this->assertSame('INSUFFICIENT_BALANCE', $e->errorCode);
        }

        $this->svc()->setStatus($wallet, 'frozen');
        try {
            $this->svc()->debit($wallet, 10, 'vendor_payment');
            $this->fail('expected WALLET_FROZEN');
        } catch (ApiException $e) {
            $this->assertSame('WALLET_FROZEN', $e->errorCode);
        }
        $this->assertSame(1000, $wallet->refresh()->balance_minor);
    }

    #[Test]
    public function deposits_hold_release_and_forfeit_change_available_balance(): void
    {
        $user = $this->makeUser();
        $wallet = $this->fund($user, 5000);
        $deposits = app(DepositService::class);

        $d1 = $deposits->hold($wallet, 'cup', 1000, 'cup-1', ['label' => 'Cup']);
        $d2 = $deposits->hold($wallet, 'locker', 2000, 'locker-1');
        $deposits->hold($wallet, 'cup', 1000, 'cup-1'); // idempotent
        $wallet->refresh();
        $this->assertSame(5000, $wallet->balance_minor);
        $this->assertSame(3000, $wallet->held_minor);
        $this->assertSame(2000, $wallet->availableMinor());
        $this->assertSame(2000, $wallet->refundableMinor());

        // Cannot spend held funds
        try {
            $this->svc()->debit($wallet, 2500, 'vendor_payment');
            $this->fail('held funds must not be spendable');
        } catch (ApiException $e) {
            $this->assertSame('INSUFFICIENT_BALANCE', $e->errorCode);
        }

        $deposits->release($d1);
        $deposits->release($d1); // idempotent
        $wallet->refresh();
        $this->assertSame(5000, $wallet->balance_minor);
        $this->assertSame(2000, $wallet->held_minor);
        $this->assertSame(3000, $wallet->availableMinor());

        $deposits->release($d2, forfeit: true);
        $wallet->refresh();
        $this->assertSame(3000, $wallet->balance_minor);
        $this->assertSame(0, $wallet->held_minor);
        $this->assertSame(3000, $wallet->availableMinor());
        $this->assertSame(['released', 'forfeited'], [$d1->refresh()->status, $d2->refresh()->status]);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit_forfeit')->count());
        $this->assertSame(1, WalletTransaction::where('type', 'deposit_release')->count());
    }

    #[Test]
    public function wallet_api_reports_buckets_and_ledger(): void
    {
        $user = $this->makeUser();
        $wallet = $this->fund($user, 6000);
        $this->fund($user, 1500, null, 'promo');
        app(DepositService::class)->hold($wallet, 'wristband', 1000, 'w1');

        $this->api('GET', '/api/v1/wallet', [], $user)->assertOk()
            ->assertJsonPath('data.balance_minor', 7500)
            ->assertJsonPath('data.balance', '7.500')
            ->assertJsonPath('data.available_minor', 6500)
            ->assertJsonPath('data.held_deposits_minor', 1000)
            ->assertJsonPath('data.cash_minor', 6000)
            ->assertJsonPath('data.promo_minor', 1500)
            ->assertJsonPath('data.refundable_minor', 5000)
            ->assertJsonStructure(['data' => ['points' => ['balance', 'tier_code', 'next_tier_code', 'points_to_next_tier']]]);

        $this->api('GET', '/api/v1/wallet/transactions?type=topup', [], $user)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'credit')
            ->assertJsonPath('data.0.title', 'Wallet top-up')
            ->assertJsonStructure(['data' => [['id', 'type', 'direction', 'amount_minor', 'amount', 'balance_after_minor', 'title', 'description', 'reference', 'status', 'created_at', 'meta']], 'pagination']);

        $this->withHeader('Accept-Language', 'ar')->api('GET', '/api/v1/wallet/transactions?type=topup', [], $user)
            ->assertJsonPath('data.0.title', 'شحن المحفظة');
    }

    #[Test]
    public function wallet_module_can_be_disabled_per_tenant(): void
    {
        $this->tenant->putSetting('modules.wallet', false)->save();
        $this->api('GET', '/api/v1/wallet', [], $this->makeUser())->assertForbidden()->assertJsonPath('code', 'MODULE_DISABLED');
    }
}
