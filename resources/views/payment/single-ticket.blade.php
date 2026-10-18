@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $event = $ticket->event;
    $tz = $brand?->setting('tenant.timezone') ?: config('app.timezone');
    $start = $event?->start_time?->copy()->setTimezone($tz);
    $venue = $event?->mapLocation ? \App\View\PublicPage::pick($event->mapLocation, 'name', $lang) : \App\View\PublicPage::pick($event, 'location', $lang);
    $statusMap = [
        'active' => ['pill-success', $t('صالحة', 'Valid')], 'used' => ['pill-neutral', $t('مستخدمة', 'Used')],
        'expired' => ['pill-neutral', $t('منتهية', 'Expired')], 'cancelled' => ['pill-error', $t('ملغاة', 'Cancelled')], 'refunded' => ['pill-error', $t('مستردة', 'Refunded')],
    ];
    $isValid = $ticket->status === 'active';
    $usedAt = $ticket->used_at?->copy()->setTimezone($tz);
    [$stClass, $stLabel] = $statusMap[$ticket->status] ?? ['pill-neutral', $ticket->status];
    $typeLabels = ['standard' => $t('عادية', 'Standard'), 'vip' => 'VIP', 'premium' => $t('مميزة', 'Premium'), 'early_bird' => $t('حجز مبكر', 'Early bird')];
@endphp
@section('title', $t('تذكرتك', 'Your ticket'))
@section('band-sub', $t('تذكرة دخول', 'Entry ticket'))

@section('content')
    <article class="ticket" id="ticketCard">
        <div class="ticket-head">
            <span class="pill {{ $isValid ? '' : $stClass }}" data-ticket-status="{{ $ticket->status }}"><span class="dot"></span>{{ $stLabel }}</span>
            <h1 class="ev-title">{{ \App\View\PublicPage::pick($event, 'title', $lang) ?: $t('تذكرة فعالية', 'Event ticket') }}</h1>
            <div class="ev-meta">
                @if($start)
                    <span><i class="bi bi-calendar3"></i> {{ $start->locale($lang)->translatedFormat('l j F') }}</span>
                    <span class="num"><i class="bi bi-clock"></i> {{ $start->format('H:i') }}</span>
                @endif
                @if($venue)<span><i class="bi bi-geo-alt"></i> {{ $venue }}</span>@endif
            </div>
        </div>
        <div class="ticket-perf"><i></i></div>
        <div class="ticket-body">
            <div class="qr-panel" @unless($isValid) style="opacity:.35; filter: grayscale(1)" @endunless aria-label="{{ $t('رمز QR للدخول', 'Entry QR code') }}">
                {!! QrCode::size(300)->margin(2)->errorCorrection('M')->generate($ticket->qr_code) !!}
            </div>
            @if($isValid)
                <div class="qr-caption"><i class="bi bi-brightness-high"></i> {{ $t('ارفع سطوع الشاشة واعرض الرمز عند البوابة', 'Turn up the brightness and show this code at the gate') }}</div>
            @elseif($ticket->status === 'used')
                <div class="qr-caption"><i class="bi bi-check2-circle"></i> {{ $t('تم استخدام هذه التذكرة', 'This ticket has been used') }}@if($usedAt) · <span class="num">{{ $usedAt->locale($lang)->translatedFormat('j F Y') }} {{ $usedAt->format('H:i') }}</span>@endif</div>
            @else
                <div class="qr-caption"><i class="bi bi-x-circle"></i> {{ $t('هذه التذكرة غير صالحة للدخول', 'This ticket is not valid for entry') }}</div>
            @endif
            <div class="qr-code-text mono ltr">{{ $ticket->ticket_number ?: $ticket->qr_code }}</div>

            <dl class="rows">
                @if($ticket->holder_name)
                    <div><dt>{{ $t('حامل التذكرة', 'Ticket holder') }}</dt><dd>{{ $ticket->holder_name }}</dd></div>
                @endif
                <div><dt>{{ $t('نوع التذكرة', 'Ticket type') }}</dt><dd>{{ $typeLabels[$ticket->ticket_type] ?? $ticket->ticket_type }}</dd></div>
                @if($ticket->seat_number)
                    <div><dt>{{ $t('المقعد', 'Seat') }}</dt><dd class="num">{{ $ticket->seat_number }}</dd></div>
                @endif
                <div><dt>{{ $t('السعر', 'Price') }}</dt><dd><x-admin.money :amount="$ticket->price" :currency="$ticket->currency ?: \App\View\Fmt::currency()" /></dd></div>
                @if($ticket->purchase_date)
                    <div><dt>{{ $t('تاريخ الشراء', 'Purchased') }}</dt><dd class="num">{{ $ticket->purchase_date->copy()->setTimezone($tz)->locale($lang)->translatedFormat('j F Y') }}</dd></div>
                @endif
                @if($ticket->transaction_id)
                    <div><dt>{{ $t('رقم العملية', 'Transaction') }}</dt><dd class="mono ltr">{{ $ticket->transaction_id }}</dd></div>
                @endif
            </dl>
        </div>
    </article>

    <div class="actions no-print">
        <button class="btn btn-primary" type="button" id="saveTicket"><i class="bi bi-download"></i> {{ $t('حفظ التذكرة كصورة', 'Save ticket as image') }}</button>
        @if(! empty($downloadUrl))
            <a href="{{ $downloadUrl }}{{ str_contains($downloadUrl, '?') ? '&' : '?' }}lang={{ $lang }}" class="btn btn-tonal"><i class="bi bi-collection"></i> {{ $t('كل تذاكر الطلب', 'All tickets in this order') }}</a>
        @endif
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.getElementById('saveTicket').addEventListener('click', function () {
    var card = document.getElementById('ticketCard');
    if (!window.html2canvas) { window.print(); return; }
    html2canvas(card, { scale: 2, backgroundColor: null, logging: false, useCORS: true }).then(function (canvas) {
        canvas.toBlob(function (blob) {
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = @json(\Illuminate\Support\Str::slug($brand?->slug ?? 'event').'_ticket_'.$ticket->id.'.png');
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
        });
    });
});
</script>
@endpush
