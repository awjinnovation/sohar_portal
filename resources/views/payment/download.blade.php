@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $tz = $brand?->setting('tenant.timezone') ?: config('app.timezone');
@endphp
@section('title', $t('تذاكرك', 'Your tickets'))
@section('band-sub', $t('تذاكر الطلب', 'Order tickets'))

@section('content')
    <section class="s-card">
        <h2><i class="bi bi-receipt"></i> {{ $t('ملخص الطلب', 'Order summary') }}</h2>
        <dl class="rows">
            <div><dt>{{ $t('رقم العملية', 'Transaction ID') }}</dt><dd class="mono ltr">{{ $payment->transaction_id }}</dd></div>
            <div><dt>{{ $t('المبلغ المدفوع', 'Amount paid') }}</dt><dd><x-admin.money :minor="(int) $payment->amount_minor" :currency="$payment->currency" /></dd></div>
            <div><dt>{{ $t('عدد التذاكر', 'Tickets') }}</dt><dd class="num">{{ $tickets->count() }}</dd></div>
            @if($payment->user?->name)
                <div><dt>{{ $t('الاسم', 'Name') }}</dt><dd>{{ $payment->user->name }}</dd></div>
            @endif
        </dl>
    </section>

    @forelse($tickets as $index => $ticket)
        @php $start = $ticket->event?->start_time?->copy()->setTimezone($tz); @endphp
        <a href="{{ route('payment.ticket.single', ['qrCode' => $ticket->qr_code, 'lang' => $lang]) }}" class="s-card ticket-mini" style="text-decoration:none; color: inherit; display:flex">
            <div class="qr-panel">{!! QrCode::size(120)->margin(1)->generate($ticket->qr_code) !!}</div>
            <div style="flex:1; min-width:0">
                <div class="s">{{ $t('تذكرة', 'Ticket') }} <span class="num">#{{ $index + 1 }}</span></div>
                <div class="t">{{ \App\View\PublicPage::pick($ticket->event, 'title', $lang) }}</div>
                @if($start)<div class="s num">{{ $start->locale($lang)->translatedFormat('j F') }} · {{ $start->format('H:i') }}</div>@endif
                <div style="margin-top:6px"><span class="pill {{ $ticket->status === 'active' ? 'pill-success' : 'pill-neutral' }}"><span class="dot"></span>{{ $ticket->status === 'active' ? $t('صالحة', 'Valid') : $t('مستخدمة', 'Used') }}</span></div>
            </div>
            <i class="bi bi-chevron-left dir-icon muted"></i>
        </a>
    @empty
        <div class="note info"><i class="bi bi-info-circle"></i><div>{{ $t('لا توجد تذاكر لهذا الطلب.', 'No tickets were found for this order.') }}</div></div>
    @endforelse
@endsection
