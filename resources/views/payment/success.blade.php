@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $isTicket = $payment->payment_type === 'ticket';
    $paidAt = $payment->paid_at ?? $payment->updated_at;
    $tz = $brand?->setting('tenant.timezone') ?: config('app.timezone');
    $tickets = collect($tickets ?? []);
    $pointsEarned = (int) ($pointsEarned ?? 0);
    $qty = $tickets->isNotEmpty() ? $tickets->count() : (int) ($payment->metadata['quantity'] ?? 1);
    $pointsLabel = $brand ? (string) ($brand->setting('points.label.'.$lang) ?: $t('نقاط', 'Points')) : $t('نقاط', 'Points');
@endphp
@section('title', $t('اكتمل الدفع', 'Payment successful'))
@section('band-sub', $t('إيصال الدفع', 'Payment receipt'))

@section('content')
    <section class="s-card">
        <div class="result">
            <div class="result-ico ok"><i class="bi bi-check-lg"></i></div>
            <h1>{{ $t('اكتمل الدفع', 'Payment successful') }}</h1>
            <p>{{ $isTicket ? $t('تأكّد حجزك. ستجد تذاكرك في التطبيق، ويمكنك تنزيلها من هنا.', 'Your booking is confirmed. Your tickets are in the app and can be downloaded here.') : $t('أُضيف المبلغ إلى رصيد محفظتك.', 'The amount has been added to your wallet balance.') }}</p>
            <x-admin.money :minor="(int) $payment->amount_minor" :currency="$payment->currency" size="lg" />
        </div>

        <dl class="rows">
            <div><dt>{{ $t('رقم المعاملة', 'Transaction ID') }}</dt><dd class="mono ltr">{{ $transactionId }}</dd></div>
            <div><dt>{{ $t('النوع', 'Type') }}</dt><dd>{{ $isTicket ? $t('شراء تذاكر', 'Ticket purchase') : $t('شحن المحفظة', 'Wallet top-up') }}</dd></div>
            @if($isTicket)
                <div><dt>{{ $t('عدد التذاكر', 'Tickets') }}</dt><dd class="num">{{ $qty }}</dd></div>
            @endif
            @if($paidAt)
                <div><dt>{{ $t('التاريخ', 'Date') }}</dt><dd><span class="num">{{ $paidAt->copy()->setTimezone($tz)->locale($lang)->translatedFormat('j F Y') }} · {{ $paidAt->copy()->setTimezone($tz)->format('H:i') }}</span></dd></div>
            @endif
            <div><dt>{{ $t('طريقة الدفع', 'Method') }}</dt><dd>{{ $payment->method === 'wallet' ? $t('المحفظة', 'Wallet') : $t('بطاقة', 'Card') }}</dd></div>
            @if($pointsEarned > 0)
                <div><dt>{{ $t('النقاط المكتسبة', 'Points earned') }}</dt><dd><span class="pill pill-success" data-points-earned="{{ $pointsEarned }}"><i class="bi bi-stars"></i><span class="num">+{{ number_format($pointsEarned) }}</span> {{ $pointsLabel }}</span></dd></div>
            @endif
            <div><dt>{{ $t('الحالة', 'Status') }}</dt><dd><span class="pill pill-success"><span class="dot"></span>{{ $t('مكتملة', 'Completed') }}</span></dd></div>
        </dl>

        @if($tickets->isNotEmpty())
            <h2 style="font-size: 15px; margin: 18px 0 8px">{{ $t('التذاكر الصادرة', 'Tickets issued') }}</h2>
            <dl class="rows" data-tickets-issued="{{ $tickets->count() }}">
                @foreach($tickets as $issued)
                    <div>
                        <dt>
                            {{ \App\View\PublicPage::pick($issued->event, 'title', $lang) ?: $t('تذكرة', 'Ticket') }}
                            @if($issued->booking_date)<span class="muted num" style="display:block; font-size: 12.5px">{{ $issued->booking_date->locale($lang)->translatedFormat('j F Y') }}</span>@endif
                        </dt>
                        <dd><a href="{{ route('payment.ticket.single', $issued->qr_code) }}?lang={{ $lang }}" class="mono ltr">{{ $issued->ticket_number ?: \Illuminate\Support\Str::limit($issued->qr_code, 16) }}</a></dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <div class="actions">
        @if(! empty($downloadUrl))
            <a href="{{ $downloadUrl }}{{ str_contains($downloadUrl, '?') ? '&' : '?' }}lang={{ $lang }}" class="btn btn-primary"><i class="bi bi-ticket-perforated"></i> {{ $t('عرض التذاكر وتنزيلها', 'View & download tickets') }}</a>
        @endif
        <p class="muted" style="text-align:center; margin: 8px 0 0; font-size: 13.5px">{{ $t('يمكنك إغلاق هذه الصفحة والعودة إلى التطبيق.', 'You can close this page and return to the app.') }}</p>
    </div>
@endsection
