<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\WalletService;

class DepositAndPaymentAdminTest extends AdminPlatformTestCase
{
    public function test_release_and_forfeit_deposits(): void
    {
        $admin = $this->tenantUser();
        $wallet = $this->walletWithCash(User::factory()->create(), 5000);
        $vendor = Vendor::create(['name' => ['en' => 'Bar', 'ar' => 'بار'], 'is_active' => true]);
        $cup = app(DepositService::class)->hold($wallet, 'cup', 500, 'cup-1', ['vendor_id' => $vendor->id]);
        $band = app(DepositService::class)->hold($wallet->fresh(), 'wristband', 1000, 'band-1');
        $this->assertSame(1500, $wallet->fresh()->held_minor);

        $this->actingAs($admin)->get(route('admin.deposits.index', ['status' => 'held', 'vendor_id' => $vendor->id]))
            ->assertOk()->assertSee('بار');

        $this->actingAs($admin)->post(route('admin.deposits.release', $cup))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.deposits.release', $band), ['forfeit' => '1'])->assertSessionHas('success');

        $this->assertSame('released', $cup->fresh()->status);
        $this->assertSame('forfeited', $band->fresh()->status);
        $wallet->refresh();
        $this->assertSame(0, $wallet->held_minor);
        $this->assertSame(4000, $wallet->balance_minor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposit.forfeit', 'subject_id' => $band->id]);

        // Releasing twice is refused.
        $this->actingAs($admin)->post(route('admin.deposits.release', $cup))->assertSessionHas('error');
    }

    public function test_refund_wallet_ticket_payment(): void
    {
        $admin = $this->tenantUser();
        $user = User::factory()->create(['phone_number' => '96890001234']);
        $wallet = $this->walletWithCash($user, 5000);
        $payment = Payment::create([
            'user_id' => $user->id, 'transaction_id' => 'TX-WALLET-1', 'payment_type' => 'ticket', 'method' => 'wallet',
            'amount' => 2, 'amount_minor' => 2000, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now(),
        ]);
        app(WalletService::class)->debit($wallet, 2000, 'ticket_purchase', ['reference' => $payment, 'idempotency_key' => 'pay-1']);
        $this->assertSame(3000, $wallet->fresh()->balance_minor);

        $this->actingAs($admin)->get(route('admin.payments.index', ['q' => '96890001234']))->assertOk()->assertSee('TX-WALLET-1');
        $this->actingAs($admin)->get(route('admin.payments.show', $payment))->assertOk()->assertSee('استرداد الدفعة');

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'Event cancelled'])
            ->assertRedirect(route('admin.payments.show', $payment))->assertSessionHas('success');

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(5000, $wallet->fresh()->balance_minor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refund', 'subject_id' => $payment->id]);
    }

    public function test_topup_payments_are_not_refundable_here(): void
    {
        $admin = $this->tenantUser();
        $payment = Payment::create([
            'user_id' => User::factory()->create()->id, 'transaction_id' => 'TX-TOPUP-1', 'payment_type' => 'topup', 'method' => 'gateway',
            'amount' => 5, 'amount_minor' => 5000, 'currency' => 'OMR', 'status' => 'completed',
        ]);

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment))->assertSessionHas('error');
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_viewer_cannot_refund_payments(): void
    {
        $viewer = $this->tenantUser('viewer');
        $payment = Payment::create([
            'user_id' => User::factory()->create()->id, 'transaction_id' => 'TX-V-1', 'payment_type' => 'ticket', 'method' => 'gateway',
            'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed',
        ]);

        $this->actingAs($viewer)->get(route('admin.payments.show', $payment))->assertOk()->assertDontSee('استرداد الدفعة');
        $this->actingAs($viewer)->post(route('admin.payments.refund', $payment))->assertForbidden();
    }
}
