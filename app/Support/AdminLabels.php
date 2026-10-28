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
        'vendor_payment' => 'دفع إلى تاجر',
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
        'released' => ['مُرجَع', 'success'],
        'forfeited' => ['مُصادَر', 'danger'],
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
        'cancelled' => ['ملغى', 'secondary'],
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

    /** Payment timeline (payment_events.type). */
    public const PAYMENT_EVENTS = [
        'created' => 'أُنشئت الدفعة',
        'session_created' => 'فُتحت جلسة دفع لدى البوابة',
        'checkout_opened' => 'فُتحت صفحة الدفع',
        'gateway_checked' => 'استُعلم من البوابة',
        'webhook_received' => 'وصل إشعار من البوابة',
        'completed' => 'اكتملت الدفعة',
        'cancelled' => 'أُلغيت الدفعة',
        'failed' => 'فشلت الدفعة',
        'refunded' => 'استُردت الدفعة',
        'late_capture' => 'دُفع المبلغ بعد الإلغاء',
        'amount_mismatch' => 'المبلغ المدفوع لا يطابق الطلب',
        'gateway_refund_succeeded' => 'أُعيد المبلغ إلى البطاقة',
        'gateway_refund_retry' => 'تُعاد محاولة الاسترداد',
        'gateway_refund_failed' => 'فشل الاسترداد إلى البطاقة',
        'gateway_refund_needs_review' => 'الاسترداد يحتاج إلى مراجعة',
        'check_requested' => 'طُلب التحقق يدويًا',
        'review_cleared' => 'رُوجعت الدفعة',
        'webhook_replayed' => 'أُعيدت معالجة إشعار',
        'duplicate_capture' => 'دُفع المبلغ مرتين',
        'points_retaken' => 'خُصمت النقاط مجددًا',
        'points_retake_failed' => 'تعذّر خصم النقاط مجددًا',
    ];

    public const PAYMENT_EVENT_SOURCES = [
        'api' => 'التطبيق',
        'redirect' => 'صفحة الدفع',
        'webhook' => 'إشعار البوابة',
        'reconciler' => 'المطابقة التلقائية',
        'poll' => 'استعلام التطبيق',
        'admin' => 'لوحة التحكم',
        'scheduler' => 'المهام المجدولة',
        'system' => 'النظام',
    ];

    public const GATEWAY_REFUND_STATUSES = [
        'pending' => ['بانتظار الإرسال', 'warning'],
        'processing' => ['قيد الإرسال', 'info'],
        'succeeded' => ['أُعيد إلى البطاقة', 'success'],
        'failed' => ['فشل', 'error'],
        'needs_review' => ['يحتاج إلى مراجعة', 'warning'],
    ];

    public const WEBHOOK_STATUSES = [
        'received' => ['مستلم', 'info'],
        'processed' => ['عولج', 'success'],
        'ignored' => ['لا يخص دفعة', 'neutral'],
        'failed' => ['فشلت معالجته', 'error'],
        'rejected' => ['مرفوض', 'error'],
    ];

    public const GATEWAY_SESSION_STATUSES = [
        'open' => ['مفتوحة', 'info'],
        'paid' => ['مدفوعة', 'success'],
        'cancelled' => ['ملغاة', 'neutral'],
        'expired' => ['منتهية', 'neutral'],
    ];

    public const GATEWAY_CALL_OUTCOMES = [
        'ok' => ['نجح', 'success'],
        'client_error' => ['رُفض الطلب', 'warning'],
        'server_error' => ['خطأ لدى البوابة', 'error'],
        'network_error' => ['تعذّر الاتصال', 'error'],
        'circuit_open' => ['أُوقف مؤقتًا', 'neutral'],
    ];

    public const PAYMENT_REVIEW_REASONS = [
        'amount_mismatch' => 'المبلغ المدفوع لا يطابق الطلب',
        'refund_failed' => 'فشل الاسترداد إلى البطاقة',
        'refund_needs_review' => 'نتيجة الاسترداد غير مؤكدة',
        'duplicate_capture' => 'دُفع المبلغ مرتين عبر جلستين',
        'session_unverifiable' => 'تعذّر التحقق من جلسة الدفع لدى البوابة',
        'late_capture_points' => 'دُفع بعد الإلغاء ولم يكفِ رصيد النقاط لخصمها مجددًا',
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
