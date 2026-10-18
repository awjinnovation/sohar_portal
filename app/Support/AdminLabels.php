<?php

namespace App\Support;

/**
 * Admin panel labels / badge colors. Labels are Arabic source strings;
 * label()/badge() translate them through lang/en.json for the EN panel.
 */
class AdminLabels
{
    public const TX_TYPES = [
        'topup' => 'شحن رصيد',
        'ticket_purchase' => 'شراء تذاكر',
        'vendor_payment' => 'دفع لتاجر',
        'deposit_hold' => 'حجز تأمين',
        'deposit_release' => 'إرجاع تأمين',
        'deposit_forfeit' => 'مصادرة تأمين',
        'refund' => 'استرداد',
        'refund_reversal' => 'إلغاء استرداد',
        'points_redemption' => 'استبدال نقاط',
        'adjustment' => 'تعديل يدوي',
    ];

    public const BUCKETS = [
        'cash' => 'نقدي',
        'promo' => 'ترويجي',
        'mixed' => 'مختلط',
    ];

    public const WALLET_STATUSES = [
        'active' => ['نشطة', 'success'],
        'frozen' => ['مجمّدة', 'danger'],
    ];

    public const TX_STATUSES = [
        'completed' => ['مكتملة', 'success'],
        'pending' => ['معلّقة', 'warning'],
        'reversed' => ['ملغاة', 'secondary'],
    ];

    public const REFUND_STATUSES = [
        'pending' => ['قيد الانتظار', 'warning'],
        'approved' => ['موافق عليه', 'info'],
        'paid' => ['مدفوع', 'success'],
        'rejected' => ['مرفوض', 'danger'],
    ];

    public const DEMO_STATUSES = [
        'new' => ['جديد', 'brand'],
        'contacted' => ['تم التواصل', 'info'],
        'closed' => ['مغلق', 'secondary'],
    ];

    public const REFUND_METHODS = [
        'original_payment' => 'وسيلة الدفع الأصلية',
        'bank_transfer' => 'تحويل بنكي',
    ];

    public const DEPOSIT_STATUSES = [
        'held' => ['محجوز', 'warning'],
        'released' => ['مُرجع', 'success'],
        'forfeited' => ['مُصادر', 'danger'],
    ];

    public const DEPOSIT_TYPES = [
        'wristband' => 'سوار',
        'cup' => 'كوب',
        'locker' => 'خزانة',
        'equipment' => 'معدات',
        'other' => 'أخرى',
    ];

    public const PAYMENT_STATUSES = [
        'pending' => ['قيد الانتظار', 'warning'],
        'processing' => ['قيد المعالجة', 'info'],
        'completed' => ['مكتمل', 'success'],
        'failed' => ['فشل', 'danger'],
        'cancelled' => ['ملغي', 'secondary'],
        'refunded' => ['مسترد', 'dark'],
    ];

    public const PAYMENT_TYPES = [
        'ticket' => 'تذاكر',
        'topup' => 'شحن محفظة',
    ];

    public const PAYMENT_METHODS = [
        'gateway' => 'بوابة الدفع',
        'wallet' => 'المحفظة',
    ];

    public const VENDOR_STAFF_ROLES = [
        'staff' => 'موظف',
        'manager' => 'مدير',
    ];

    /** Bootstrap badge HTML for a [label, color] map entry. */
    public static function badge(array $map, ?string $key): string
    {
        [$label, $color] = $map[$key] ?? [$key ?? '-', 'secondary'];

        return '<span class="badge bg-'.e($color).'">'.e(__((string) $label)).'</span>';
    }

    public static function label(array $map, ?string $key): string
    {
        $value = $map[$key] ?? $key ?? '-';

        // Labels are written in Arabic and translated via lang/en.json.
        return __((string) (is_array($value) ? $value[0] : $value));
    }
}
