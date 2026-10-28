<?php

namespace App\Services\Payments\Gateway;

/** One logical gateway operation (retried as one unit by GatewayHttp). */
final class GatewayRequest
{
    public function __construct(
        public readonly string $gateway,
        public readonly ?string $mode,
        public readonly string $operation,
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?array $body = null,
        public readonly ?int $tenantId = null,
        public readonly ?int $paymentId = null,
        // false for create session / refund: after a timeout the request may
        // have been processed, so only provably unsent attempts are retried.
        public readonly bool $idempotent = true,
        // HTTP statuses that mean "not processed, try later" for a
        // non-idempotent request.
        public readonly array $unprocessedStatuses = [425, 429, 503],
    ) {}
}
