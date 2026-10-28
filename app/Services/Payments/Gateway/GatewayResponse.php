<?php

namespace App\Services\Payments\Gateway;

final class GatewayResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $json,
        public readonly string $body,
        public readonly string $correlationId,
    ) {}

    /** The `data` object of a Thawani-style envelope ({success, code, description, data}). */
    public function data(): array
    {
        $data = $this->json['data'] ?? [];

        return is_array($data) ? $data : [];
    }
}
