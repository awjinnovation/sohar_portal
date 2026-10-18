@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $hideLangSwitch = true;
@endphp
@section('title', $t('جارٍ التحويل إلى بوابة الدفع', 'Redirecting to payment'))
@section('band-sub', $t('دفع آمن', 'Secure payment'))

@section('content')
    <section class="s-card">
        <div class="result">
            <div class="spinner" role="status" aria-label="{{ $t('جارٍ التحميل', 'Loading') }}"></div>
            <h1>{{ $t('جارٍ التحويل إلى بوابة الدفع', 'Redirecting to the payment gateway') }}</h1>
            <p>{{ $t('يرجى الانتظار، سيتم نقلك لإتمام الدفع بأمان…', 'Please wait while we take you to complete your payment securely…') }}</p>
        </div>
        <form method="post" name="redirect" action="{{ $gatewayUrl }}" id="payment-form">
            <input type="hidden" name="encRequest" value="{{ $encRequest }}">
            <input type="hidden" name="access_code" value="{{ $accessCode }}">
            <noscript><button type="submit" class="btn btn-primary">{{ $t('متابعة الدفع', 'Continue to payment') }}</button></noscript>
        </form>
    </section>
    <p class="muted" style="text-align:center; font-size: 13px"><i class="bi bi-lock-fill"></i> {{ $t('اتصال مشفّر', 'Encrypted connection') }}</p>
@endsection

@push('scripts')
<script>
    setTimeout(function () { document.getElementById('payment-form').submit(); }, 1000);
</script>
@endpush
