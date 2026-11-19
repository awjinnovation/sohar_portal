@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    // Codes from App\Http\Controllers\Web\PaymentController::ERR_* → [ar, en]
    $messages = [
        'not_pending' => ['لم تعد هذه العملية بانتظار الدفع.', 'This payment is no longer pending.'],
        'not_completed' => ['نؤكّد الدفع مع البنك الآن، وتتحدّث هذه الصفحة تلقائيًّا.', 'We’re confirming the payment with the bank. This page updates on its own.'],
        'payment_declined' => ['لم تُقبل البطاقة، ولم يُخصم أي مبلغ. يمكنك المحاولة مرة أخرى.', 'The card was declined and nothing was charged. You can try again.'],
        'no_response' => ['لم يصل رد من بوابة الدفع.', 'No response was received from the payment gateway.'],
        'verification_failed' => ['تعذر التحقق من رد بوابة الدفع.', 'The payment gateway response could not be verified.'],
        'payment_failed' => ['لم تنجح عملية الدفع. حاول مرة أخرى.', 'The payment failed. Please try again.'],
        'server_error' => ['حدث خطأ في معالجة الدفع.', 'An error occurred while processing your payment.'],
        'gateway_unavailable' => ['تعذّر الاتصال ببوابة الدفع الآن. أعد المحاولة بعد لحظات.', 'The payment gateway can’t be reached right now. Please try again in a moment.'],
        'gateway_not_configured' => ['الدفع بالبطاقة غير متاح حاليًا. تواصل مع المنظّم.', 'Card payment isn’t available right now. Please contact the organizer.'],
    ];
    $code = $code ?? 'server_error';
    [$msgAr, $msgEn] = $messages[$code] ?? $messages['server_error'];
    $shown = $t($msgAr, $msgEn);
    $pending = $code === 'not_completed';
    if ($pending && empty($refreshUrl)) {
        // The page stopped reloading itself: the confirmation is late, never a reason to pay again.
        $shown = $t('لم يصل تأكيد البنك بعد، وسيكتمل طلبك تلقائيًّا فور وصوله.', 'The bank’s confirmation hasn’t arrived yet. Your order completes on its own as soon as it does.');
    }
@endphp
@if($pending && ! empty($refreshUrl))
    @push('head')
        <meta http-equiv="refresh" content="3;url={{ $refreshUrl }}">
    @endpush
@endif
@section('title', $pending ? $t('الدفع قيد المعالجة', 'Payment processing') : $t('تعذر إتمام الدفع', 'Payment problem'))
@section('band-sub', $t('حالة الدفع', 'Payment status'))

@section('content')
    <section class="s-card">
        <div class="result">
            <div class="result-ico {{ $pending ? 'warn' : 'ko' }}"><i class="bi bi-{{ $pending ? 'hourglass-split' : 'exclamation-lg' }}"></i></div>
            <h1>{{ $pending ? $t('الدفع قيد المعالجة', 'Payment processing') : $t('تعذر إتمام الدفع', 'We couldn’t complete the payment') }}</h1>
            <p data-error-code="{{ $code }}">{{ $shown }}</p>
            @if(! empty($status))
                <p style="margin-top: 12px"><span class="pill pill-neutral ltr">{{ $status }}</span></p>
            @endif
            @if(! empty($retryUrl))
                <p style="margin-top: 16px"><a class="btn btn-primary" href="{{ $retryUrl }}">{{ $pending ? $t('تحقّق مرة أخرى', 'Check again') : $t('أعد المحاولة', 'Try again') }}</a></p>
            @endif
        </div>
    </section>
    @if(isset($error) && config('app.debug'))
        <div class="note warn ltr" style="text-align:left"><i class="bi bi-bug"></i><div><b>Debug:</b> {{ $error }}</div></div>
    @endif
    @if($pending)
        <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('إذا خُصم المبلغ من بطاقتك فلا تدفع مرة أخرى، فطلبك يكتمل تلقائيًّا فور تأكيد الدفع.', 'If your card was charged, don’t pay again: your order completes on its own once the payment is confirmed.') }}</div></div>
    @else
        <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('لا يُخصم أي مبلغ ما لم تكتمل العملية. عُد إلى التطبيق لتحاول مجددًا.', 'You are not charged for a payment that did not complete. Return to the app to try again.') }}</div></div>
    @endif
@endsection
