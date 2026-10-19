<?php

namespace App\Support;

/**
 * Helpers for localized JSON values stored as {en, ar}
 * (docs/places_content_contract.md §2–3).
 */
class Localized
{
    public const LOCALES = ['en', 'ar'];

    /** Always {en, ar} (null for a missing language). */
    public static function pair(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $out = [];
        foreach (self::LOCALES as $locale) {
            $v = $value[$locale] ?? null;
            $out[$locale] = is_string($v) && trim($v) !== '' ? $v : null;
        }

        return $out;
    }

    /** Value in $locale (default: request locale), falling back to the other language. */
    public static function text(mixed $value, ?string $locale = null): ?string
    {
        $pair = self::pair($value);
        $locale = in_array($locale ??= app()->getLocale(), self::LOCALES, true) ? $locale : 'en';
        $other = $locale === 'ar' ? 'en' : 'ar';

        return $pair[$locale] ?? $pair[$other];
    }

    /** Normalize form input; null when both languages are empty. */
    public static function fromInput(mixed $value, ?callable $map = null): ?array
    {
        $pair = [];
        foreach (self::LOCALES as $locale) {
            $v = is_array($value) ? ($value[$locale] ?? null) : null;
            $v = is_string($v) ? trim($v) : null;
            if ($map && $v !== null && $v !== '') {
                $v = $map($v);
            }
            $pair[$locale] = $v === '' ? null : $v;
        }

        return array_filter($pair, fn ($v) => $v !== null) ? $pair : null;
    }
}
