<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\PointsAccount;
use App\Models\PointsTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points per (user, tenant), contract §7.
 *
 * Earn lots carry `remaining` + `expires_at`; consumption (redeem, spend at
 * checkout, reversal) is FIFO by expiry so the oldest points go first and
 * the expiry job only ever removes what is still unspent.
 */
class PointsService
{
    public function __construct(private TenantContext $tenants, private WalletService $wallets) {}

    public function accountFor(User $user, ?Tenant $tenant = null): PointsAccount
    {
        $tenant ??= $this->tenants->get();
        $find = fn () => PointsAccount::withoutTenantScope()
            ->where('tenant_id', $tenant->id)->where('user_id', $user->id)->first();

        if ($account = $find()) {
            return $account;
        }

        try {
            $account = new PointsAccount(['user_id' => $user->id]);
            $account->tenant_id = $tenant->id;
            $account->save();

            return $account->fresh();
        } catch (UniqueConstraintViolationException) {
            return $find();
        }
    }

    /** Points for a spend: floor(amount_major × earn_per_currency_unit × tier.multiplier). */
    public function pointsForSpend(Tenant $tenant, int $amountMinor, ?PointsAccount $account = null): int
    {
        $earnPerUnit = (float) $tenant->setting('points.earn_per_currency_unit', 0);
        $multiplier = (float) ($account ? $this->tierInfo($tenant, $account)['tier']['multiplier'] ?? 1 : 1);
        if ($earnPerUnit <= 0 || $amountMinor <= 0) {
            return 0;
        }
        $divisor = 10 ** $tenant->currencyDecimals();

        // Work in integers (×10000) to avoid float drift on the multiplier.
        $factor = (int) round($earnPerUnit * $multiplier * 10000);

        return intdiv($amountMinor * $factor, $divisor * 10000);
    }

    /**
     * Earn on a completed spend (ticket_purchase / vendor_payment).
     */
    public function earnForSpend(User $user, Tenant $tenant, int $amountMinor, Model $reference, string $key): ?PointsTransaction
    {
        if (! $tenant->moduleEnabled('points')) {
            return null;
        }
        $account = $this->accountFor($user, $tenant);
        $points = $this->pointsForSpend($tenant, $amountMinor, $account);

        return $points > 0 ? $this->award($account, $tenant, $points, 'platform.points.earn', $reference, $key) : null;
    }

    public function award(PointsAccount $account, Tenant $tenant, int $points, string $titleKey, ?Model $reference, string $key, array $meta = []): PointsTransaction
    {
        $expiryDays = (int) $tenant->setting('points.expiry_days', 0);

        return $this->apply($account, $key, function (PointsAccount $a) use ($points, $titleKey, $reference, $expiryDays, $meta) {
            $a->balance += $points;
            $a->lifetime_earned += $points;

            return [
                'type' => 'earn',
                'points' => $points,
                'remaining' => $points,
                'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
                'title_key' => $titleKey,
                'reference' => $reference,
                'meta' => $meta,
            ];
        });
    }

    /**
     * Redeem points into promo wallet credit (POST /points/redeem).
     *
     * @return array{points: PointsTransaction, wallet: \App\Models\WalletTransaction}
     */
    public function redeemToWallet(User $user, Tenant $tenant, int $points, string $key): array
    {
        $min = (int) $tenant->setting('points.min_redeem_points', 0);
        if ($points < max(1, $min)) {
            throw ApiException::limitExceeded(__('platform.errors.limit_exceeded').' (min '.$min.')');
        }
        $valuePerPoint = (int) $tenant->setting('points.redeem_value_minor_per_point', 0);
        $credit = $points * $valuePerPoint;
        if ($credit <= 0) {
            throw ApiException::moduleDisabled('points');
        }

        $account = $this->accountFor($user, $tenant);
        $wallet = $this->wallets->walletFor($user, $tenant);

        return DB::transaction(function () use ($account, $wallet, $points, $credit, $key) {
            $pointsTx = $this->consume($account, $points, 'redeem', 'platform.points.redeem', null, "redeem:$key");
            $walletTx = $this->wallets->credit($wallet, $credit, 'points_redemption', [
                'bucket' => 'promo',
                'idempotency_key' => "points:$key",
                'reference' => $pointsTx,
                'meta' => ['points' => $points],
            ]);

            return ['points' => $pointsTx, 'wallet' => $walletTx];
        });
    }

