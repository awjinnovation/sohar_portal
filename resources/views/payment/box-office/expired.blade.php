@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
@endphp
@section('title', $t('انتهت صلاحية الرابط', 'Link expired'))
@section('band-sub', $t('رابط الدفع', 'Payment link'))
@include('payment.box-office._styles')

@section('content')
    <section class="s-card" data-order-status="expired">
        <div class="result">
            <div class="result-ico warn"><i class="bi bi-hourglass-bottom"></i></div>
            <h1>{{ $t('انتهت صلاحية رابط الدفع', 'This payment link has expired') }}</h1>
            <p>{{ $t('لم يُدفع الطلب خلال المدة المحددة، فأُلغي حجز التذاكر ولم يُخصم أي مبلغ.', 'The order was not paid in time, so the tickets were released and nothing was charged.') }}</p>
        </div>
        @include('payment.box-office._summary', ['muted' => true])
    </section>
    <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('اطلب من موظف البوابة رابط دفع جديدًا إن رغبت في الشراء.', 'Ask the gate staff for a new payment link if you still want the tickets.') }}</div></div>
@endsection
