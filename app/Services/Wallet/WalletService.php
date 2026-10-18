<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPromoLot;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code path allowed to move wallet money.
 *
 * Every mutation: DB transaction + row lock on the wallet, append-only
 * ledger row with the resulting balances, optional idempotency key (unique
 * per wallet: replaying a key returns the original transaction).
 *
 * Buckets (contract §6a): `cash` (top-ups, refundable) and `promo`
 * (points redemption, bonuses, goodwill). Spending consumes promo first.
 * `held` reserves funds for deposits: available = balance − held.
 *
 * Options accepted by the public methods:
 *  - idempotency_key  string|null
 *  - reference        Model|null   (morph)
 *  - vendor_id        int|null
 *  - created_by       int|null     (staff/admin actor)
 *  - title_key / description_key   translation keys (platform.tx.*)
 *  - meta             array        (merged; `bucket` is set automatically)
 *  - status           string       (default completed)
 *  - bucket           'cash'|'promo' for credits (default cash);
 *                     'cash' for debits forces cash-only (refund payouts)
 *  - allow_frozen     bool         (releases / reversals / admin adjustments)
 *  - promo_expires_at CarbonInterface|null  expiry of a promo credit
 *                     (default: now + wallet.promo_expiry_days, null = never)
 *  - enforce_max_balance bool      (top-ups)
 */
class WalletService
{
    public function __construct(private TenantContext $tenants) {}

    public function walletFor(User $user, ?Tenant $tenant = null): Wallet
    {
        $tenant ??= $this->tenants->get();

        $find = fn () => Wallet::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($wallet = $find()) {
            return $wallet;
        }

        try {
            $wallet = new Wallet(['user_id' => $user->id, 'currency' => $tenant->currency()]);
            $wallet->tenant_id = $tenant->id;
            $wallet->save();

            return $wallet->fresh();
        } catch (UniqueConstraintViolationException) {
            return $find();
        }
    }

    public function credit(Wallet $wallet, int $amount, string $type, array $opts = []): WalletTransaction
    {
        $this->assertPositive($amount);
        $bucket = $opts['bucket'] ?? 'cash';
        if (! in_array($bucket, ['cash', 'promo'], true)) {
            throw new InvalidArgumentException("Unknown bucket [$bucket]");
        }

        return $this->apply($wallet, $type, 'credit', $amount, $opts, function (Wallet $w) use ($amount, $bucket, $opts) {
            $this->assertNotFrozen($w, $opts);

            if (! empty($opts['enforce_max_balance'])) {
                $max = (int) $this->tenantOf($w)->setting('wallet.max_balance_minor', 0);
                if ($max > 0 && $w->balance_minor + $amount > $max) {
                    throw ApiException::limitExceeded();
                }
            }

            return $bucket === 'cash' ? [$amount, 0, 0] : [0, $amount, 0];
        });
    }

    /**
     * Spend from available funds, promo first then cash.
     */
    public function debit(Wallet $wallet, int $amount, string $type, array $opts = []): WalletTransaction
    {
        $this->assertPositive($amount);

        return $this->apply($wallet, $type, 'debit', $amount, $opts, function (Wallet $w) use ($amount, $opts) {
            $this->assertNotFrozen($w, $opts);

            if (($opts['bucket'] ?? null) === 'cash') {
                if ($amount > $w->refundableMinor()) {
                    throw ApiException::insufficientBalance($w->refundableMinor());
                }

                return [-$amount, 0, 0];
            }

            if ($amount > $w->availableMinor()) {
                throw ApiException::insufficientBalance($w->availableMinor());
            }

            $promo = min(max(0, $w->promo_minor), $amount);

            return [-($amount - $promo), -$promo, 0];
        });
    }

    /** Reserve funds for a deposit (balance unchanged, available decreases). */
    public function hold(Wallet $wallet, int $amount, array $opts = []): WalletTransaction
    {
        $this->assertPositive($amount);

        return $this->apply($wallet, 'deposit_hold', 'debit', $amount, $opts, function (Wallet $w) use ($amount, $opts) {
            $this->assertNotFrozen($w, $opts);
            if ($amount > $w->availableMinor()) {
                throw ApiException::insufficientBalance($w->availableMinor());
            }

            return [0, 0, $amount];
        });
    }

    /** Return held deposit funds to available. Allowed on frozen wallets. */
    public function release(Wallet $wallet, int $amount, array $opts = []): WalletTransaction
    {
        $this->assertPositive($amount);
        $opts['allow_frozen'] = true;

        return $this->apply($wallet, 'deposit_release', 'credit', $amount, $opts, function (Wallet $w) use ($amount) {
            if ($amount > $w->held_minor) {
                throw new ApiException('INVALID_STATE', 'Release exceeds held amount', 409);
            }

            return [0, 0, -$amount];
        });
    }

