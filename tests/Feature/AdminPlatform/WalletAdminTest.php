<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;

class WalletAdminTest extends AdminPlatformTestCase
{
    public function test_manual_credit_creates_ledger_and_audit_rows(): void
    {
        $admin = $this->tenantUser();
        $wallet = $this->walletWithCash(User::factory()->create(), 0);

        $this->actingAs($admin)->post(route('admin.wallets.adjust', $wallet), [
            'amount' => '1.500', 'direction' => 'credit', 'bucket' => 'promo', 'reason' => 'Goodwill',
        ])->assertRedirect(route('admin.wallets.show', $wallet))->assertSessionHas('success');

        $wallet->refresh();
        $this->assertSame(1500, $wallet->promo_minor);
        $this->assertSame(1500, $wallet->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id, 'type' => 'adjustment', 'direction' => 'credit',
            'amount_minor' => 1500, 'created_by' => $admin->id, 'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'wallet.adjustment', 'subject_id' => $wallet->id, 'actor_id' => $admin->id, 'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_manual_debit_and_validation(): void
    {
        $admin = $this->tenantUser();
        $wallet = $this->walletWithCash(User::factory()->create(), 5000);

        $this->actingAs($admin)->post(route('admin.wallets.adjust', $wallet), [
            'amount' => '2', 'direction' => 'debit', 'bucket' => 'cash', 'reason' => 'Correction',
        ])->assertSessionHas('success');
        $this->assertSame(3000, $wallet->fresh()->cash_minor);

        // More than available -> service error flashed, nothing changes.
        $this->actingAs($admin)->post(route('admin.wallets.adjust', $wallet), [
            'amount' => '50', 'direction' => 'debit', 'bucket' => 'cash', 'reason' => 'Too much',
        ])->assertSessionHas('error');
        $this->assertSame(3000, $wallet->fresh()->cash_minor);

        // Reason required, max 3 decimals for OMR.
        $this->actingAs($admin)->post(route('admin.wallets.adjust', $wallet), [
            'amount' => '1.2345', 'direction' => 'credit', 'bucket' => 'cash', 'reason' => '',
        ])->assertSessionHasErrors(['amount', 'reason']);
    }

    public function test_freeze_and_unfreeze(): void
    {
        $admin = $this->tenantUser();
        $wallet = $this->walletWithCash(User::factory()->create(), 0);

        $this->actingAs($admin)->post(route('admin.wallets.status', $wallet), ['status' => 'frozen', 'reason' => 'Fraud check'])
            ->assertRedirect(route('admin.wallets.show', $wallet));
        $this->assertSame(Wallet::STATUS_FROZEN, $wallet->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.status', 'subject_id' => $wallet->id]);

        $this->actingAs($admin)->get(route('admin.wallets.show', $wallet))->assertOk()->assertSee('إلغاء التجميد');

        $this->actingAs($admin)->post(route('admin.wallets.status', $wallet), ['status' => 'active']);
        $this->assertSame(Wallet::STATUS_ACTIVE, $wallet->fresh()->status);

        $this->actingAs($admin)->post(route('admin.wallets.status', $wallet), ['status' => 'bogus'])
            ->assertSessionHasErrors('status');
    }

    public function test_search_and_open_wallet_of_user_without_one(): void
    {
        $admin = $this->tenantUser();
        $user = User::factory()->create(['name' => 'Salim Customer', 'phone_number' => '96891112222']);

        $this->actingAs($admin)->get(route('admin.wallets.index', ['q' => '9689111']))
            ->assertOk()->assertSee('Salim Customer')->assertSee('فتح المحفظة');

        // GET never creates a wallet: legacy ?user_id= → "no wallet yet" page.
        $this->actingAs($admin)->get(route('admin.wallets.index', ['user_id' => $user->id]))
            ->assertRedirect(route('admin.wallets.user', $user));
        $this->actingAs($admin)->get(route('admin.wallets.user', $user))
            ->assertOk()->assertSee('لا توجد محفظة لهذا المستخدم بعد')->assertSee(route('admin.wallets.create', $user));
        $this->assertFalse(Wallet::where('user_id', $user->id)->exists());

        // POST creates it (audited); a second POST is a no-op redirect.
        $response = $this->actingAs($admin)->post(route('admin.wallets.create', $user));
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('admin.wallets.show', $wallet));
        $this->assertSame($this->tenant->id, $wallet->tenant_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.created', 'subject_id' => $wallet->id]);
        $this->actingAs($admin)->post(route('admin.wallets.create', $user))->assertRedirect(route('admin.wallets.show', $wallet));
        $this->assertSame(1, Wallet::where('user_id', $user->id)->count());

        // Once it exists the GET goes straight to it.
        $this->actingAs($admin)->get(route('admin.wallets.user', $user))->assertRedirect(route('admin.wallets.show', $wallet));
    }

    public function test_refund_vendor_charge(): void
    {
        $admin = $this->tenantUser();
        $wallet = $this->walletWithCash(User::factory()->create(), 5000);
        $vendor = Vendor::create(['name' => ['en' => 'Cafe', 'ar' => 'مقهى'], 'is_active' => true]);
        $charge = app(WalletService::class)->debit($wallet, 1200, 'vendor_payment', ['vendor_id' => $vendor->id, 'idempotency_key' => 'charge-1']);

        $this->actingAs($admin)->post(route('admin.wallets.refund-charge', $charge), ['reason' => 'Wrong order'])
            ->assertRedirect(route('admin.wallets.show', $wallet))->assertSessionHas('success');

        $this->assertSame(5000, $wallet->fresh()->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'refund', 'reference_id' => $charge->id, 'direction' => 'credit']);
        $this->actingAs($admin)->get(route('admin.wallets.show', $wallet))->assertOk()->assertSee('مسترد');

        // A non vendor charge cannot be refunded here.
        $topup = WalletTransaction::where('wallet_id', $wallet->id)->where('type', 'topup')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.wallets.refund-charge', $topup))->assertSessionHas('error');
    }
}
