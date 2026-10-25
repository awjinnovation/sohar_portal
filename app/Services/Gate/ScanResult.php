<?php

namespace App\Services\Gate;

/**
 * Scan result vocabulary (contract §B2) and its localized messages
 * (lang/{en,ar}/gate.php). Messages are always returned in both languages.
 */
final class ScanResult
{
    public const VALID = 'valid';
    public const REENTRY_OK = 'reentry_ok';
    public const ALREADY_USED = 'already_used';
    public const INVALID = 'invalid';
    public const WRONG_EVENT = 'wrong_event';
    public const WRONG_DATE = 'wrong_date';
    public const NOT_YET_OPEN = 'not_yet_open';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const WRONG_GATE = 'wrong_gate';

    public const ALL = [
        self::VALID, self::REENTRY_OK, self::ALREADY_USED, self::INVALID, self::WRONG_EVENT,
        self::WRONG_DATE, self::NOT_YET_OPEN, self::CANCELLED, self::REFUNDED, self::WRONG_GATE,
    ];

    /** Results that let the visitor through. */
    public const ADMITTED = [self::VALID, self::REENTRY_OK];

    public const REJECTS = [
        self::ALREADY_USED, self::INVALID, self::WRONG_EVENT, self::WRONG_DATE,
        self::NOT_YET_OPEN, self::CANCELLED, self::REFUNDED, self::WRONG_GATE,
    ];

    public static function isAdmitted(string $result): bool
    {
        return in_array($result, self::ADMITTED, true);
    }

    /**
     * {en, ar} message for a result, optionally with a detail line
     * (e.g. "Already used at 18:42 (Main Gate A)").
     *
     * @return array{en: string, ar: string}
     */
    public static function message(string $result, ?string $detail = null, array $params = [], string $direction = 'in'): array
    {
        $out = [];
        foreach (['en', 'ar'] as $locale) {
            $key = $direction === 'out' && $result === self::VALID ? 'gate.results.exit' : "gate.results.$result";
            $text = trans($key, [], $locale);
            if ($detail) {
                // A param may be per-locale: ['en' => 'Main Gate A', 'ar' => 'البوابة الرئيسية A'].
                $local = array_map(fn ($v) => is_array($v) ? (string) ($v[$locale] ?? reset($v)) : $v, $params);
                $line = trans("gate.details.$detail", $local, $locale);
                if ($line !== "gate.details.$detail") {
                    // Arabic joins with the Arabic comma (style rules forbid the em dash).
                    $text .= ($locale === 'ar' ? '، ' : ' — ').$line;
                }
            }
            $out[$locale] = $text;
        }

        return $out;
    }
}
