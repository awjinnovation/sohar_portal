<?php

namespace Tests\Feature\Platform;

use App\Models\Deposit;
use App\Models\PayToken;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private $customer;

    private $cashier;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->customer = $this->makeUser();
        $this->customer->update(['name' => 'Fatma Al Balushi']);
        $this->fund($this->customer, 10000);
        $this->cashier = $this->makeUser();
        $this->grantRole($this->cashier, 'vendor-staff');
        $this->vendor = Vendor::create(['name' => ['en' => 'Karak House', 'ar' => 'بيت الكرك'], 'is_active' => true]);
        $this->vendor->staff()->attach($this->cashier->id, ['role' => 'staff']);
    }

    private function payToken(): string
    {
        return $this->api('POST', '/api/v1/wallet/pay-token', [], $this->customer)->assertOk()
            ->assertJsonPath('data.expires_in', 60)->json('data.token');
    }

    private function charge(string $token, int $amount = 1500, string $key = 'c1')
    {
        return $this->api('POST', '/api/v1/merchant/charge', [
            'pay_token' => $token, 'amount_minor' => $amount, 'description' => '2 karak', 'idempotency_key' => $key,
        ], $this->cashier);
    }

    #[Test]
    public function vendor_staff_can_charge_a_wallet_with_a_pay_token(): void
    {
        $this->charge($this->payToken())->assertOk()
            ->assertJsonPath('data.amount', '1.500')
            ->assertJsonPath('data.customer_display_name', 'Fatma B.')
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(8500, $this->wallet($this->customer)->balance_minor);
        $tx = WalletTransaction::where('type', 'vendor_payment')->sole();
        $this->assertSame($this->vendor->id, $tx->vendor_id);
        $this->assertSame($this->cashier->id, $tx->created_by);
        // points: floor(1.5 × 10) = 15
        $this->assertSame(15, app(PointsService::class)->accountFor($this->customer, $this->tenant)->balance);

        $this->api('GET', '/api/v1/merchant/transactions', [], $this->cashier)->assertOk()
            ->assertJsonPath('data.totals.sales_minor', 1500)->assertJsonPath('data.totals.count', 1);
        $this->api('GET', '/api/v1/merchant/me', [], $this->cashier)->assertOk()
            ->assertJsonPath('data.vendor.name', 'Karak House');
    }

    #[Test]
    public function a_pay_token_is_single_use(): void
    {
        $token = $this->payToken();
        $this->charge($token, 1000, 'k1')->assertOk();
        // same request retried with the same key -> same result, no second charge
        $this->charge($token, 1000, 'k1')->assertOk();
        // a new charge with the used token
        $this->charge($token, 1000, 'k2')->assertStatus(410)->assertJsonPath('code', 'PAY_TOKEN_EXPIRED');

        $this->assertSame(9000, $this->wallet($this->customer)->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'vendor_payment')->count());
    }

    #[Test]
    public function expired_tampered_or_foreign_tokens_are_rejected(): void
    {
        $token = $this->payToken();
        $this->travel(61)->seconds();
        $this->charge($token)->assertStatus(410)->assertJsonPath('code', 'PAY_TOKEN_EXPIRED');
        $this->travelBack();

        $parts = explode('.', $this->payToken());
        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $claims['uid'] = 999;
        $parts[1] = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $this->charge(implode('.', $parts), 1000, 'k3')->assertStatus(422)->assertJsonPath('code', 'PAY_TOKEN_INVALID');

        // token issued in another tenant
        $other = $this->makeTenant('other');
        $this->fund($this->customer, 5000, $other);
        $foreign = $this->api('POST', '/api/v1/wallet/pay-token', [], $this->customer, $other)->json('data.token');
        $this->charge($foreign, 1000, 'k4')->assertStatus(422)->assertJsonPath('code', 'PAY_TOKEN_INVALID');

        $this->assertSame(10000, $this->wallet($this->customer)->balance_minor);
        $this->assertSame(5000, $this->wallet($this->customer, $other)->balance_minor);
    }

    #[Test]
    public function charge_beyond_available_balance_fails_and_burns_nothing(): void
    {
        $this->charge($this->payToken(), 20000)->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_BALANCE');
        $this->assertSame(10000, $this->wallet($this->customer)->balance_minor);
        // token burn was rolled back with the failed charge
        $this->assertSame(1, PayToken::whereNull('used_at')->count());
    }

    #[Test]
    public function regular_users_cannot_use_merchant_endpoints(): void
    {
        $this->api('POST', '/api/v1/merchant/charge', ['pay_token' => 'x', 'amount_minor' => 1, 'idempotency_key' => 'z'], $this->makeUser())
            ->assertForbidden();
        // vendor-staff role in this tenant does not grant access in another tenant
        $other = $this->makeTenant('other');
        $this->api('GET', '/api/v1/merchant/me', [], $this->cashier, $other)->assertForbidden();
    }

    #[Test]
    public function staff_take_and_release_deposits(): void
    {
        $res = $this->api('POST', '/api/v1/merchant/deposits', [
            'pay_token' => $this->payToken(), 'type' => 'cup', 'amount_minor' => 500, 'label' => 'Cup #12', 'idempotency_key' => 'd1',
        ], $this->cashier)->assertOk()->assertJsonPath('data.status', 'held');

        $this->assertSame(9500, $this->wallet($this->customer)->availableMinor());

        $id = $res->json('data.id');
        $this->api('POST', "/api/v1/merchant/deposits/$id/release", ['forfeit' => false], $this->cashier)
            ->assertOk()->assertJsonPath('data.status', 'released');
        $this->assertSame(10000, $this->wallet($this->customer)->availableMinor());
        $this->assertSame('released', Deposit::find($id)->status);
    }

    #[Test]
    public function vendor_charge_refund_reverses_points(): void
    {
        $this->charge($this->payToken(), 5000)->assertOk(); // 50 points
        $tx = WalletTransaction::where('type', 'vendor_payment')->sole();

        app(WalletAdminService::class)->refundVendorCharge($tx, $this->makeUser(), 'wrong amount');

        $this->assertSame(10000, $this->wallet($this->customer)->balance_minor);
        $this->assertSame(0, app(PointsService::class)->accountFor($this->customer, $this->tenant)->balance);
    }
}
