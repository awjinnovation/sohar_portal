<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Admin-panel money operations: manual adjustments, payment refunds, vendor
 * charge refunds. Every operation is audited.
 */
class WalletAdminService
{
    public function __construct(
        private WalletService $wallets,
        private PointsService $points,
        private DepositService $deposits,
    ) {}

    /**
     * Signed manual adjustment. Credits default to the promo bucket
     * (goodwill, not refundable) unless $bucket = 'cash'.
     */
    public function adjust(Wallet $wallet, int $signedAmount, string $bucket, string $reason, User $admin): WalletTransaction
    {
        if ($signedAmount === 0 || trim($reason) === '') {
            throw new ApiException('VALIDATION_ERROR', 'Amount and reason are required', 422);
        }
        $opts = [
            'bucket' => $bucket,
            'allow_frozen' => true,
            'created_by' => $admin->id,
            'idempotency_key' => 'admin:'.$admin->id.':'.sha1($wallet->id.'|'.$signedAmount.'|'.$reason.'|'.now()->format('YmdHi')),
            'meta' => ['reason' => $reason, 'admin_id' => $admin->id],
        ];

        if ($signedAmount > 0) {
            $tx = $this->wallets->credit($wallet, $signedAmount, 'adjustment', $opts);
        } else {
            if ($bucket !== 'cash') {
                unset($opts['bucket']); // promo first, then cash
            }
            $tx = $this->wallets->debit($wallet, -$signedAmount, 'adjustment', $opts);
        }

        AuditLog::record('wallet.adjustment', $wallet, [
            'amount_minor' => $signedAmount, 'bucket' => $bucket, 'reason' => $reason, 'transaction_id' => $tx->id,
        ]);

        return $tx;
    }

    /**
     * Refund a completed ticket payment: tickets cancelled, wallet-paid
     * amounts credited back in the buckets they came from, deposits
     * released, points spent restored and points earned reversed (§6a).
     * Card payments are marked refunded; the money goes back through the
     * bank's merchant portal.
     */
    public function refundPayment(Payment $payment, User $admin, ?string $reason = null): Payment
    {
        $tenant = Tenant::findOrFail($payment->tenant_id);

        return DB::transaction(function () use ($payment, $admin, $reason, $tenant) {
            $locked = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->refunded_at || $locked->status === 'refunded') {
                return $locked;
            }
            if ($locked->status !== 'completed') {
                throw new ApiException('INVALID_STATE', 'Only completed payments can be refunded', 409);
            }
            if ($locked->payment_type !== Payment::TYPE_TICKET) {
                throw new ApiException('INVALID_STATE', 'Top-ups are refunded through wallet refund requests', 409);
            }

            if ($locked->method === Payment::METHOD_WALLET) {
                $purchase = WalletTransaction::withoutTenantScope()
                    ->where('type', 'ticket_purchase')
                    ->where('reference_type', $locked->getMorphClass())
                    ->where('reference_id', $locked->id)
                    ->first();
                if ($purchase) {
                    $this->wallets->refundDebit($purchase, 'refund', [
                        'idempotency_key' => "refund:payment:{$locked->id}",
                        'reference' => $locked,
                        'created_by' => $admin->id,
                        'title_key' => 'platform.tx.ticket_refund.title',
                        'description_key' => 'platform.tx.ticket_refund.description',
                        'meta' => array_merge($purchase->meta ?? [], ['kind' => 'purchase_refund', 'reason' => $reason]),
                    ]);
                }
            }

            Deposit::withoutTenantScope()->where('payment_id', $locked->id)->where('status', 'held')->get()
                ->each(fn (Deposit $d) => $this->deposits->release($d, false, $admin->id));

            Ticket::withoutTenantScope()->where('transaction_id', $locked->transaction_id)
                ->where('status', 'active')->update(['status' => 'cancelled']);

            $meta = $locked->metadata ?? [];
            if (($meta['points_used'] ?? 0) > 0 && $locked->user) {
                $this->points->restore($locked->user, $tenant, (int) $meta['points_used'], $locked, "pay:{$locked->id}:points_refund");
            }
            $this->points->reverseEarned($locked, "reverse:payment:{$locked->id}");

            $locked->status = 'refunded';
            $locked->refunded_at = now();
            $locked->save();

            AuditLog::record('payment.refund', $locked, ['reason' => $reason, 'method' => $locked->method]);

            return $locked;
        });
    }

    /** Refund a vendor charge back to the customer's wallet (and reverse its points). */
    public function refundVendorCharge(WalletTransaction $charge, User $admin, ?string $reason = null): WalletTransaction
    {
        if ($charge->type !== 'vendor_payment' || $charge->direction !== 'debit') {
            throw new ApiException('INVALID_STATE', 'Only vendor payments can be refunded here', 409);
        }

        return DB::transaction(function () use ($charge, $admin, $reason) {
            $tx = $this->wallets->refundDebit($charge, 'refund', [
                'idempotency_key' => "refund:tx:{$charge->id}",
                'reference' => $charge,
                'vendor_id' => $charge->vendor_id,
                'created_by' => $admin->id,
                'meta' => ['kind' => 'vendor_refund', 'reason' => $reason],
            ]);
            $this->points->reverseEarned($charge, "reverse:tx:{$charge->id}");
            AuditLog::record('wallet.vendor_refund', $charge, ['reason' => $reason, 'refund_transaction_id' => $tx->id]);

            return $tx;
        });
    }
}
