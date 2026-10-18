<?php

namespace Tests\Feature\Platform;

use App\Models\WalletPromoLot;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class PromoExpiryTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function wallets(): WalletService
    {
        return app(WalletService::class);
    }

    private function enableExpiry(?int $days): void
    {
        $this->tenant->putSetting('wallet.promo_expiry_days', $days)->save();
        $this->tenant->refresh();
        $this->actingAsTenant($this->tenant);
    }

    private function promo($wallet, int $minor, string $key)
    {
        return $this->wallets()->credit($wallet, $minor, 'adjustment', ['bucket' => 'promo', 'idempotency_key' => $key]);
    }

    #[Test]
    public function promo_never_expires_by_default(): void
    {
        $wallet = $this->fund($this->makeUser(), 1000, null, 'promo');
        $this->assertNull(WalletPromoLot::first()->expires_at);

        $this->travel(5 * 365)->days();
        $this->artisan('wallet:expire-promo')->assertSuccessful();
        $this->assertSame(1000, $wallet->fresh()->promo_minor);
    }

    #[Test]
    public function remaining_promo_expires_fifo_and_cash_is_untouched(): void
    {
        $this->enableExpiry(30);
        $user = $this->makeUser();
        $wallet = $this->fund($user, 5000); // cash
        $first = $this->promo($wallet, 1000, 'p1');
        $this->assertNotNull($first->meta['expires_at']);
        $this->assertStringEndsWith('+04:00', $first->meta['expires_at']);

        $this->travel(10)->days();
        $this->promo($wallet, 800, 'p2'); // expires 10 days later than the first

        // Spending 300 consumes promo first, from the oldest lot.
        $this->wallets()->debit($wallet, 300, 'vendor_payment', ['idempotency_key' => 'spend']);
        $this->assertSame([700, 800], WalletPromoLot::orderBy('id')->pluck('remaining_minor')->all());

        // Day 31: only the first lot's remainder expires.
        $this->travel(21)->days();
        $this->artisan('wallet:expire-promo')->assertSuccessful();
        $wallet->refresh();
        $this->assertSame(800, $wallet->promo_minor);
        $this->assertSame(5000, $wallet->cash_minor);

        $expiry = WalletTransaction::where('type', 'adjustment')->where('direction', 'debit')->sole();
        $this->assertSame(700, $expiry->amount_minor);
        $this->assertSame('promo_expired', $expiry->meta['reason']);
        $this->assertSame('promo', $expiry->meta['bucket']);
        $this->assertSame(-700, $expiry->promo_delta_minor);

        // Running again the same day does nothing.
        $this->artisan('wallet:expire-promo')->assertSuccessful();
        $this->assertSame(1, WalletTransaction::where('type', 'adjustment')->where('direction', 'debit')->count());

        // Day 41: the second lot expires too.
        $this->travel(10)->days();
        $this->artisan('wallet:expire-promo')->assertSuccessful();
        $this->assertSame(0, $wallet->fresh()->promo_minor);
        $this->assertSame(0, (int) WalletPromoLot::sum('remaining_minor'));

        // The app sees the expiry in the ledger.
        $rows = collect($this->api('GET', '/api/v1/wallet/transactions', [], $user)->assertOk()->json('data'));
        $this->assertSame('promo_expired', $rows->firstWhere('amount_minor', 800)['meta']['reason']);
        $this->assertNotNull($rows->firstWhere('amount_minor', 1000)['expires_at']);
    }

    #[Test]
    public function expiry_never_breaks_held_deposits(): void
    {
        $this->enableExpiry(1);
        $wallet = $this->fund($this->makeUser(), 0 + 1, null, 'cash');
        $this->promo($wallet, 999, 'p');
        $this->wallets()->hold($wallet, 900, ['idempotency_key' => 'hold']);

        $this->travel(2)->days();
        $this->artisan('wallet:expire-promo')->assertSuccessful();
        $wallet->refresh();
        // available was 100 → only 100 expired; held stays covered.
        $this->assertSame(899, $wallet->promo_minor);
        $this->assertSame(900, $wallet->held_minor);
        $this->assertSame(0, $wallet->availableMinor());
    }

    #[Test]
    public function the_command_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($e) => str_contains($e->command, 'wallet:expire-promo')));
    }
}
