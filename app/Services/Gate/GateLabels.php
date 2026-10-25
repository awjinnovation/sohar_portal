<?php

namespace App\Services\Gate;

/**
 * Admin panel labels for the gate module. Arabic source strings, translated
 * for the EN panel through lang/gate/en.json (loaded by GateServiceProvider).
 */
class GateLabels
{
    /** result => [label, pill variant] */
    public const RESULTS = [
        'valid' => ['دخول مسموح', 'success'],
        'reentry_ok' => ['إعادة دخول', 'info'],
        'already_used' => ['مستخدمة مسبقًا', 'warning'],
        'invalid' => ['رمز غير صالح', 'error'],
        'wrong_event' => ['فعالية أخرى', 'error'],
        'wrong_date' => ['تاريخ غير صحيح', 'error'],
        'not_yet_open' => ['لم تُفتح بعد', 'warning'],
        'cancelled' => ['ملغاة', 'error'],
        'refunded' => ['مستردة', 'error'],
        'wrong_gate' => ['بوابة خاطئة', 'warning'],
    ];

    public const GATE_DIRECTIONS = [
        'entry' => 'دخول',
        'exit' => 'خروج',
        'both' => 'دخول وخروج',
    ];

    public const SCAN_DIRECTIONS = [
        'in' => 'دخول',
        'out' => 'خروج',
    ];

    public const SOURCES = [
        'camera' => 'الكاميرا',
        'hardware' => 'ماسح مدمج',
        'wedge' => 'ماسح USB/بلوتوث',
        'manual' => 'إدخال يدوي',
        'box_office' => 'بيع البوابة',
    ];

    public const DEVICE_TYPES = [
        'android_pos' => 'جهاز نقاط بيع أندرويد',
        'handheld' => 'ماسح محمول',
        'phone' => 'هاتف',
        'tablet' => 'جهاز لوحي',
        'web' => 'متصفح (حاسوب)',
    ];

    /** connectivity => [label, variant] */
    public const CONNECTIVITY = [
        'online' => ['متصل', 'success'],
        'idle' => ['خامل', 'warning'],
        'offline' => ['غير متصل', 'neutral'],
        'revoked' => ['ملغى', 'error'],
    ];

    public static function result(?string $result, ?string $direction = null): string
    {
        if ($result === 'valid' && $direction === 'out') {
            return __('خروج');
        }

        return __(self::RESULTS[$result][0] ?? (string) $result);
    }

    public static function resultVariant(?string $result): string
    {
        return self::RESULTS[$result][1] ?? 'neutral';
    }

    public static function label(array $map, ?string $key): string
    {
        $v = $map[$key] ?? $key;

        return __((string) (is_array($v) ? $v[0] : $v));
    }
}
