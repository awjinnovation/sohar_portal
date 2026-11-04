<?php

namespace App\Services\Satisfaction;

use App\Models\Tenant;
use App\Support\Localized;

/**
 * The tenant's `radi` settings (docs/radi_satisfaction_contract.md §1),
 * normalized for /tenant/config, GET /satisfaction/survey and the admin
 * report: asset URL resolved, {en, ar} pairs, aspects with valid unique keys.
 */
final class RadiSettings
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    public const MAX_ASPECTS = 10;

    public const COMMENT_MAX = 1000;

    /** Public block (tenant config `radi`). */
    public static function present(Tenant $tenant): array
    {
        $s = (array) $tenant->setting('radi', []);
        $text = fn ($v) => array_map(fn ($x) => $x ?? '', Localized::pair($v));

        return [
            'logo_url' => Tenant::assetUrl(is_string($s['logo_url'] ?? null) ? trim($s['logo_url']) : null) ?? '',
            'external_url' => self::externalUrl($s['external_url'] ?? null),
            'title' => $text($s['title'] ?? null),
            'intro' => $text($s['intro'] ?? null),
            'aspects' => self::aspects($tenant),
        ];
    }

    /** @return list<array{key:string, label:array{en:string, ar:string}}> */
    public static function aspects(Tenant $tenant): array
    {
        $out = [];
        foreach ((array) $tenant->setting('radi.aspects', []) as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key) || isset($out[$key])) {
                continue;
            }
            $label = array_map(fn ($x) => $x ?? '', Localized::pair($row['label'] ?? null));
            $out[$key] = ['key' => $key, 'label' => $label];
            if (count($out) >= self::MAX_ASPECTS) {
                break;
            }
        }

        return array_values($out);
    }

    /** @return list<string> */
    public static function aspectKeys(Tenant $tenant): array
    {
        return array_column(self::aspects($tenant), 'key');
    }

    /** Label of an aspect key in the current locale (the key itself when unknown). */
    public static function aspectLabel(Tenant $tenant, string $key): string
    {
        foreach (self::aspects($tenant) as $a) {
            if ($a['key'] === $key) {
                return Localized::text($a['label']) ?? $key;
            }
        }

        return $key;
    }

    /**
     * Admin check of a posted `radi.aspects` list: at most MAX_ASPECTS rows of
     * {key, label {en?, ar?}} with unique lowercase keys and a label in at
     * least one language. Returns [$errorKey => message] or [].
     */
    public static function aspectsErrors(mixed $list, string $errorKey): array
    {
        $valid = is_array($list) && array_is_list($list) && count($list) <= self::MAX_ASPECTS;
        $seen = [];
        foreach ($valid ? $list : [] as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            $label = is_array($row) ? ($row['label'] ?? null) : null;
            if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key) || isset($seen[$key])
                || array_diff(array_keys($row), ['key', 'label'])
                || ! is_array($label) || array_diff(array_keys($label), ['en', 'ar'])
                || ! array_filter($label, fn ($v) => is_string($v) && trim($v) !== '')
                || array_filter($label, fn ($v) => ! is_string($v) || mb_strlen($v) > 60)) {
                $valid = false;
                break;
            }
            $seen[$key] = true;
        }

        return $valid ? [] : [$errorKey => __('اكتب الجوانب قائمة JSON لا تزيد على :max عناصر، ولكل عنصر مفتاح key بأحرف إنجليزية صغيرة، وتسمية label بالعربية أو الإنجليزية لا تزيد على 60 حرفًا.', ['max' => self::MAX_ASPECTS])];
    }

    /** Only http(s) links are passed to the app. */
    private static function externalUrl(mixed $url): string
    {
        $url = is_string($url) ? trim($url) : '';

        return preg_match('#^https?://\S+$#i', $url) ? $url : '';
    }
}