    /** Keep held deposit funds: held decreases and the balance is debited. */
    public function forfeit(Wallet $wallet, int $amount, array $opts = []): WalletTransaction
    {
        $this->assertPositive($amount);
        $opts['allow_frozen'] = true;

        return $this->apply($wallet, 'deposit_forfeit', 'debit', $amount, $opts, function (Wallet $w) use ($amount) {
            if ($amount > $w->held_minor) {
                throw new ApiException('INVALID_STATE', 'Forfeit exceeds held amount', 409);
            }
            // The held funds leave the wallet; consume cash first because the
            // hold was guaranteed by real money, then promo for any shortfall.
            $cash = min($amount, max(0, $w->cash_minor));

            return [-$cash, -($amount - $cash), -$amount];
        });
    }

    /**
     * Credit back a previous debit, splitting into the same buckets it
     * consumed (used for ticket / vendor payment refunds).
     */
    public function refundDebit(WalletTransaction $original, string $type, array $opts = []): WalletTransaction
    {
        $wallet = Wallet::withoutTenantScope()->findOrFail($original->wallet_id);
        $cash = -min(0, $original->cash_delta_minor);
        $promo = -min(0, $original->promo_delta_minor);
        $amount = $cash + $promo;
        $this->assertPositive($amount);
        $opts['allow_frozen'] = true;
        $opts['meta'] = array_merge($opts['meta'] ?? [], ['original_transaction_id' => $original->id]);

        return $this->apply($wallet, $type, 'credit', $amount, $opts, fn () => [$cash, $promo, 0]);
    }

    /**
     * Debit the promo left in lots whose expires_at has passed (ledger type
     * `adjustment`, meta.reason = promo_expired). Capped at what is still
     * available (held deposits are never broken). Returns null if nothing
     * expired.
     */
    public function expirePromo(Wallet $wallet, ?CarbonInterface $now = null): ?WalletTransaction
    {
        $now ??= now();

        return DB::transaction(function () use ($wallet, $now) {
            $locked = Wallet::withoutTenantScope()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $lots = WalletPromoLot::withoutTenantScope()
                ->where('wallet_id', $locked->id)
                ->where('remaining_minor', '>', 0)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->get();
            $amount = min((int) $lots->sum('remaining_minor'), $locked->promo_minor, $locked->availableMinor());
            if ($amount <= 0) {
                return null;
            }

            return $this->apply($locked, 'adjustment', 'debit', $amount, [
                'allow_frozen' => true,
                'title_key' => 'platform.tx.promo_expired.title',
                'description_key' => 'platform.tx.promo_expired.description',
                'meta' => ['reason' => 'promo_expired', 'expired_lot_ids' => $lots->pluck('id')->all()],
            ], fn () => [0, -$amount, 0]);
        });
    }

    public function setStatus(Wallet $wallet, string $status): Wallet
    {
        if (! in_array($status, [Wallet::STATUS_ACTIVE, Wallet::STATUS_FROZEN], true)) {
            throw new InvalidArgumentException("Unknown wallet status [$status]");
        }
        $wallet->status = $status;
        $wallet->save();

        return $wallet;
    }

