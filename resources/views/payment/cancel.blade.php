@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $failed = ($payment->status ?? null) === 'failed';
@endphp
@section('title', $failed ? $t('فشل الدفع', 'Payment failed') : $t('أُلغي الدفع', 'Payment cancelled'))
@section('band-sub', $t('حالة الدفع', 'Payment status'))

@section('content')
    <section class="s-card">
        <div class="result">
            <div class="result-ico {{ $failed ? 'ko' : 'warn' }}"><i class="bi bi-{{ $failed ? 'x-lg' : 'slash-circle' }}"></i></div>
            <h1>{{ $failed ? $t('فشل الدفع', 'Payment failed') : $t('أُلغي الدفع', 'Payment cancelled') }}</h1>
            <p>{{ $t('لم يُخصم أي مبلغ من حسابك.', 'No money has been taken from your account.') }}</p>
            @if($payment)
                <x-admin.money :minor="(int) $payment->amount_minor" :currency="$payment->currency" size="lg" class="muted" />
            @endif
        </div>
        @if($payment)
            <dl class="rows">
                <div><dt>{{ $t('رقم المعاملة', 'Transaction ID') }}</dt><dd class="mono ltr">{{ $payment->transaction_id }}</dd></div>
                <div><dt>{{ $t('الحالة', 'Status') }}</dt><dd>
                    <span class="pill {{ $failed ? 'pill-error' : 'pill-neutral' }}">@if($failed)<i class="bi bi-x-circle-fill"></i>@else<span class="dot"></span>@endif{{ $failed ? $t('فشلت', 'Failed') : $t('ملغاة', 'Cancelled') }}</span>
                </dd></div>
            </dl>
        @endif
    </section>

    <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('إن واجهتك مشكلة في الدفع فعُد إلى التطبيق وحاول مرة أخرى، أو تواصل مع الدعم.', 'If something went wrong, go back to the app and try again, or contact support.') }}</div></div>
@endsection
