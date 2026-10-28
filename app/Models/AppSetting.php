<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use \App\Tenancy\BelongsToTenant;
    use \App\Models\Concerns\BustsPublicApiCache;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the value with appropriate type casting
     */
    public function getValueAttribute()
    {
        $value = $this->setting_value;

        return match($this->setting_type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'decimal' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember(static::cacheKey("key_{$key}"), 3600, function () use ($key) {
            $setting = static::where('setting_key', $key)->first();
            return $setting ? ['v' => $setting->value] : null;
        });

        return $value === null ? $default : $value['v'];
    }

    /**
     * Get a setting only if it is flagged public (safe for unauthenticated API).
     */
    public static function getPublic(string $key, mixed $default = null): mixed
    {
        $public = static::getPublicSettings();

        return array_key_exists($key, $public) ? $public[$key] : $default;
    }

    protected static function cacheKey(string $suffix): string
    {
        return 'app_setting_t'.(app(\App\Tenancy\TenantContext::class)->id() ?? 0).'_'.$suffix;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null, bool $isPublic = false): self
    {
        // Convert value to string based on type
        $stringValue = match($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        $setting = static::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $stringValue,
                'setting_type' => $type,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );

        // Clear cache
        Cache::forget(static::cacheKey("key_{$key}"));
        Cache::forget(static::cacheKey('public'));

        return $setting;
    }

    /**
     * Get all public settings for API
     */
    public static function getPublicSettings(): array
    {
        return Cache::remember(static::cacheKey('public'), 3600, function () {
            $settings = static::where('is_public', true)->get();

            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->setting_key] = $setting->value;
            }

            return $result;
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(?string $key = null): void
    {
        Cache::forget(static::cacheKey('public'));
        if ($key) {
            Cache::forget(static::cacheKey("key_{$key}"));
        }
    }

    protected static function booted(): void
    {
        static::saved(fn (AppSetting $s) => static::clearCache($s->setting_key));
        static::deleted(fn (AppSetting $s) => static::clearCache($s->setting_key));
    }
}
