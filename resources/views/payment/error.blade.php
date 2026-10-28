@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    // Codes from App\Http\Controllers\Web\PaymentController::ERR_* → [ar, en]
    $messages = [
        'not_pending' => ['لم تعد هذه العملية بانتظار الدفع.', 'This payment is no longer pending.'],
        'not_completed' => ['لم تكتمل عملية الدفع بعد. ستظهر النتيجة في التطبيق خلال لحظات.', 'The payment is not completed yet. The app will show the result in a moment.'],
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
@endphp
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
    <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('لا يُخصم أي مبلغ ما لم تكتمل العملية. عُد إلى التطبيق لتحاول مجددًا.', 'You are not charged for a payment that did not complete. Return to the app to try again.') }}</div></div>
@endsection
