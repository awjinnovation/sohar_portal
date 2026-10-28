<?php

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\Wallet\WalletService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wallet top-ups through the payment gateway (contract §6). The wallet is
 * credited by PaymentFulfillmentService when the gateway confirms, exactly
 * once (idempotency key topup:{id}).
 */
class TopupService
{
    public function __construct(private WalletService $wallets, private PaymentGatewayManager $gateways) {}

    public function initialize(User $user, Tenant $tenant, int $amountMinor, string $key): WalletTopup
    {
        $wallet = $this->wallets->walletFor($user, $tenant);

        $find = fn () => WalletTopup::withoutTenantScope()->where('wallet_id', $wallet->id)->where('idempotency_key', $key)->first();
        if ($existing = $find()) {
            return $existing;
        }

        if ($wallet->isFrozen()) {
            throw ApiException::walletFrozen();
        }

        $min = (int) $tenant->setting('wallet.min_topup_minor', 0);
        $max = (int) $tenant->setting('wallet.max_topup_minor', 0);
        $maxBalance = (int) $tenant->setting('wallet.max_balance_minor', 0);
        if ($amountMinor < max(1, $min) || ($max > 0 && $amountMinor > $max)) {
            throw ApiException::limitExceeded(sprintf('Top-up must be between %s and %s %s',
                $tenant->formatMoney(max(1, $min)), $tenant->formatMoney($max), $tenant->currency()));
        }
        if ($maxBalance > 0 && $wallet->balance_minor + $amountMinor > $maxBalance) {
            throw ApiException::limitExceeded(sprintf('Wallet balance cannot exceed %s %s',
                $tenant->formatMoney($maxBalance), $tenant->currency()));
        }

        try {
            return DB::transaction(function () use ($user, $tenant, $wallet, $amountMinor, $key) {
                $topup = new WalletTopup([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'amount_minor' => $amountMinor,
                    'status' => 'pending',
                    'idempotency_key' => $key,
                ]);
                $topup->tenant_id = $tenant->id;
                $topup->save();

                $payment = new Payment([
                    'user_id' => $user->id,
                    'transaction_id' => 'TOP'.strtoupper(Str::random(12)),
                    'payment_type' => Payment::TYPE_TOPUP,
                    'method' => Payment::METHOD_GATEWAY,
                    'gateway' => $this->gateways->driver($tenant),
                    'payable_id' => $topup->id,
                    'payable_type' => WalletTopup::class,
                    'amount' => Money::format($amountMinor, $tenant->currencyDecimals()),
                    'amount_minor' => $amountMinor,
                    'currency' => $tenant->currency(),
                    'status' => 'pending',
                    'metadata' => [
                        'topup_id' => $topup->id,
                        'user_phone' => $user->phone_number,
                        'currency_decimals' => $tenant->currencyDecimals(),
                        'quantity' => 1,
                    ],
                ]);
                $payment->tenant_id = $tenant->id;
                $payment->save();

                $topup->payment_id = $payment->id;
                $topup->save();

                app(PaymentRecorder::class)->record($payment, 'created', 'api', [
                    'type' => $payment->payment_type, 'gateway' => $payment->gateway, 'amount_minor' => $amountMinor,
                ], null, 'pending');

                return $topup;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $find() ?? throw $e;
        }
    }
}
