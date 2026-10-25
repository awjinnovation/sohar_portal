@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $seconds = $order->secondsLeft();
@endphp
@section('title', $t('ادفع ثمن تذاكرك', 'Pay for your tickets'))
@section('band-sub', $t('رابط الدفع', 'Payment link'))
@include('payment.box-office._styles')

@section('content')
    @if($notice === 'failed')
        <div class="note error" data-notice="failed"><i class="bi bi-x-circle"></i><div>{{ $t('لم ينجح الدفع ولم يُخصم أي مبلغ. أعد المحاولة أو استعمل بطاقة أخرى.', 'The payment did not go through and nothing was charged. Try again or use another card.') }}</div></div>
    @elseif($notice === 'cancelled')
        <div class="note warn" data-notice="cancelled"><i class="bi bi-info-circle"></i><div>{{ $t('لم يكتمل الدفع ولم يُخصم أي مبلغ. أعد المحاولة بالضغط على زر الدفع.', 'The payment was not completed and nothing was charged. Press the pay button to try again.') }}</div></div>
    @endif

    <div class="bo-timer {{ $seconds <= 120 ? 'is-low' : '' }}" id="boTimer" data-seconds="{{ $seconds }}" role="timer" aria-live="off">
        <i class="bi bi-hourglass-split"></i>
        <span>{{ $t('تنتهي صلاحية الرابط خلال', 'This link expires in') }}</span>
        <b id="boTimerValue">{{ sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) }}</b>
    </div>

    <section class="s-card" data-order-status="pending">
        @include('payment.box-office._summary')
    </section>

    <form method="POST" action="{{ route('box-office.pay.checkout', ['token' => $order->public_token]) }}" class="actions bo-pay">
        @csrf
        <input type="hidden" name="lang" value="{{ $lang }}">
        <button type="submit" class="btn btn-primary" data-pay-button>
            <i class="bi bi-credit-card-2-front"></i>
            <span>{{ $t('ادفع', 'Pay') }}</span>
            <x-admin.money :minor="(int) $order->total_minor" :currency="$order->currency" />
        </button>
        <p class="muted bo-secure"><i class="bi bi-shield-lock"></i> {{ $t('يُدفع المبلغ عبر بوابة الدفع الآمنة، وتصل التذاكر إلى الحساب المرتبط برقم الهاتف أعلاه.', 'You pay through the secure payment gateway. The tickets go to the account of the phone number above.') }}</p>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var box = document.getElementById('boTimer');
    var out = document.getElementById('boTimerValue');
    var end = Date.now() + parseInt(box.getAttribute('data-seconds'), 10) * 1000;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        var left = Math.max(0, Math.round((end - Date.now()) / 1000));
        out.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60);
        if (left <= 120) { box.classList.add('is-low'); }
        if (left === 0) { clearInterval(timer); setTimeout(function () { location.reload(); }, 800); }
    }
    var timer = setInterval(tick, 1000);
    tick();
    document.querySelector('.bo-pay').addEventListener('submit', function () {
        var b = document.querySelector('[data-pay-button]');
        b.disabled = true; b.style.opacity = '.75';
    });
})();
</script>
@endpush
