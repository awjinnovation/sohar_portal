@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
@endphp
@section('title', 'Test payment')
@section('band-sub', $t('بوابة دفع تجريبية', 'Test payment gateway'))

@section('content')
    <div class="note warn"><i class="bi bi-cone-striped"></i><div><b>{{ $t('وضع الاختبار', 'Test mode') }}</b> — {{ $t('لن يتم خصم أي مبلغ حقيقي. اختر النتيجة لمحاكاتها.', 'No real money is charged. Choose the outcome to simulate.') }}</div></div>

    <section class="s-card">
        <div class="result">
            <div class="result-ico info"><i class="bi bi-credit-card-2-front"></i></div>
            <h1>{{ $payment->payment_type === 'topup' ? $t('شحن المحفظة', 'Wallet top-up') : $t('شراء تذاكر', 'Ticket purchase') }}</h1>
            <x-admin.money :minor="(int) $payment->amount_minor" :currency="$payment->currency" size="hero" />
        </div>
        <dl class="rows">
            <div><dt>{{ $t('رقم العملية', 'Transaction ID') }}</dt><dd class="mono ltr">{{ $payment->transaction_id }}</dd></div>
        </dl>
    </section>

    <form method="POST" action="{{ $completeUrl }}" class="actions">
        @csrf
        <button class="btn btn-primary" name="result" value="success" type="submit"><i class="bi bi-check-lg"></i> Simulate success @if($isAr)<span class="muted" style="color:inherit;opacity:.8">· نجاح</span>@endif</button>
        <button class="btn btn-danger" name="result" value="failure" type="submit"><i class="bi bi-x-lg"></i> Simulate failure @if($isAr)<span style="opacity:.8">· فشل</span>@endif</button>
        <button class="btn btn-neutral" name="result" value="cancel" type="submit">Cancel @if($isAr)<span style="opacity:.8">· إلغاء</span>@endif</button>
    </form>
@endsection
