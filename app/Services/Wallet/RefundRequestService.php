<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Cash-out of refundable wallet funds (contract §6, §6a).
 *
 * Creating a request immediately moves the amount out of the wallet into a
 * *pending* `refund` transaction (cash bucket only — promo is never
 * refundable, and held deposits are excluded). The admin then approves
 * (-> mark paid) or rejects (-> `refund_reversal` credits the cash back).
 */
class RefundRequestService
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @param  array{amount_minor?:int|null, method:string, reason?:string|null, bank_iban?:string|null, bank_name?:string|null}  $input
     */
    public function create(User $user, Tenant $tenant, array $input, string $key): RefundRequest
    {
        if (! $tenant->moduleEnabled('refunds') || ! $tenant->setting('wallet.refunds.enabled', false)) {
            throw ApiException::refundNotAllowed();
        }

        $wallet = $this->wallets->walletFor($user, $tenant);
        $find = fn () => RefundRequest::withoutTenantScope()->where('wallet_id', $wallet->id)->where('idempotency_key', $key)->first();
        if ($existing = $find()) {
            return $existing;
        }

        $methods = (array) $tenant->setting('wallet.refunds.methods', []);
        if (! in_array($input['method'], $methods, true)) {
            throw ApiException::refundNotAllowed('Refund method not available');
        }
        if ($input['method'] === 'bank_transfer' && empty($input['bank_iban'])) {
            throw new ApiException('VALIDATION_ERROR', 'bank_iban is required for bank transfers', 422, ['errors' => ['bank_iban' => ['required']]]);
        }

        $windowDays = (int) $tenant->setting('wallet.refunds.window_days', 0);
        if ($windowDays > 0) {
            $lastTopup = WalletTransaction::withoutTenantScope()->where('wallet_id', $wallet->id)
                ->where('type', 'topup')->latest('id')->value('created_at');
            if ($lastTopup && now()->diffInDays($lastTopup, true) > $windowDays) {
                throw ApiException::refundNotAllowed("Refunds must be requested within $windowDays days of the last top-up");
            }
        }

        $refundable = $wallet->refundableMinor();
        $amount = (int) ($input['amount_minor'] ?? $refundable);
        $min = (int) $tenant->setting('wallet.refunds.min_minor', 0);
        $fee = (int) $tenant->setting('wallet.refunds.fee_minor', 0);

        if ($amount <= 0 || $amount > $refundable) {
            throw ApiException::refundNotAllowed(sprintf('Refundable amount is %s %s', $tenant->formatMoney($refundable), $tenant->currency()));
        }
        if ($amount < $min || $amount <= $fee) {
            throw ApiException::refundNotAllowed(sprintf('Minimum refund is %s %s', $tenant->formatMoney(max($min, $fee + 1)), $tenant->currency()));
        }

        try {
            return DB::transaction(function () use ($user, $tenant, $wallet, $input, $amount, $fee, $key) {
                $request = new RefundRequest([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'amount_minor' => $amount,
                    'fee_minor' => $fee,
                    'method' => $input['method'],
                    'status' => 'pending',
                    'reason' => $input['reason'] ?? null,
                    'bank_iban' => $input['bank_iban'] ?? null,
                    'bank_name' => $input['bank_name'] ?? null,
                    'idempotency_key' => $key,
                ]);
                $request->tenant_id = $tenant->id;
                $request->save();

                $tx = $this->wallets->debit($wallet, $amount, 'refund', [
                    'bucket' => 'cash',
                    'status' => 'pending',
                    'idempotency_key' => "refund_request:{$request->id}",
                    'reference' => $request,
                    'meta' => ['refund_request_id' => $request->id, 'fee_minor' => $fee, 'method' => $input['method']],
                ]);

                $request->hold_transaction_id = $tx->id;
                $request->save();

                return $request;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $find() ?? throw $e;
        }
    }

    public function approve(RefundRequest $request, User $admin, ?string $note = null): RefundRequest
    {
        return $this->transition($request, ['pending'], 'approved', $admin, $note);
    }

    public function markPaid(RefundRequest $request, User $admin, ?string $note = null): RefundRequest
    {
        return $this->transition($request, ['pending', 'approved'], 'paid', $admin, $note, function (RefundRequest $r) {
            $r->paid_at = now();
            WalletTransaction::withoutTenantScope()->whereKey($r->hold_transaction_id)->update(['status' => 'completed']);
        });
    }

    public function reject(RefundRequest $request, User $admin, ?string $note = null): RefundRequest
    {
        return $this->transition($request, ['pending', 'approved'], 'rejected', $admin, $note, function (RefundRequest $r) {
            $wallet = Wallet::withoutTenantScope()->findOrFail($r->wallet_id);
            $tx = $this->wallets->credit($wallet, $r->amount_minor, 'refund_reversal', [
                'bucket' => 'cash',
                'allow_frozen' => true,
                'idempotency_key' => "refund_request:{$r->id}:reversal",
                'reference' => $r,
                'meta' => ['refund_request_id' => $r->id],
            ]);
            $r->reversal_transaction_id = $tx->id;
            WalletTransaction::withoutTenantScope()->whereKey($r->hold_transaction_id)->update(['status' => 'reversed']);
        });
    }

    private function transition(RefundRequest $request, array $from, string $to, User $admin, ?string $note, ?callable $effect = null): RefundRequest
    {
        return DB::transaction(function () use ($request, $from, $to, $admin, $note, $effect) {
            $locked = RefundRequest::withoutTenantScope()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $to) {
                return $locked;
            }
            if (! in_array($locked->status, $from, true)) {
                throw new ApiException('INVALID_STATE', "Cannot change a {$locked->status} refund request to $to", 409);
            }

            if ($effect) {
                $effect($locked);
            }
            $locked->status = $to;
            $locked->decided_by = $admin->id;
            $locked->decided_at ??= now();
            if ($note !== null) {
                $locked->admin_note = $note;
            }
            $locked->save();

            AuditLog::record("refund_request.$to", $locked, ['note' => $note, 'amount_minor' => $locked->amount_minor]);

            return $locked;
        });
    }
}
