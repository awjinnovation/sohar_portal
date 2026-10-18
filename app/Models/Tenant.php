<?php

namespace App\Models;

use App\Support\Arr2;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * An organizer / party owning events, vendors, wallets... (contract §1).
 *
 * @property array $name
 * @property array|null $settings
 * @property array|null $gateway_credentials
 */
class Tenant extends Model
{
    public const STATUS_ACTIVE = 'active';

    protected $fillable = ['slug', 'name', 'status', 'domain', 'settings', 'gateway_credentials'];

    protected $casts = [
        'name' => 'array',
        'settings' => 'array',
        'gateway_credentials' => 'encrypted:array',
    ];

    protected $hidden = ['gateway_credentials'];

    /** @var array|null memoized merged settings */
    protected ?array $resolvedSettings = null;

    protected static function booted(): void
    {
        static::saved(function (Tenant $tenant) {
            $tenant->resolvedSettings = null;
            Cache::forget(self::cacheKey($tenant->slug));
            Cache::forget('tenant_config_'.$tenant->id);
        });
    }

    public static function cacheKey(string $slug): string
    {
        return 'tenant_by_slug_'.$slug;
    }

    public static function findBySlug(?string $slug): ?self
    {
        if (! $slug) {
            return null;
        }

        return static::where('slug', $slug)->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = $this->name ?? [];

        return $name[$locale] ?? $name['en'] ?? $name['ar'] ?? $this->slug;
    }

    /**
     * Full settings document: config('tenancy.defaults') deep-merged with
     * this tenant's stored overrides.
     */
    public function resolvedSettings(): array
    {
        return $this->resolvedSettings ??= Arr2::mergeSettings(
            config('tenancy.defaults', []),
            $this->settings ?? []
        );
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->resolvedSettings(), $key, $default);
    }

    /** Persist a single setting (dotted key) into the overrides. */
    public function putSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->resolvedSettings = null;

        return $this;
    }

    public function moduleEnabled(string $module): bool
    {
        return (bool) $this->setting("modules.$module", false);
    }

    public function currency(): string
    {
        return (string) $this->setting('tenant.currency', 'OMR');
    }

    public function currencyDecimals(): int
    {
        return (int) $this->setting('tenant.currency_decimals', 3);
    }

    public function formatMoney(int $minor): string
    {
        return Money::format($minor, $this->currencyDecimals());
    }

    public function toMinor(string|int|float|null $amount): int
    {
        return Money::toMinor($amount, $this->currencyDecimals());
    }

    /**
     * Gateway credentials for a provider, falling back to config/services.php.
     */
    public function gatewayCredential(string $provider, string $key): mixed
    {
        $value = data_get($this->gateway_credentials ?? [], "$provider.$key");

        return ($value === null || $value === '')
            ? config('services.'.str_replace('-', '_', $provider).".$key")
            : $value;
    }

    /** Public URL of an uploaded branding asset or an absolute URL as is. */
    public static function assetUrl(?string $pathOrUrl): ?string
    {
        if (! $pathOrUrl) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $pathOrUrl)) {
            return $pathOrUrl;
        }
        if (str_starts_with($pathOrUrl, 'storage/') || str_starts_with($pathOrUrl, '/')) {
            return asset(ltrim($pathOrUrl, '/'));
        }

        return Storage::disk('public')->url($pathOrUrl);
    }

    public function logoUrl(): ?string
    {
        return self::assetUrl($this->setting('branding.logo_url'));
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
