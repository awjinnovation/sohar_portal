{{-- Order summary of a gate box office order (pending / paid / expired / cancelled pages). Needs $order, $event, $lang, $maskedPhone. --}}
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $bookingDate = $order->booking_date?->copy()->locale($lang);
    $venue = $event?->mapLocation ? \App\View\PublicPage::pick($event->mapLocation, 'name', $lang) : \App\View\PublicPage::pick($event, 'location', $lang);
    $muted = $muted ?? false;
@endphp
<div class="bo-event">
    <span class="bo-event-ico"><i class="bi bi-ticket-perforated"></i></span>
    <div class="bo-event-meta">
        <div class="bo-event-title">{{ \App\View\PublicPage::pick($event, 'title', $lang) ?: $t('فعالية', 'Event') }}</div>
        <div class="muted bo-event-sub">
            @if($bookingDate)<span><i class="bi bi-calendar3"></i> {{ $bookingDate->translatedFormat('l j F Y') }}</span>@endif
            @if($venue)<span><i class="bi bi-geo-alt"></i> {{ $venue }}</span>@endif
        </div>
    </div>
</div>

<dl class="rows bo-lines" data-lines="{{ $order->lines->count() }}">
    @foreach($order->lines as $line)
        <div>
            <dt>
                <span class="bo-type-name">{{ \App\Models\EventTicketType::pickLocale($line->name, $lang) ?: $line->key }}</span>
                <span class="muted num bo-type-qty">× {{ $line->quantity }}</span>
            </dt>
            <dd><x-admin.money :minor="(int) $line->line_total_minor" :currency="$order->currency" /></dd>
        </div>
    @endforeach
    <div class="bo-total">
        <dt>{{ $t('الإجمالي', 'Total') }}</dt>
        <dd><x-admin.money :minor="(int) $order->total_minor" :currency="$order->currency" class="{{ $muted ? 'muted' : '' }}" /></dd>
    </div>
</dl>

<dl class="rows bo-facts">
    <div><dt>{{ $t('رقم الهاتف', 'Phone number') }}</dt><dd class="ltr num">{{ $maskedPhone }}</dd></div>
    <div><dt>{{ $t('رقم الطلب', 'Order number') }}</dt><dd class="mono ltr">{{ $order->reference }}</dd></div>
</dl>
