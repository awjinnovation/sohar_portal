<?php

namespace Tests\Feature\Platform;

use App\Models\RefundRequest;
use App\Models\WalletTransaction;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\RefundRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function request($user, array $body)
    {
        return $this->api('POST', '/api/v1/wallet/refund-requests', $body + ['method' => 'original_payment', 'idempotency_key' => 'rr-'.uniqid()], $user);
    }

    #[Test]
    public function refund_request_holds_funds_until_decided(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 8000);

        $res = $this->request($user, ['amount_minor' => 5000, 'reason' => 'Leaving'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount_minor', 5000);

        $wallet = $this->wallet($user);
        $this->assertSame(3000, $wallet->balance_minor);
        $tx = WalletTransaction::where('type', 'refund')->firstOrFail();
        $this->assertSame('pending', $tx->status);
        $this->assertSame('cash', $tx->meta['bucket']);

        $this->api('GET', '/api/v1/wallet/refund-requests', [], $user)->assertOk()->assertJsonCount(1, 'data');

        $admin = $this->makeUser();
        $rr = RefundRequest::findOrFail($res->json('data.id'));
        $svc = app(RefundRequestService::class);
        $svc->approve($rr, $admin, 'ok');
        $svc->markPaid($rr->refresh(), $admin);

        $this->assertSame('paid', $rr->refresh()->status);
        $this->assertSame('completed', $tx->refresh()->status);
        $this->assertSame(3000, $this->wallet($user)->balance_minor);
    }

    #[Test]
    public function rejected_refund_returns_cash_to_the_wallet(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 8000);
        $res = $this->request($user, ['amount_minor' => 5000])->assertCreated();

        $rr = RefundRequest::findOrFail($res->json('data.id'));
        app(RefundRequestService::class)->reject($rr, $this->makeUser(), 'not eligible');
        app(RefundRequestService::class)->reject($rr->refresh(), $this->makeUser()); // idempotent

        $wallet = $this->wallet($user);
        $this->assertSame(8000, $wallet->balance_minor);
        $this->assertSame(8000, $wallet->cash_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'refund_reversal')->count());
        $this->assertSame('reversed', WalletTransaction::where('type', 'refund')->value('status'));
    }

    #[Test]
    public function refund_is_limited_to_refundable_cash(): void
    {
        $user = $this->makeUser();
        $wallet = $this->fund($user, 6000);
        $this->fund($user, 4000, null, 'promo');              // promo is never refundable
        app(DepositService::class)->hold($wallet, 'cup', 1000, 'cup'); // held deposits excluded

        $this->api('GET', '/api/v1/wallet', [], $user)->assertJsonPath('data.refundable_minor', 5000);

        $this->request($user, ['amount_minor' => 5001])->assertStatus(422)->assertJsonPath('code', 'REFUND_NOT_ALLOWED');

        // Omitting the amount = full refundable balance.
        $this->request($user, [])->assertCreated()->assertJsonPath('data.amount_minor', 5000);
        $wallet->refresh();
        $this->assertSame(1000, $wallet->cash_minor);
        $this->assertSame(4000, $wallet->promo_minor);
        $this->assertSame(1000, $wallet->held_minor);
        $this->assertSame(0, $wallet->refundableMinor());
    }

    #[Test]
    public function refund_rules_from_tenant_settings_are_enforced(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 5000);

        $this->request($user, ['amount_minor' => 500])->assertStatus(422)->assertJsonPath('code', 'REFUND_NOT_ALLOWED'); // min 1.000
        $this->request($user, ['amount_minor' => 2000, 'method' => 'bank_transfer'])->assertStatus(422); // iban required

        $this->tenant->putSetting('wallet.refunds.enabled', false)->save();
        $this->request($user, ['amount_minor' => 2000])->assertStatus(422)->assertJsonPath('code', 'REFUND_NOT_ALLOWED');
    }

    #[Test]
    public function refund_request_is_idempotent(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 5000);
        $a = $this->request($user, ['amount_minor' => 2000, 'idempotency_key' => 'same'])->assertCreated();
        $b = $this->request($user, ['amount_minor' => 2000, 'idempotency_key' => 'same'])->assertOk();

        $this->assertSame($a->json('data.id'), $b->json('data.id'));
        $this->assertSame(3000, $this->wallet($user)->balance_minor);
    }
}
