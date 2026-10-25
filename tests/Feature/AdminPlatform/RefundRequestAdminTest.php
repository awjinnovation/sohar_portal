<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\RefundRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\RefundRequestService;

class RefundRequestAdminTest extends AdminPlatformTestCase
{
    private function pendingRequest(int $amount = 4000): RefundRequest
    {
        $user = User::factory()->create();
        $this->walletWithCash($user, 10000);

        return app(RefundRequestService::class)->create($user, $this->tenant, [
            'amount_minor' => $amount, 'method' => 'bank_transfer', 'bank_iban' => 'OM810180000001299123456', 'bank_name' => 'Bank',
        ], 'req-'.$user->id);
    }

    public function test_approve_then_mark_paid(): void
    {
        $admin = $this->tenantUser();
        $request = $this->pendingRequest();
        $this->assertSame(6000, $request->wallet->fresh()->cash_minor);

        $this->actingAs($admin)->get(route('admin.refund-requests.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.refund-requests.show', $request))->assertOk()->assertSee('OM810180000001299123456');

        $this->actingAs($admin)->post(route('admin.refund-requests.approve', $request), ['admin_note' => 'ok'])
            ->assertRedirect(route('admin.refund-requests.show', $request))->assertSessionHas('success');
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->decided_by);

        $this->actingAs($admin)->post(route('admin.refund-requests.paid', $request), ['admin_note' => 'TRX-77'])
            ->assertSessionHas('success');
        $fresh = $request->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('TRX-77', $fresh->admin_note);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('completed', WalletTransaction::find($fresh->hold_transaction_id)->status);
        $this->assertSame(6000, $fresh->wallet->fresh()->cash_minor);

        // Paid requests cannot be rejected anymore.
        $this->actingAs($admin)->post(route('admin.refund-requests.reject', $request))->assertSessionHas('error');
        $this->assertSame('paid', $request->fresh()->status);
    }

    public function test_reject_credits_the_cash_back(): void
    {
        $admin = $this->tenantUser();
        $request = $this->pendingRequest(4000);

        $this->actingAs($admin)->post(route('admin.refund-requests.reject', $request), ['admin_note' => 'Not eligible'])
            ->assertSessionHas('success');

        $fresh = $request->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame(10000, $fresh->wallet->fresh()->cash_minor);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $fresh->reversal_transaction_id, 'type' => 'refund_reversal', 'amount_minor' => 4000]);
        $this->assertSame('reversed', WalletTransaction::find($fresh->hold_transaction_id)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund_request.rejected', 'subject_id' => $request->id]);
    }

    public function test_index_filters_by_status(): void
    {
        $admin = $this->tenantUser();
        $pending = $this->pendingRequest();
        $rejected = $this->pendingRequest();
        $rejected->forceFill(['status' => 'rejected'])->save();

        $this->actingAs($admin)->get(route('admin.refund-requests.index'))
            ->assertOk()->assertSee(route('admin.refund-requests.show', $pending), false)
            ->assertDontSee(route('admin.refund-requests.show', $rejected), false);

        $this->actingAs($admin)->get(route('admin.refund-requests.index', ['status' => 'rejected']))
            ->assertOk()->assertSee(route('admin.refund-requests.show', $rejected), false);
    }
}
