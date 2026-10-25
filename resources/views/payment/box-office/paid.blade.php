@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $appName = $brand?->localizedName($lang);
@endphp
@section('title', $t('اكتمل الدفع', 'Payment complete'))
@section('band-sub', $t('تذاكرك', 'Your tickets'))
@include('payment.box-office._styles')

@section('content')
    <section class="s-card" data-order-status="paid">
        <div class="result">
            <div class="result-ico ok"><i class="bi bi-check-lg"></i></div>
            <h1>{{ $t('اكتمل الدفع', 'Payment complete') }}</h1>
            <p>{{ $t('تأكّد طلبك. اعرض رمز كل تذكرة عند البوابة.', 'Your order is confirmed. Show each ticket code at the gate.') }}</p>
        </div>
        @include('payment.box-office._summary')
    </section>

    <div class="note info" data-app-hint>
        <i class="bi bi-phone"></i>
        <div>{{ $t('سجّل الدخول إلى تطبيق', 'Sign in to the') }} {{ $appName }} {{ $t('برقم الهاتف', 'app with') }} <span class="ltr num">{{ $maskedPhone }}</span> {{ $t('لتجد تذاكرك فيه.', 'to find your tickets there.') }}</div>
    </div>

    <div data-tickets-issued="{{ $tickets->count() }}">
        @foreach($tickets as $index => $ticket)
            @php
                $type = $ticket->ticketType;
                $typeName = $type ? $type->nameText($lang) : $ticket->ticket_type;
                $admits = $ticket->admits();
            @endphp
            <article class="s-card bo-ticket">
                <div class="bo-ticket-head">
                    <span class="bo-badge" style="--type-color: {{ $type?->colorValue() ?? \App\Models\EventTicketType::CATEGORY_COLORS['general'] }}"><i class="bi bi-ticket-perforated"></i>{{ $typeName }}</span>
                    <span class="muted num">{{ $t('تذكرة', 'Ticket') }} {{ $index + 1 }}/{{ $tickets->count() }}</span>
                </div>
                <div class="qr-panel" aria-label="{{ $t('رمز QR للدخول', 'Entry QR code') }}">{!! QrCode::size(240)->margin(1)->errorCorrection('M')->generate($ticket->qr_code) !!}</div>
                <div class="bo-ticket-foot">
                    <a href="{{ route('payment.ticket.single', ['qrCode' => $ticket->qr_code, 'lang' => $lang]) }}" class="mono ltr">{{ $ticket->ticket_number }}</a>
                    @if($admits > 1)
                        <span class="pill pill-brand"><i class="bi bi-people"></i>{{ $t('يدخل بها', 'Admits') }} {{ trans_choice('gate.people', $admits, ['count' => $admits], $lang) }}</span>
                    @endif
                    @if($type?->requires_id)
                        <span class="pill pill-warning"><i class="bi bi-person-vcard"></i>{{ $t('تُطلب الهوية', 'ID required') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="actions">
        @if($downloadUrl)
            <a href="{{ $downloadUrl }}{{ str_contains($downloadUrl, '?') ? '&' : '?' }}lang={{ $lang }}" class="btn btn-tonal"><i class="bi bi-collection"></i> {{ $t('عرض التذاكر وتنزيلها', 'View & download tickets') }}</a>
        @endif
    </div>
@endsection
