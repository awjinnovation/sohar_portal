@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
@endphp
@section('title', $t('أُلغي الطلب', 'Order cancelled'))
@section('band-sub', $t('رابط الدفع', 'Payment link'))
@include('payment.box-office._styles')

@section('content')
    <section class="s-card" data-order-status="cancelled">
        <div class="result">
            <div class="result-ico ko"><i class="bi bi-slash-circle"></i></div>
            <h1>{{ $t('أُلغي هذا الطلب', 'This order was cancelled') }}</h1>
            <p>{{ $t('ألغى موظف البوابة الطلب، ولم يُخصم أي مبلغ.', 'The gate staff cancelled this order and nothing was charged.') }}</p>
        </div>
        @include('payment.box-office._summary', ['muted' => true])
    </section>
    <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('اطلب من موظف البوابة طلبًا جديدًا إن رغبت في الشراء.', 'Ask the gate staff for a new order if you still want the tickets.') }}</div></div>
@endsection
