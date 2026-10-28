<?php

namespace App\Services\Payments\Gateway;

use App\Models\Tenant;

/**
 * A tenant's Thawani settings: mode (settings payments.thawani.mode, else
 * THAWANI_MODE) and keys. Keys are taken as one set: the tenant's own
 * (tenants.gateway_credentials.thawani) when it has a secret key, else the
 * platform's (THAWANI_* in config/services.php) — never mixed field by field.
 */
final class ThawaniConfig
{
    public const MODES = ['uat', 'production'];

    public function __construct(
        public readonly string $mode,
        public readonly string $secretKey,
        public readonly string $publishableKey,
        public readonly string $webhookSecret,
    ) {}

    public static function forTenant(Tenant $tenant): self
    {
        $mode = (string) ($tenant->setting('payments.thawani.mode') ?: config('services.thawani.mode', 'uat'));
        $own = (array) data_get($tenant->gateway_credentials ?? [], 'thawani', []);
        $keys = filled($own['secret_key'] ?? null) ? $own : (array) config('services.thawani', []);

        return new self(
            in_array($mode, self::MODES, true) ? $mode : 'uat',
            trim((string) ($keys['secret_key'] ?? '')),
            trim((string) ($keys['publishable_key'] ?? '')),
            trim((string) ($keys['webhook_secret'] ?? '')),
        );
    }

    /** Same keys on another host (a session keeps the mode it was created in). */
    public function withMode(?string $mode): self
    {
        return $mode && $mode !== $this->mode && in_array($mode, self::MODES, true)
            ? new self($mode, $this->secretKey, $this->publishableKey, $this->webhookSecret)
            : $this;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publishableKey !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config("payments.thawani.base_urls.{$this->mode}"), '/');
    }

    public function apiUrl(string $path): string
    {
        return $this->baseUrl().'/api/v1/'.ltrim($path, '/');
    }

    /** The hosted payment page for a session. */
    public function checkoutUrl(string $sessionId): string
    {
        return $this->baseUrl().'/pay/'.rawurlencode($sessionId).'?key='.rawurlencode($this->publishableKey);
    }
}
