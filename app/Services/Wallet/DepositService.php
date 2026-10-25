<?php

namespace App\Services\Wallet;

use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Refundable deposits (wristband, cup, locker...): a hold on wallet funds
 * that is later released (back to available) or forfeited.
 */
class DepositService
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @param  array{vendor_id?:int|null, event_id?:int|null, payment_id?:int|null, created_by?:int|null, label?:string|null}  $attrs
     */
    public function hold(Wallet $wallet, string $type, int $amount, string $key, array $attrs = []): Deposit
    {
        $find = fn () => Deposit::withoutTenantScope()->where('wallet_id', $wallet->id)->where('idempotency_key', $key)->first();
        if ($existing = $find()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($wallet, $type, $amount, $key, $attrs) {
                $deposit = new Deposit(array_merge($attrs, [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'type' => $type,
                    'amount_minor' => $amount,
                    'status' => 'held',
                    'idempotency_key' => $key,
                ]));
                $deposit->tenant_id = $wallet->tenant_id;
                $deposit->save();

                $this->wallets->hold($wallet, $amount, [
                    'idempotency_key' => "deposit:$key",
                    'reference' => $deposit,
                    'vendor_id' => $attrs['vendor_id'] ?? null,
                    'created_by' => $attrs['created_by'] ?? null,
                    'meta' => ['deposit_type' => $type, 'label' => $attrs['label'] ?? null],
                ]);

                return $deposit;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $find() ?? throw $e;
        }
    }

    /** Release (or forfeit) a held deposit. Idempotent: non-held deposits are returned unchanged. */
    public function release(Deposit $deposit, bool $forfeit = false, ?int $staffId = null): Deposit
    {
        return DB::transaction(function () use ($deposit, $forfeit, $staffId) {
            $locked = Deposit::withoutTenantScope()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'held') {
                return $locked;
            }

            $wallet = Wallet::withoutTenantScope()->findOrFail($locked->wallet_id);
            $opts = [
                'idempotency_key' => "deposit:{$locked->id}:".($forfeit ? 'forfeit' : 'release'),
                'reference' => $locked,
                'vendor_id' => $locked->vendor_id,
                'created_by' => $staffId,
                'meta' => ['deposit_type' => $locked->type, 'label' => $locked->label],
            ];
            $forfeit
                ? $this->wallets->forfeit($wallet, $locked->amount_minor, $opts)
                : $this->wallets->release($wallet, $locked->amount_minor, $opts);

            $locked->status = $forfeit ? 'forfeited' : 'released';
            $locked->released_by = $staffId;
            $locked->released_at = now();
            $locked->save();

            return $locked;
        });
    }
}
