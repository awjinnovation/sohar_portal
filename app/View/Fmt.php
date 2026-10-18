<?php

namespace App\View;

use App\Tenancy\TenantContext;

/**
 * View formatting helpers (design_system.md §3.2.3): money with grouping,
 * exactly currency_decimals decimals, ISO code, U+2212 minus.
 */
class Fmt
{
    public static function currency(): string
    {
        return app(TenantContext::class)->get()?->currency() ?? 'OMR';
    }

    public static function decimals(): int
    {
        return app(TenantContext::class)->get()?->currencyDecimals() ?? 3;
    }

    /** Minor units → "12,450.000" (no sign, no currency). */
    public static function number(int $minor, ?int $decimals = null): string
    {
        $decimals ??= self::decimals();
        $minor = abs($minor);
        $div = 10 ** $decimals;
        $int = number_format(intdiv($minor, $div), 0, '.', ',');

        return $decimals > 0 ? $int.'.'.str_pad((string) ($minor % $div), $decimals, '0', STR_PAD_LEFT) : $int;
    }

    /** Decimal amount (string/float, e.g. a `decimal:3` cast) → minor units. */
    public static function toMinor(mixed $amount, ?int $decimals = null): int
    {
        return \App\Support\Money::toMinor(is_numeric($amount) ? (string) $amount : '0', $decimals ?? self::decimals());
    }

    /** Plain-text money: "OMR 12.450", "+OMR 5.000", "−OMR 1.000". */
    public static function money(int $minor, ?string $currency = null, ?int $decimals = null, bool $signed = false): string
    {
        $sign = $minor < 0 ? "\u{2212}" : ($signed && $minor > 0 ? '+' : '');

        return $sign.($currency ?? self::currency()).' '.self::number($minor, $decimals);
    }

    /** Translatable value ({"ar":…, "en":…} array or plain string) → string in $locale. */
    public static function t(mixed $value, ?string $locale = null): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }
        $locale ??= app()->getLocale();

        return (string) ($value[$locale] ?? $value['ar'] ?? $value['en'] ?? (reset($value) ?: ''));
    }
}
