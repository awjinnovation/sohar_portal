<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\Deposit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-staff operations (contract §9): charge a customer's wallet by
 * scanning their pay QR, take / release deposits.
 *
 * Idempotency keys are namespaced per vendor and checked *before* the pay
 * token is burned, so a retried request (same key) returns the original
 * result instead of failing with PAY_TOKEN_EXPIRED.
 */
class MerchantService
{
    public function __construct(
        private WalletService $wallets,
        private PayTokenService $tokens,
        private PointsService $points,
        private DepositService $deposits,
    ) {}

    public function charge(User $staff, Tenant $tenant, Vendor $vendor, string $payToken, int $amount, ?string $description, string $key): WalletTransaction
    {
        $nsKey = "merchant:{$vendor->id}:$key";
        $existing = WalletTransaction::withoutTenantScope()
            ->where('tenant_id', $tenant->id)->where('vendor_id', $vendor->id)->where('idempotency_key', $nsKey)->first();
        if ($existing) {
            return $existing;
        }
        if ($amount <= 0) {
            throw ApiException::limitExceeded('Amount must be positive');
        }

        return DB::transaction(function () use ($staff, $tenant, $vendor, $payToken, $amount, $description, $nsKey) {
            $wallet = $this->tokens->consume($payToken, $tenant, $staff);

            $tx = $this->wallets->debit($wallet, $amount, 'vendor_payment', [
                'idempotency_key' => $nsKey,
                'reference' => $vendor,
                'vendor_id' => $vendor->id,
                'created_by' => $staff->id,
                'meta' => [
                    'vendor_name' => $vendor->name,
                    'description' => $description,
                ],
            ]);

            $customer = User::find($wallet->user_id);
            if ($customer) {
                $this->points->earnForSpend($customer, $tenant, $amount, $tx, "earn:tx:{$tx->id}");
            }

            return $tx;
        });
    }

    public function takeDeposit(User $staff, Tenant $tenant, ?Vendor $vendor, string $payToken, string $type, int $amount, ?string $label, string $key): Deposit
    {
        $nsKey = 'merchant:'.($vendor?->id ?? 'staff').":$key";
        $existing = Deposit::withoutTenantScope()->where('tenant_id', $tenant->id)->where('idempotency_key', $nsKey)->first();
        if ($existing) {
            return $existing;
        }
        if ($amount <= 0) {
            throw ApiException::limitExceeded('Amount must be positive');
        }

        return DB::transaction(function () use ($staff, $tenant, $vendor, $payToken, $type, $amount, $label, $nsKey) {
            $wallet = $this->tokens->consume($payToken, $tenant, $staff);

            return $this->deposits->hold($wallet, $type, $amount, $nsKey, [
                'vendor_id' => $vendor?->id,
                'created_by' => $staff->id,
                'label' => $label,
            ]);
        });
    }
}
