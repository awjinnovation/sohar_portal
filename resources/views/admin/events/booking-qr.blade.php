{{-- Printable booking poster of an event (Admin\EventBookingQrController): scan → /e/{event}. A4, light colours only. --}}
@php
    $tt = \App\View\TenantTheme::for($tenant);
    $name = $tenant?->localizedName('ar') ?? config('app.name');
    $nameEn = $tenant?->localizedName('en');
    $logo = $tt['logo_on_primary'] ?? $tt['logo'];
    $titleAr = $event->title_ar ?: $event->title;
    $titleEn = $event->title && $event->title !== $titleAr ? $event->title : null;
    $start = $event->start_time?->copy()->locale('ar');
    $end = $event->end_time?->copy()->locale('ar');
    $venue = $event->mapLocation ? \App\View\PublicPage::pick($event->mapLocation, 'name', 'ar') : ($event->location_ar ?? $event->location);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>رمز الحجز · {{ $titleAr }}</title>
    @include('partials.theme-tokens', ['themeTenant' => $tenant])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; background: #e9edf2; font-family: var(--font-ar), var(--font-en), system-ui, sans-serif; color: #111827; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { display: flex; gap: 10px; justify-content: center; padding: 16px; }
        .toolbar a, .toolbar button { display: inline-flex; gap: 8px; align-items: center; height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; color: #111827; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: {{ $tt['shared']['header-start'] }}; color: {{ $tt['shared']['on-header'] }}; border-color: transparent; }
        .poster { width: 210mm; min-height: 297mm; margin: 0 auto 24px; background: #fff; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,.12); }
        .band { background: linear-gradient(135deg, {{ $tt['shared']['header-start'] }}, {{ $tt['shared']['header-end'] }}); color: {{ $tt['shared']['on-header'] }}; padding: 14mm 16mm 12mm; display: flex; align-items: center; gap: 14px; }
        .band img { height: 18mm; max-width: 50mm; object-fit: contain; }
        .band .org b { display: block; font-size: 22pt; line-height: 1.2; }
        .band .org span { font-size: 12pt; opacity: .85; }
        .body { flex: 1; padding: 12mm 16mm 10mm; display: flex; flex-direction: column; align-items: center; text-align: center; }
        h1 { font-size: 28pt; line-height: 1.25; margin: 0; }
        .en { font-size: 15pt; color: #4b5563; margin-top: 2mm; direction: ltr; }
        .meta { display: flex; flex-wrap: wrap; justify-content: center; gap: 3mm 8mm; margin-top: 5mm; font-size: 13pt; color: #374151; }
        .meta span { display: inline-flex; gap: 2mm; align-items: center; }
        .cta { margin-top: 9mm; font-size: 24pt; font-weight: 800; color: {{ $tt['shared']['header-start'] }}; }
        .cta-en { font-size: 14pt; font-weight: 700; color: #4b5563; direction: ltr; }
        .qr { margin-top: 6mm; padding: 6mm; border: 2.5mm solid {{ $tt['shared']['header-start'] }}; border-radius: 8mm; background: #fff; }
        .qr svg { display: block; width: 105mm; height: 105mm; }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6mm; width: 100%; margin-top: 10mm; }
        .step i { display: grid; place-items: center; width: 14mm; height: 14mm; margin: 0 auto 2mm; border-radius: 50%; background: #f3f4f6; color: {{ $tt['shared']['header-start'] }}; font-size: 18pt; }
        .step b { display: block; font-size: 12.5pt; }
        .step span { display: block; font-size: 10pt; color: #6b7280; direction: ltr; }
        .url { margin-top: auto; padding-top: 8mm; font-size: 10.5pt; color: #6b7280; direction: ltr; font-family: ui-monospace, Menlo, monospace; }
        @media print { body { background: #fff; } .toolbar { display: none; } .poster { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
        <a href="{{ route('admin.events.booking-qr', ['event' => $event, 'format' => 'svg']) }}"><i class="bi bi-download"></i> تنزيل الرمز (SVG)</a>
        <a href="{{ $url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-left"></i> فتح صفحة الحجز</a>
        <a href="{{ route('admin.events.show', $event) }}"><i class="bi bi-arrow-right"></i> رجوع</a>
    </div>

    <main class="poster">
        <header class="band">
            @if($logo)<img src="{{ $logo }}" alt="">@endif
            <div class="org"><b>{{ $name }}</b>@if($nameEn && $nameEn !== $name)<span>{{ $nameEn }}</span>@endif</div>
        </header>
        <section class="body">
            <h1>{{ $titleAr }}</h1>
            @if($titleEn)<div class="en">{{ $titleEn }}</div>@endif
            <div class="meta">
                @if($start)<span><i class="bi bi-calendar3"></i> {{ $start->translatedFormat('j F') }}@if($end && $end->toDateString() !== $start->toDateString()) – {{ $end->translatedFormat('j F Y') }}@else {{ $start->format('Y') }}@endif</span>@endif
                @if($venue)<span><i class="bi bi-geo-alt"></i> {{ $venue }}</span>@endif
            </div>

            <div class="cta">امسح الرمز لحجز تذكرتك</div>
            <div class="cta-en">Scan to book your ticket</div>
            <div class="qr">{!! QrCode::size(600)->margin(0)->errorCorrection('H')->generate($url) !!}</div>

            <div class="steps">
                <div class="step"><i class="bi bi-qr-code-scan"></i><b>امسح الرمز</b><span>Scan the code</span></div>
                <div class="step"><i class="bi bi-phone"></i><b>أدخل رقم هاتفك والرمز</b><span>Enter your phone and the code</span></div>
                <div class="step"><i class="bi bi-ticket-perforated"></i><b>ادفع واعرض تذكرتك</b><span>Pay and show your ticket</span></div>
            </div>
            <div class="url">{{ $url }}</div>
        </section>
    </main>
</body>
</html>