    /** Spend points as a checkout discount (no wallet credit). */
    public function spend(User $user, Tenant $tenant, int $points, ?Model $reference, string $key): PointsTransaction
    {
        return $this->consume($this->accountFor($user, $tenant), $points, 'redeem', 'platform.points.redeem_checkout', $reference, $key);
    }

    /** Give back points (e.g. failed checkout, refunded purchase). */
    public function restore(User $user, Tenant $tenant, int $points, ?Model $reference, string $key): ?PointsTransaction
    {
        if ($points <= 0) {
            return null;
        }
        $account = $this->accountFor($user, $tenant);
        $expiryDays = (int) $tenant->setting('points.expiry_days', 0);

        return $this->apply($account, $key, function (PointsAccount $a) use ($points, $reference, $expiryDays) {
            $a->balance += $points;

            return [
                'type' => 'adjust',
                'points' => $points,
                'remaining' => $points,
                'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
                'title_key' => 'platform.points.adjust',
                'reference' => $reference,
            ];
        });
    }

    /**
     * Reverse the points earned from a reference (refunded payment / vendor
     * charge). Clamped to the current balance — points already spent are
     * not clawed back below zero.
     */
    public function reverseEarned(Model $reference, string $key): ?PointsTransaction
    {
        $earned = PointsTransaction::withoutTenantScope()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('type', 'earn')
            ->get();

        if ($earned->isEmpty()) {
            return null;
        }

        $account = PointsAccount::withoutTenantScope()->findOrFail($earned->first()->points_account_id);
        $points = (int) $earned->sum('points');

        return $this->consume($account, $points, 'adjust', 'platform.points.reversal', $reference, $key, clamp: true);
    }

    /** Expire unspent lots whose expires_at passed. Returns points expired. */
    public function expire(?Tenant $tenant = null): int
    {
        $total = 0;
        $query = PointsTransaction::withoutTenantScope()
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        foreach ($query->pluck('points_account_id')->unique() as $accountId) {
            $account = PointsAccount::withoutTenantScope()->find($accountId);
            DB::transaction(function () use ($account, &$total) {
                $locked = PointsAccount::withoutTenantScope()->whereKey($account->id)->lockForUpdate()->first();
                $lots = PointsTransaction::withoutTenantScope()
                    ->where('points_account_id', $locked->id)
                    ->where('remaining', '>', 0)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    $pts = min($lot->remaining, $locked->balance);
                    $lot->remaining = 0;
                    $lot->save();
                    if ($pts <= 0) {
                        continue;
                    }
                    $locked->balance -= $pts;
                    $total += $pts;

                    $tx = new PointsTransaction([
                        'points_account_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'type' => 'expire',
                        'points' => -$pts,
                        'balance_after' => $locked->balance,
                        'remaining' => 0,
                        'idempotency_key' => 'expire:'.$lot->id,
                        'title_key' => 'platform.points.expire',
                        'meta' => ['lot_id' => $lot->id],
                    ]);
                    $tx->tenant_id = $locked->tenant_id;
                    $tx->save();
                }
                $locked->save();
            });
        }