    /**
     * @param  callable(Wallet): array{0:int,1:int,2:int}  $deltas  returns [cash, promo, held] deltas
     */
    protected function apply(Wallet $wallet, string $type, string $direction, int $amount, array $opts, callable $deltas): WalletTransaction
    {
        $key = $opts['idempotency_key'] ?? null;

        try {
            $tx = DB::transaction(function () use ($wallet, $type, $direction, $amount, $opts, $deltas, $key) {
                /** @var Wallet $locked */
                $locked = Wallet::withoutTenantScope()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

                if ($key !== null && ($existing = $this->findByKey($locked->id, $key))) {
                    return $this->assertSameOperation($existing, $type, $amount);
                }

                [$cashDelta, $promoDelta, $heldDelta] = $deltas($locked);

                $locked->cash_minor += $cashDelta;
                $locked->promo_minor += $promoDelta;
                $locked->held_minor += $heldDelta;
                $locked->balance_minor = $locked->cash_minor + $locked->promo_minor;

                if ($locked->held_minor < 0 || $locked->held_minor > $locked->balance_minor || $locked->promo_minor < 0) {
                    throw ApiException::insufficientBalance($wallet->availableMinor());
                }
                $locked->save();

                $promoExpiresAt = $promoDelta > 0 ? $this->promoExpiry($locked, $opts) : null;

                $bucket = match (true) {
                    $cashDelta !== 0 && $promoDelta !== 0 => 'mixed',
                    $promoDelta !== 0 => 'promo',
                    default => 'cash',
                };

                /** @var Model|null $reference */
                $reference = $opts['reference'] ?? null;

                $tx = new WalletTransaction([
                    'wallet_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'type' => $type,
                    'direction' => $direction,
                    'amount_minor' => $amount,
                    'cash_delta_minor' => $cashDelta,
                    'promo_delta_minor' => $promoDelta,
                    'held_delta_minor' => $heldDelta,
                    'balance_after_minor' => $locked->balance_minor,
                    'cash_after_minor' => $locked->cash_minor,
                    'promo_after_minor' => $locked->promo_minor,
                    'held_after_minor' => $locked->held_minor,
                    'status' => $opts['status'] ?? 'completed',
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'vendor_id' => $opts['vendor_id'] ?? null,
                    'created_by' => $opts['created_by'] ?? null,
                    'idempotency_key' => $key,
                    'title_key' => $opts['title_key'] ?? "platform.tx.$type.title",
                    'description_key' => $opts['description_key'] ?? "platform.tx.$type.description",
                    'meta' => array_merge($opts['meta'] ?? [], ['bucket' => $bucket], $promoExpiresAt ? ['expires_at' => \App\Support\TenantTime::isoForTenantId($promoExpiresAt, $locked->tenant_id)] : []),
                ]);
                $tx->tenant_id = $locked->tenant_id;
                $tx->save();

                if ($promoDelta > 0) {
                    $lot = new WalletPromoLot([
                        'wallet_id' => $locked->id,
                        'wallet_transaction_id' => $tx->id,
                        'amount_minor' => $promoDelta,
                        'remaining_minor' => $promoDelta,
                        'expires_at' => $promoExpiresAt,
                    ]);
                    $lot->tenant_id = $locked->tenant_id;
                    $lot->save();
                } elseif ($promoDelta < 0) {
                    $this->consumePromoLots($locked->id, -$promoDelta);
                }

                return $tx;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent request with the same idempotency key won the race.
            if ($key !== null && ($existing = $this->findByKey($wallet->id, $key))) {
                $tx = $this->assertSameOperation($existing, $type, $amount);
            } else {
                throw $e;
            }
        }

        $wallet->setRawAttributes(Wallet::withoutTenantScope()->find($wallet->id)->getAttributes(), true);

        return $tx;
    }

    /** Expiry of a new promo credit: explicit option, else the tenant setting. */
    protected function promoExpiry(Wallet $wallet, array $opts): ?CarbonInterface
    {
        if (array_key_exists('promo_expires_at', $opts)) {
            return $opts['promo_expires_at'];
        }
        $days = $this->tenantOf($wallet)->setting('wallet.promo_expiry_days');

        return is_numeric($days) && (int) $days > 0 ? now()->addDays((int) $days) : null;
    }

    /** FIFO: soonest-expiring lots first, then non-expiring ones (oldest first). */
    protected function consumePromoLots(int $walletId, int $amount): void
    {
        $lots = WalletPromoLot::withoutTenantScope()
            ->where('wallet_id', $walletId)
            ->where('remaining_minor', '>', 0)
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        foreach ($lots as $lot) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, $lot->remaining_minor);
            $lot->remaining_minor -= $take;
            $lot->save();
            $amount -= $take;
        }
        // Any rest came from promo credited before lots existed (never expires).
    }

    protected function findByKey(int $walletId, string $key): ?WalletTransaction
    {
        return WalletTransaction::withoutTenantScope()
            ->where('wallet_id', $walletId)
            ->where('idempotency_key', $key)
            ->first();
    }

    protected function assertSameOperation(WalletTransaction $existing, string $type, int $amount): WalletTransaction
    {
        if ($existing->type !== $type || $existing->amount_minor !== $amount) {
            throw ApiException::idempotencyConflict();
        }

        return $existing;
    }

    protected function assertNotFrozen(Wallet $wallet, array $opts): void
    {
        if ($wallet->isFrozen() && empty($opts['allow_frozen'])) {
            throw ApiException::walletFrozen();
        }
    }

    protected function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number of minor units');
        }
    }

    protected function tenantOf(Wallet $wallet): Tenant
    {
        $current = $this->tenants->get();

        return $current && $current->id === $wallet->tenant_id ? $current : Tenant::findOrFail($wallet->tenant_id);
    }
}
