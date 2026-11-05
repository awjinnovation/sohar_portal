{{-- Event booking page (Web\VisitorBookingController): sign in with phone + code, book, see and save the tickets. --}}
@extends('layouts.public')
@php
    $isAr = $lang === 'ar';
    $t = fn ($ar, $en) => $isAr ? $ar : $en;
    $title = \App\View\PublicPage::pick($event, 'title', $lang) ?: $t('فعالية', 'Event');
    $tz = $brand?->setting('tenant.timezone') ?: config('app.timezone');
    $start = $event->start_time?->copy()->locale($lang);
    $end = $event->end_time?->copy()->locale($lang);
    $venue = $event->mapLocation ? \App\View\PublicPage::pick($event->mapLocation, 'name', $lang) : \App\View\PublicPage::pick($event, 'location', $lang);
    $currency = $brand?->currency() ?? 'OMR';
    $decimals = $brand?->currencyDecimals() ?? 3;
    $today = \App\Support\TenantTime::today($brand);
    $tomorrow = \Carbon\Carbon::parse($today)->addDay()->toDateString();
    $remaining = $errorData['remaining'] ?? null;
    $messages = [
        // notices
        'otp_sent' => ['info', 'bi-chat-dots', $t('أرسلنا رمز التحقق إلى الرقم '.$otpPhoneMasked.'.', 'We texted a code to '.$otpPhoneMasked.'.')],
        'signed_in' => ['info', 'bi-person-check', $t('تم تسجيل الدخول. اختر تذاكرك.', 'You are signed in. Choose your tickets.')],
        'payment_success' => ['info', 'bi-check-circle', $t('اكتمل الدفع. تذاكرك بالأسفل.', 'Payment complete. Your tickets are below.')],
        'payment_cancelled' => ['warn', 'bi-info-circle', $t('لم يكتمل الدفع ولم يُخصم أي مبلغ.', 'The payment was not completed and nothing was charged.')],
        'payment_failed' => ['error', 'bi-x-circle', $t('لم ينجح الدفع ولم يُخصم أي مبلغ. أعد المحاولة أو استعمل بطاقة أخرى.', 'The payment did not go through and nothing was charged. Try again or use another card.')],
        // errors
        'invalid_phone' => ['error', 'bi-exclamation-circle', $t('أدخل رقم هاتف عُماني صحيحًا من 8 أرقام.', 'Enter a valid 8-digit Omani mobile number.')],
        'otp_invalid' => ['error', 'bi-exclamation-circle', $t('الرمز غير صحيح.', 'That code is not right.').($remaining !== null ? ' '.$t('المحاولات المتبقية: ', 'Attempts left: ').$remaining : '')],
        'otp_expired' => ['error', 'bi-clock-history', $t('انتهت صلاحية الرمز. اطلب رمزًا جديدًا.', 'The code has expired. Ask for a new one.')],
        'otp_attempts' => ['error', 'bi-shield-exclamation', $t('محاولات خاطئة كثيرة. اطلب رمزًا جديدًا.', 'Too many wrong codes. Ask for a new one.')],
        'throttled' => ['warn', 'bi-hourglass-split', $t('محاولات كثيرة. انتظر بضع دقائق ثم أعد المحاولة.', 'Too many attempts. Wait a few minutes and try again.')],
        'items_required' => ['error', 'bi-exclamation-circle', $t('اختر تذكرة واحدة على الأقل.', 'Choose at least one ticket.')],
        'date_required' => ['error', 'bi-calendar-x', $t('اختر يوم الزيارة.', 'Choose the day of your visit.')],
        'date_invalid' => ['error', 'bi-calendar-x', $t('هذا اليوم غير متاح.', 'That day is not available.')],
        'sold_out' => ['error', 'bi-x-octagon', $t('لا تكفي التذاكر المتبقية لهذا الطلب.', 'Not enough tickets are left for this order.').(isset($errorData['available']) ? ' '.$t('المتبقي: ', 'Left: ').$errorData['available'] : '')],
        'per_order_limit' => ['error', 'bi-exclamation-circle', $t('عدد التذاكر أكبر من المسموح في الطلب الواحد', 'Too many tickets in one order').(isset($errorData['per_order_max']) ? ' ('.$t('الحد ', 'max ').$errorData['per_order_max'].')' : '').'.'],
        'sales_closed' => ['error', 'bi-lock', $t('البيع مغلق لهذا النوع من التذاكر.', 'Sales are closed for this ticket type.')],
        'type_not_available' => ['error', 'bi-x-circle', $t('هذا النوع من التذاكر غير متاح.', 'This ticket type is not available.')],
    ];
    $unavailable = ['error', 'bi-x-circle', $t('الحجز غير متاح الآن. أعد المحاولة لاحقًا.', 'Booking is not available right now. Try again later.')];
    $notes = array_filter([$notice ? ($messages[$notice] ?? null) : null, $error ? ($messages[$error] ?? $unavailable) : null]);
    $dayLabel = fn (string $d) => $d === $today ? $t('اليوم', 'Today') : ($d === $tomorrow ? $t('غدًا', 'Tomorrow') : \Carbon\Carbon::parse($d)->locale($lang)->translatedFormat('l'));
    $onSale = $types->filter(fn ($type) => $type->salesOpen());
    $statusLabels = [
        'active' => ['pill-success', $t('صالحة', 'Valid')], 'used' => ['pill-neutral', $t('مستخدمة', 'Used')],
        'expired' => ['pill-neutral', $t('منتهية', 'Expired')], 'cancelled' => ['pill-error', $t('ملغاة', 'Cancelled')], 'refunded' => ['pill-error', $t('مستردة', 'Refunded')],
    ];
