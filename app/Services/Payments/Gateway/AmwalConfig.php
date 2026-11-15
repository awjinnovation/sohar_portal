<?php

namespace App\Services\Payments\Gateway;

use App\Models\Tenant;

/**
 * A tenant's Amwal Pay settings: mode (settings payments.amwal.mode, else
 * AMWAL_MODE) and credentials. Credentials are one set: the tenant's own
 * (tenants.gateway_credentials.amwal) when it has a secure key, else the
 * platform's (AMWAL_* in config/services.php) — never mixed field by field.
 */
final class AmwalConfig
{
    public const MODES = ['uat', 'production'];

    public function __construct(
        public readonly string $mode,
        public readonly string $merchantId,
        public readonly string $terminalId,
        public readonly string $secureKey,
    ) {}

    public static function forTenant(Tenant $tenant): self
    {
        $mode = (string) ($tenant->setting('payments.amwal.mode') ?: config('services.amwal.mode', 'uat'));
        $own = (array) data_get($tenant->gateway_credentials ?? [], 'amwal', []);
        $keys = filled($own['secure_key'] ?? null) ? $own : (array) config('services.amwal', []);

        return new self(
            in_array($mode, self::MODES, true) ? $mode : 'uat',
            trim((string) ($keys['merchant_id'] ?? '')),
            trim((string) ($keys['terminal_id'] ?? '')),
            trim((string) ($keys['secure_key'] ?? '')),
        );
    }

    /** Same credentials on another host (a session keeps the mode it was created in). */
    public function withMode(?string $mode): self
    {
        return $mode && $mode !== $this->mode && in_array($mode, self::MODES, true)
            ? new self($mode, $this->merchantId, $this->terminalId, $this->secureKey)
            : $this;
    }

    public function isConfigured(): bool
    {
        return $this->merchantId !== '' && $this->terminalId !== ''
            && $this->secureKey !== '' && ctype_xdigit($this->secureKey) && strlen($this->secureKey) % 2 === 0;
    }

    public function smartBoxUrl(): string
    {
        return (string) config("payments.amwal.hosts.{$this->mode}.smartbox");
    }

    public function apiUrl(string $path): string
    {
        return rtrim((string) config("payments.amwal.hosts.{$this->mode}.api"), '/').'/'.ltrim($path, '/');
    }
}
