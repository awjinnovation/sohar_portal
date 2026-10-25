<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use App\Models\PayToken;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

/**
 * Short-lived, single-use, HMAC-signed wallet pay tokens rendered as a QR
 * code by the app and scanned by vendor staff (contract §6 / §9).
 *
 * Format: "pt1.<base64url(json{jti,tid,uid,exp})>.<base64url(hmac-sha256)>"
 * The jti is persisted so a token can only be consumed once.
 */
class PayTokenService
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon, expires_in: int}
     */
    public function issue(User $user, Tenant $tenant): array
    {
        $wallet = $this->wallets->walletFor($user, $tenant);
        if ($wallet->isFrozen()) {
            throw ApiException::walletFrozen();
        }

        $ttl = (int) config('payments.pay_token_ttl_seconds', 60);
        $expiresAt = now()->addSeconds($ttl);
        $jti = Str::random(32);

        $token = new PayToken([
            'jti' => $jti,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'expires_at' => $expiresAt,
        ]);
        $token->tenant_id = $tenant->id;
        $token->save();

        $payload = $this->b64(json_encode(['jti' => $jti, 'tid' => $tenant->id, 'uid' => $user->id, 'exp' => $expiresAt->getTimestamp()]));

        return [
            'token' => 'pt1.'.$payload.'.'.$this->sign($payload),
            'expires_at' => $expiresAt,
            'expires_in' => $ttl,
        ];
    }

    /**
     * Verify and burn a token. Returns the customer's wallet.
     *
     * @throws ApiException PAY_TOKEN_INVALID | PAY_TOKEN_EXPIRED (expired or already used)
     */
    public function consume(string $token, Tenant $tenant, ?User $staff = null): Wallet
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== 'pt1' || ! hash_equals($this->sign($parts[1]), $parts[2])) {
            throw ApiException::payTokenInvalid();
        }

        $claims = json_decode($this->unb64($parts[1]), true);
        if (! is_array($claims) || (int) ($claims['tid'] ?? 0) !== $tenant->id) {
            throw ApiException::payTokenInvalid();
        }

        if ((int) ($claims['exp'] ?? 0) < now()->getTimestamp()) {
            throw ApiException::payTokenExpired();
        }

        // Atomic single use: only one concurrent consumer can flip used_at.
        $burned = PayToken::withoutTenantScope()
            ->where('jti', $claims['jti'] ?? '')
            ->where('tenant_id', $tenant->id)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->update(['used_at' => now(), 'used_by' => $staff?->id]);

        if ($burned !== 1) {
            throw ApiException::payTokenExpired();
        }

        $row = PayToken::withoutTenantScope()->where('jti', $claims['jti'])->first();

        return Wallet::withoutTenantScope()->findOrFail($row->wallet_id);
    }

    private function sign(string $payload): string
    {
        $key = (string) config('app.key');

        return $this->b64(hash_hmac('sha256', 'pay-token|'.$payload, $key, true));
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