        return $total;
    }

    /**
     * Manual admin adjustment (type `adjust`) with a reason; audited.
     * Positive: a new lot (tenant expiry applies), lifetime_earned / tier
     * unchanged. Negative: consumes lots FIFO; fails with
     * INSUFFICIENT_POINTS when the balance is too low.
     */
    public function adminAdjust(PointsAccount $account, int $points, string $reason, User $admin): PointsTransaction
    {
        $reason = trim($reason);
        if ($points === 0 || $reason === '') {
            throw new ApiException('VALIDATION_ERROR', 'Points and reason are required', 422);
        }
        $tenant = $this->tenants->get()?->id === $account->tenant_id ? $this->tenants->get() : Tenant::findOrFail($account->tenant_id);
        $key = 'admin:'.$admin->id.':'.sha1($account->id.'|'.$points.'|'.$reason.'|'.now()->format('YmdHi'));
        $meta = ['reason' => $reason, 'admin_id' => $admin->id];

        if ($points > 0) {
            $expiryDays = (int) $tenant->setting('points.expiry_days', 0);
            $tx = $this->apply($account, $key, fn (PointsAccount $a) => tap([
                'type' => 'adjust',
                'points' => $points,
                'remaining' => $points,
                'expires_at' => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
                'title_key' => 'platform.points.adjust',
                'meta' => $meta,
            ], function () use ($a, $points) {
                $a->balance += $points;
            }));
        } else {
            $tx = $this->consume($account, -$points, 'adjust', 'platform.points.adjust', null, $key);
            if (! $tx->meta) {
                $tx->meta = $meta;
                $tx->save();
            }
        }

        AuditLog::record('points.adjustment', $account, [
            'points' => $points, 'reason' => $reason, 'balance_after' => $tx->balance_after, 'points_transaction_id' => $tx->id,
        ]);

        return $tx;
    }

    /**
     * @return array{tier: array|null, next_tier: array|null, points_to_next_tier: int}
     */
    public function tierInfo(Tenant $tenant, PointsAccount $account): array
    {
        $tiers = collect($tenant->setting('points.tiers', []))->sortBy('min_points')->values();
        $basis = $account->lifetime_earned;

        $current = $tiers->filter(fn ($t) => $basis >= (int) ($t['min_points'] ?? 0))->last() ?? $tiers->first();
        $next = $tiers->first(fn ($t) => (int) ($t['min_points'] ?? 0) > $basis);

        return [
            'tier' => $current,
            'next_tier' => $next,
            'points_to_next_tier' => $next ? max(0, (int) $next['min_points'] - $basis) : 0,
        ];
    }

    /** Lots expiring in the next 30 days (for GET /points). */
    public function expiringSoon(PointsAccount $account, int $days = 30): array
    {
        return PointsTransaction::withoutTenantScope()
            ->where('points_account_id', $account->id)
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->get()
            ->map(fn ($lot) => ['points' => $lot->remaining, 'expires_at' => \App\Support\TenantTime::iso($lot->expires_at)])
            ->all();
    }

    protected function consume(PointsAccount $account, int $points, string $type, string $titleKey, ?Model $reference, string $key, bool $clamp = false): PointsTransaction
    {
        if ($points <= 0) {
            throw ApiException::insufficientPoints($account->balance);
        }

        return $this->apply($account, $key, function (PointsAccount $a) use ($points, $type, $titleKey, $reference, $clamp) {
            if ($points > $a->balance) {
                if (! $clamp) {
                    throw ApiException::insufficientPoints($a->balance);
                }
                $points = $a->balance;
            }

            $left = $points;
            $lots = PointsTransaction::withoutTenantScope()
                ->where('points_account_id', $a->id)
                ->where('remaining', '>', 0)
                ->orderByRaw('expires_at is null')   // dated lots first
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($lots as $lot) {
                if ($left <= 0) {
                    break;
                }
                $take = min($lot->remaining, $left);
                $lot->remaining -= $take;
                $lot->save();
                $left -= $take;
            }
            $a->balance -= $points;

            return [
                'type' => $type,
                'points' => -$points,
                'remaining' => 0,
                'title_key' => $titleKey,
                'reference' => $reference,
            ];
        });
    }

    /**
     * @param  callable(PointsAccount): array  $mutate  mutates the locked account and returns tx attributes
     */
    protected function apply(PointsAccount $account, string $key, callable $mutate): PointsTransaction
    {
        $find = fn () => PointsTransaction::withoutTenantScope()
            ->where('points_account_id', $account->id)
            ->where('idempotency_key', $key)
            ->first();

        try {
            $tx = DB::transaction(function () use ($account, $key, $mutate, $find) {
                $locked = PointsAccount::withoutTenantScope()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                if ($existing = $find()) {
                    return $existing;
                }

                $attrs = $mutate($locked);
                $locked->save();

                $reference = $attrs['reference'] ?? null;
                unset($attrs['reference']);

                $tx = new PointsTransaction(array_merge($attrs, [
                    'points_account_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'balance_after' => $locked->balance,
                    'idempotency_key' => $key,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                ]));
                $tx->tenant_id = $locked->tenant_id;
                $tx->save();

                return $tx;
            });
        } catch (UniqueConstraintViolationException $e) {
            $tx = $find() ?? throw $e;
        }

        $account->setRawAttributes(PointsAccount::withoutTenantScope()->find($account->id)->getAttributes(), true);

        return $tx;
    }
}
