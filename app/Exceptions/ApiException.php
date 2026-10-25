<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain error rendered as the contract error envelope:
 * {success:false, message, code, errors?} with an HTTP status.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($message ?: $errorCode);
    }

    public static function insufficientBalance(int $availableMinor = 0): static
    {
        return new static('INSUFFICIENT_BALANCE', __('platform.errors.insufficient_balance'), 422, ['available_minor' => $availableMinor]);
    }

    public static function walletFrozen(): static
    {
        return new static('WALLET_FROZEN', __('platform.errors.wallet_frozen'), 423);
    }

    public static function payTokenExpired(): static
    {
        return new static('PAY_TOKEN_EXPIRED', __('platform.errors.pay_token_expired'), 410);
    }

    public static function payTokenInvalid(): static
    {
        return new static('PAY_TOKEN_INVALID', __('platform.errors.pay_token_invalid'), 422);
    }

    public static function limitExceeded(string $message = ''): static
    {
        return new static('LIMIT_EXCEEDED', $message ?: __('platform.errors.limit_exceeded'), 422);
    }

    public static function moduleDisabled(string $module): static
    {
        return new static('MODULE_DISABLED', __('platform.errors.module_disabled', ['module' => $module]), 403, ['module' => $module]);
    }

    public static function refundNotAllowed(string $message = ''): static
    {
        return new static('REFUND_NOT_ALLOWED', $message ?: __('platform.errors.refund_not_allowed'), 422);
    }

    public static function insufficientPoints(int $balance = 0): static
    {
        return new static('INSUFFICIENT_POINTS', __('platform.errors.insufficient_points'), 422, ['points_balance' => $balance]);
    }

    public static function idempotencyConflict(): static
    {
        return new static('IDEMPOTENCY_CONFLICT', __('platform.errors.idempotency_conflict'), 409);
    }

    public function render()
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->extra ? ['data' => $this->extra] : []), $this->status);
    }
}