@endphp
@section('title', $title)
@section('band-sub', $t('احجز تذكرتك', 'Book your ticket'))
@include('visitor._styles')

@section('content')
    <section class="s-card vb-event">
        <span class="vb-event-ico"><i class="bi bi-ticket-perforated"></i></span>
        <div class="vb-event-meta">
            <h1 class="vb-event-title">{{ $title }}</h1>
            <div class="muted vb-event-sub">
                @if($start)
                    <span><i class="bi bi-calendar3"></i>
                        {{ $start->translatedFormat('j F') }}@if($end && $end->toDateString() !== $start->toDateString()) – {{ $end->translatedFormat('j F') }}@endif
                    </span>
                    <span class="num"><i class="bi bi-clock"></i> {{ $start->format('H:i') }}@if($end) – {{ $end->format('H:i') }}@endif</span>
                @endif
                @if($venue)<span><i class="bi bi-geo-alt"></i> {{ $venue }}</span>@endif
            </div>
        </div>
    </section>

    @foreach($notes as [$tone, $icon, $text])
        <div class="note {{ $tone }}" data-visitor-message><i class="bi {{ $icon }}"></i><div>{{ $text }}</div></div>
    @endforeach

    @if($tickets->isNotEmpty())
        <h2 class="vb-section-title"><i class="bi bi-qr-code"></i> {{ $t('تذاكرك', 'Your tickets') }} <span class="muted num">({{ $tickets->count() }})</span></h2>
        <div data-visitor-tickets="{{ $tickets->count() }}">
            @foreach($tickets as $ticket)
                @php
                    $type = $ticket->ticketType;
                    [$stClass, $stLabel] = $statusLabels[$ticket->status] ?? ['pill-neutral', $ticket->status];
                    $valid = $ticket->status === 'active';
                    $admits = $ticket->admits();
                    $when = $ticket->booking_date
                        ? $ticket->booking_date->copy()->locale($lang)->translatedFormat('l j F Y')
                        : \App\Models\EventTicketType::validityLabel($ticket->validityValue(), $lang);
                @endphp
                <article class="s-card vb-ticket" id="ticket-{{ $ticket->id }}" data-ticket-status="{{ $ticket->status }}">
                    <div class="vb-ticket-head">
                        <span class="vb-badge" style="--type-color: {{ $type?->colorValue() ?? \App\Models\EventTicketType::CATEGORY_COLORS['general'] }}"><i class="bi bi-ticket-perforated"></i>{{ $type ? $type->nameText($lang) : $ticket->ticket_type }}</span>
                        <span class="pill {{ $valid ? 'pill-success' : $stClass }}">{{ $stLabel }}</span>
                    </div>
                    <div class="vb-ticket-event">{{ $title }}</div>
                    <div class="muted vb-ticket-when"><i class="bi bi-calendar3"></i> {{ $when }}</div>
                    <div class="qr-panel" @unless($valid) style="opacity:.35; filter: grayscale(1)" @endunless aria-label="{{ $t('رمز QR للدخول', 'Entry QR code') }}">
                        {!! QrCode::size(240)->margin(1)->errorCorrection('M')->generate($ticket->qr_code) !!}
                    </div>
                    <div class="vb-ticket-foot">
                        <span class="mono ltr">{{ $ticket->ticket_number ?: $ticket->qr_code }}</span>
                        @if($admits > 1)
                            <span class="pill pill-brand"><i class="bi bi-people"></i>{{ $t('يدخل بها', 'Admits') }} {{ trans_choice('gate.people', $admits, ['count' => $admits], $lang) }}</span>
                        @endif
                        @if($type?->requires_id)
                            <span class="pill pill-warning"><i class="bi bi-person-vcard"></i>{{ $t('تُطلب الهوية', 'ID required') }}</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn-tonal vb-save" data-save-ticket="ticket-{{ $ticket->id }}"
                            data-file="{{ \Illuminate\Support\Str::slug($brand?->localizedName('en') ?: 'event') }}_ticket_{{ $ticket->ticket_number ?: $ticket->id }}.png">
                        <i class="bi bi-download"></i> {{ $t('حفظ التذكرة صورةً', 'Save ticket as image') }}
                    </button>
                </article>
            @endforeach
        </div>
    @endif

    @if(! $visitor)
        <section class="s-card" data-visitor-step="{{ $otpPhone ? 'otp' : 'phone' }}">
            @if(! $otpPhone)
                <h2><i class="bi bi-phone"></i> {{ $t('رقم هاتفك', 'Your phone number') }}</h2>
                <p class="muted vb-lead">{{ $t('نرسل لك رمزًا للتحقق. لا حاجة لإنشاء حساب.', 'We will text you a code. No account needed.') }}</p>
                <form method="POST" action="{{ route('visitor.otp.send', ['event' => $event->id]) }}" class="vb-form" data-busy-form>
                    @csrf
                    <input type="hidden" name="lang" value="{{ $lang }}">
                    <label class="vb-phone">
                        <span class="vb-cc num">+{{ $countryCode }}</span>
                        <input type="tel" name="phone_number" value="{{ old('phone_number') }}" inputmode="numeric" autocomplete="tel-national"
                               placeholder="{{ $phoneHint }}" maxlength="15" required aria-label="{{ $t('رقم الهاتف', 'Phone number') }}">
                    </label>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> {{ $t('أرسل الرمز', 'Send code') }}</button>
                </form>
            @else
                <h2><i class="bi bi-shield-lock"></i> {{ $t('رمز التحقق', 'Verification code') }}</h2>
                <p class="muted vb-lead">{{ $t('أدخل الرمز المكون من 6 أرقام المرسل إلى', 'Enter the 6-digit code sent to') }} <span class="ltr num">{{ $otpPhoneMasked }}</span></p>
                <form method="POST" action="{{ route('visitor.otp.verify', ['event' => $event->id]) }}" class="vb-form" data-busy-form>
                    @csrf
                    <input type="hidden" name="lang" value="{{ $lang }}">
                    <input type="hidden" name="phone_number" value="{{ $otpPhone }}">
                    <input type="text" name="otp" class="vb-otp num" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                           maxlength="6" required autofocus placeholder="••••••" aria-label="{{ $t('رمز التحقق', 'Verification code') }}">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> {{ $t('تحقق وتابع', 'Verify and continue') }}</button>
                </form>
                <div class="vb-links">
                    <form method="POST" action="{{ route('visitor.otp.send', ['event' => $event->id]) }}">
                        @csrf
                        <input type="hidden" name="lang" value="{{ $lang }}">
                        <input type="hidden" name="phone_number" value="{{ $otpPhone }}">
                        <button type="submit" class="vb-link"><i class="bi bi-arrow-repeat"></i> {{ $t('أعد إرسال الرمز', 'Resend code') }}</button>
                    </form>
                    <form method="POST" action="{{ route('visitor.logout', ['event' => $event->id]) }}">
                        @csrf
                        <input type="hidden" name="lang" value="{{ $lang }}">
                        <button type="submit" class="vb-link"><i class="bi bi-pencil"></i> {{ $t('غيّر الرقم', 'Change number') }}</button>
                    </form>
                </div>
            @endif
        </section>
    @elseif($ended)
        <div class="note warn" data-booking="ended"><i class="bi bi-calendar-x"></i><div>{{ $t('انتهت هذه الفعالية.', 'This event has ended.') }}</div></div>
    @elseif($onSale->isEmpty())
        <div class="note warn" data-booking="closed"><i class="bi bi-lock"></i><div>{{ $t('لا تُباع تذاكر هذه الفعالية عبر الإنترنت حاليًا.', 'Tickets for this event are not on sale online right now.') }}</div></div>
    @else
        <form method="POST" action="{{ route('visitor.checkout', ['event' => $event->id]) }}" class="s-card vb-book" id="vbBook" data-busy-form data-decimals="{{ $decimals }}">
            @csrf
            <input type="hidden" name="lang" value="{{ $lang }}">
            <input type="hidden" name="form_key" value="{{ $formKey }}">
            <h2><i class="bi bi-cart"></i> {{ $tickets->isNotEmpty() ? $t('احجز تذاكر أخرى', 'Book more tickets') : $t('اختر تذاكرك', 'Choose your tickets') }}</h2>

            @if($dates)
                <div class="vb-label">{{ $t('يوم الزيارة', 'Day of your visit') }}</div>
                <div class="vb-dates" role="radiogroup">
                    @foreach($dates as $d)
                        @php $soldOut = $d['available'] === 0; @endphp
                        <label class="vb-date {{ $soldOut ? 'is-off' : '' }}">
                            <input type="radio" name="booking_date" value="{{ $d['date'] }}" @checked($selectedDate === $d['date']) @disabled($soldOut)>
                            <span class="vb-date-day">{{ $dayLabel($d['date']) }}</span>
                            <span class="vb-date-num num">{{ \Carbon\Carbon::parse($d['date'])->locale($lang)->translatedFormat('j M') }}</span>
                            @if($soldOut)<span class="vb-date-left">{{ $t('نفدت', 'Sold out') }}</span>@endif
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="vb-types">
                @foreach($types as $type)
                    @php
                        $max = max(1, min(10, (int) $type->per_order_max));
                        $open = $type->salesOpen();
                        $qty = (int) old('items.'.$type->id, 0);
                    @endphp
                    <div class="vb-type {{ $open ? '' : 'is-off' }}" data-price="{{ (int) $type->price_minor }}">
                        <div class="vb-type-meta">
                            <div class="vb-type-name"><span class="vb-dot" style="background: {{ $type->colorValue() }}"></span>{{ $type->nameText($lang) }}</div>
                            <div class="muted vb-type-sub">
                                @if((int) $type->price_minor === 0)
                                    {{ $t('مجانية', 'Free') }}
                                @else
                                    <x-admin.money :minor="(int) $type->price_minor" :currency="$currency" />
                                @endif
                                @unless($type->isDated()) · {{ \App\Models\EventTicketType::validityLabel($type->validityValue(), $lang) }}@endunless
                                @if(max(1, (int) $type->admits) > 1) · {{ $t('يدخل بها', 'Admits') }} {{ trans_choice('gate.people', (int) $type->admits, ['count' => (int) $type->admits], $lang) }}@endif
                                @unless($open) · {{ $t('البيع مغلق', 'Sales closed') }}@endunless
                            </div>
                        </div>
                        <div class="vb-stepper">
                            <button type="button" class="vb-step" data-step="-1" aria-label="{{ $t('أقل', 'Less') }}" @disabled(! $open)><i class="bi bi-dash"></i></button>
                            <input type="number" name="items[{{ $type->id }}]" value="{{ $qty }}" min="0" max="{{ $max }}" inputmode="numeric" class="num" @disabled(! $open)
                                   aria-label="{{ $t('عدد التذاكر', 'Number of tickets') }}: {{ $type->nameText($lang) }}">
                            <button type="button" class="vb-step" data-step="1" aria-label="{{ $t('أكثر', 'More') }}" @disabled(! $open)><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="vb-total">
                <span>{{ $t('الإجمالي', 'Total') }}</span>
                <span id="vbTotal"><x-admin.money :minor="0" :currency="$currency" /></span>
            </div>
            <button type="submit" class="btn btn-primary vb-pay" id="vbPay" data-label-pay="{{ $t('ادفع بالبطاقة', 'Pay by card') }}" data-label-free="{{ $t('احصل على التذاكر', 'Get the tickets') }}">
                <i class="bi bi-credit-card-2-front"></i> <span>{{ $t('ادفع بالبطاقة', 'Pay by card') }}</span>
            </button>
            <p class="muted vb-secure"><i class="bi bi-shield-lock"></i> {{ $t('يتم الدفع عبر بوابة الدفع الآمنة. تجد تذاكرك هنا وفي التطبيق عند الدخول برقم هاتفك.', 'You pay through the secure payment gateway. Your tickets show here, and in the app when you sign in with your number.') }}</p>
        </form>
    @endif

    @if($visitor)
        <form method="POST" action="{{ route('visitor.logout', ['event' => $event->id]) }}" class="vb-who">
            @csrf
            <input type="hidden" name="lang" value="{{ $lang }}">
            <span class="muted"><i class="bi bi-person-circle"></i> {{ $t('مسجّل برقم', 'Signed in as') }} <span class="ltr num">{{ $visitorPhone }}</span></span>
            <button type="submit" class="vb-link">{{ $t('تسجيل الخروج', 'Sign out') }}</button>
        </form>
    @endif
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function () {
    // Double submits: disable the form's button once sent.
    document.querySelectorAll('[data-busy-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var b = form.querySelector('button[type=submit]');
            if (b) { setTimeout(function () { b.disabled = true; b.style.opacity = '.75'; }, 0); }
        });
    });

    // Quantity steppers + live total (the server prices the order again).
    var book = document.getElementById('vbBook');
    if (book) {
        var decimals = parseInt(book.getAttribute('data-decimals'), 10) || 0;
        var pay = document.getElementById('vbPay');
        var total = function () {
            var minor = 0, count = 0;
            book.querySelectorAll('.vb-type').forEach(function (row) {
                var input = row.querySelector('input');
                var q = Math.max(0, parseInt(input.value, 10) || 0);
                count += q;
                minor += q * (parseInt(row.getAttribute('data-price'), 10) || 0);
            });
            var amount = (minor / Math.pow(10, decimals)).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            var money = document.querySelector('#vbTotal .money');
            var cur = money.querySelector('.cur');
            money.innerHTML = '';
            money.appendChild(cur);
            money.appendChild(document.createTextNode(amount));
            pay.disabled = count === 0;
            pay.style.opacity = count === 0 ? '.6' : '';
            pay.querySelector('span').textContent = count > 0 && minor === 0 ? pay.getAttribute('data-label-free') : pay.getAttribute('data-label-pay');
        };
        book.querySelectorAll('.vb-type').forEach(function (row) {
            var input = row.querySelector('input');
            row.querySelectorAll('[data-step]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var max = parseInt(input.max, 10) || 10;
                    var v = (parseInt(input.value, 10) || 0) + parseInt(btn.getAttribute('data-step'), 10);
                    input.value = Math.min(max, Math.max(0, v));
                    total();
                });
            });
            input.addEventListener('input', total);
        });
        total();
    }

    // Save a ticket card as a PNG.
    document.querySelectorAll('[data-save-ticket]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = document.getElementById(btn.getAttribute('data-save-ticket'));
            if (!window.html2canvas) { window.print(); return; }
            btn.style.visibility = 'hidden';
            html2canvas(card, { scale: 2, backgroundColor: '#ffffff', logging: false, useCORS: true }).then(function (canvas) {
                btn.style.visibility = '';
                canvas.toBlob(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = btn.getAttribute('data-file');
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                });
            });
        });
    });
})();
</script>
@endpush
