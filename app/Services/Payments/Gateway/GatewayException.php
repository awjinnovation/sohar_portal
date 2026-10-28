<?php

namespace App\Services\Payments\Gateway;

use RuntimeException;

/**
 * A gateway call that did not succeed. `kind`:
 *  - network       no HTTP response (connect error, timeout)
 *  - server        5xx / 408 / 425 / 429 (worth retrying)
 *  - client        other 4xx or `success: false` (retrying will not help)
 *  - circuit_open  not sent: the gateway failed repeatedly just before
 *  - config        not sent: the tenant's gateway is not configured
 *
 * `ambiguous`: a non-idempotent request (create session, refund) may have
 * reached the gateway, so it must not be sent again blindly.
 */
class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $kind,
        public readonly ?int $status = null,
        public readonly ?array $json = null,
        public readonly bool $ambiguous = false,
    ) {
        parent::__construct($message);
    }

    public function retryable(): bool
    {
        return ! $this->ambiguous && in_array($this->kind, ['network', 'server', 'circuit_open'], true);
    }

    public static function notConfigured(string $what): self
    {
        return new self($what, 'config');
    }
}
