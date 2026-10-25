@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $refunded = $order->status === \App\Models\BoxOfficeOrder::STATUS_REFUNDED;
@endphp
@section('title', $refunded ? $t('أُعيد المبلغ', 'Payment refunded') : $t('سيُعاد المبلغ', 'Your payment will be refunded'))
@section('band-sub', $t('رابط الدفع', 'Payment link'))
@include('payment.box-office._styles')

@section('content')
    <section class="s-card" data-order-status="{{ $order->status }}">
        <div class="result">
            <div class="result-ico {{ $refunded ? 'info' : 'warn' }}"><i class="bi bi-{{ $refunded ? 'arrow-counterclockwise' : 'exclamation-lg' }}"></i></div>
            @if($refunded)
                <h1>{{ $t('أُعيد المبلغ', 'Payment refunded') }}</h1>
                <p>{{ $t('استردّ المنظِّم مبلغ هذا الطلب إلى وسيلة الدفع التي استعملتها.', 'The organizer refunded this order to the payment method you used.') }}</p>
            @else
                <h1>{{ $t('لم تصدر التذاكر', 'No tickets were issued') }}</h1>
                <p>{{ $t('وصل الدفع بعد انتهاء صلاحية الطلب، ونفدت التذاكر في هذه الأثناء، فلم تصدر تذاكر وسيُعاد المبلغ كاملًا إلى وسيلة الدفع.', 'Your payment arrived after the order had expired and the tickets had sold out meanwhile, so no tickets were issued and the full amount will be refunded to your payment method.') }}</p>
            @endif
        </div>
        @include('payment.box-office._summary', ['muted' => true])
    </section>
    <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('للاستفسار عن الاسترداد اذكر رقم الطلب لموظف البوابة أو لفريق الدعم.', 'For questions about the refund, give the order number to the gate staff or to support.') }}</div></div>
@endsection
