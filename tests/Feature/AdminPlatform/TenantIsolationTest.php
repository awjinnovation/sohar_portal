<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Deposit;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\RefundRequestService;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;

class TenantIsolationTest extends AdminPlatformTestCase
{
    public function test_admin_of_tenant_a_cannot_reach_rows_of_tenant_b(): void
    {
        $admin = $this->tenantUser();
        $b = $this->otherTenant();
        $customer = User::factory()->create();

        $rows = app(TenantContext::class)->run($b, function () use ($b, $customer) {
            $wallets = app(WalletService::class);
            $wallet = $wallets->walletFor($customer, $b);
            $wallets->credit($wallet, 10000, 'topup', ['bucket' => 'cash', 'idempotency_key' => 'b-topup']);
            $vendor = Vendor::create(['name' => ['en' => 'B shop', 'ar' => 'متجر ب'], 'is_active' => true]);
            $charge = $wallets->debit($wallet->fresh(), 500, 'vendor_payment', ['vendor_id' => $vendor->id, 'idempotency_key' => 'b-charge']);
            $deposit = app(DepositService::class)->hold($wallet->fresh(), 'cup', 300, 'b-cup');
            $refund = app(RefundRequestService::class)->create($customer, $b, ['amount_minor' => 2000, 'method' => 'original_payment'], 'b-refund');
            $payment = Payment::create([
                'user_id' => $customer->id, 'transaction_id' => 'TX-B-1', 'payment_type' => 'ticket', 'method' => 'gateway',
                'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed',
            ]);

            return compact('wallet', 'vendor', 'charge', 'deposit', 'refund', 'payment');
        });

        $this->assertSame($b->id, $rows['wallet']->tenant_id);

        $this->actingAs($admin);
        $this->get(route('admin.wallets.show', $rows['wallet']))->assertNotFound();
        $this->post(route('admin.wallets.adjust', $rows['wallet']), ['amount' => '1', 'direction' => 'credit', 'bucket' => 'cash', 'reason' => 'x'])->assertNotFound();
        $this->post(route('admin.wallets.status', $rows['wallet']), ['status' => 'frozen'])->assertNotFound();
        $this->post(route('admin.wallets.refund-charge', $rows['charge']))->assertNotFound();
        $this->get(route('admin.vendors.show', $rows['vendor']))->assertNotFound();
        $this->get(route('admin.vendors.edit', $rows['vendor']))->assertNotFound();
        $this->delete(route('admin.vendors.destroy', $rows['vendor']))->assertNotFound();
        $this->post(route('admin.deposits.release', $rows['deposit']))->assertNotFound();
        $this->get(route('admin.refund-requests.show', $rows['refund']))->assertNotFound();
        $this->post(route('admin.refund-requests.reject', $rows['refund']))->assertNotFound();
        $this->get(route('admin.payments.show', $rows['payment']))->assertNotFound();
        $this->post(route('admin.payments.refund', $rows['payment']))->assertNotFound();

        // Lists of tenant A do not leak tenant B rows.
        $this->get(route('admin.vendors.index'))->assertOk()->assertDontSee('متجر ب');
        $this->get(route('admin.payments.index'))->assertOk()->assertDontSee('TX-B-1');
        $this->get(route('admin.wallets.index'))->assertOk()->assertDontSee(route('admin.wallets.show', $rows['wallet']), false);

        // Nothing changed in tenant B.
        $this->assertSame('active', $rows['wallet']->fresh()->status);
        $this->assertSame('held', Deposit::withoutTenantScope()->find($rows['deposit']->id)->status);
        $this->assertDatabaseHas('vendors', ['id' => $rows['vendor']->id]);
    }
}
