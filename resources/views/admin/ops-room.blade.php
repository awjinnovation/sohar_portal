{{-- Admin → Operations room: full-screen wall screen of the technical operations room
     (App\Http\Controllers\Admin\OpsRoomController). No admin sidebar; always dark.
     public/js/ops-room.js renders $payload, then polls admin.ops-room.data every 10 s. --}}
@php
    $locale = \App\Http\Middleware\SetAdminLocale::current();
    $isAr = $locale === 'ar';
    $tt = \App\View\TenantTheme::for($brand ?? null);
    $brandName = $brand?->localizedName($locale) ?? config('app.name');
    $logo = $tt['logo_on_primary'] ?? ($tt['logo'] ?? null);
    $i18n = [
        'locale' => $isAr ? 'ar-OM-u-nu-latn' : 'en-GB',
        'updated' => __('آخر تحديث :time'),
        'offline' => __('انقطع الاتصال بالخادم، والبيانات المعروضة من الساعة :time. تُعاد المحاولة تلقائيًا.'),
        'session' => __('انتهت جلسة الدخول. يُرجى تسجيل الدخول مرة أخرى ثم فتح الشاشة من جديد.'),
        'login' => __('تسجيل الدخول'),
        'sec' => __(':n ث'), 'min' => __(':n د'), 'hour' => __(':n س'),
        'ago' => __('قبل :time'),
        'never' => __('لا يوجد'),
        'status' => ['ok' => __('يعمل بانتظام'), 'warn' => __('بطء'), 'critical' => __('يحتاج إلى تدخل')],
        'process' => ['running' => __('يعمل'), 'stopped' => __('متوقف'), 'unknown' => __('لا توجد إشارة')],
        'horizon' => ['running' => __('يعمل'), 'paused' => __('موقوف مؤقتًا'), 'inactive' => __('متوقف')],
        'queue' => __('الطابور :name'),
        'workers' => __('العاملون على هذا الطابور: :n'),
        'last_sent' => __('أُرسلت آخر رسالة قبل :time'),
        'channels' => [
            'online' => __('التطبيق والموقع'),
            'link' => __('رابط دفع من البوابة'),
            'card' => __('جهاز البطاقة في البوابة'),
            'wallet' => __('المحفظة'),
            'other' => __('إصدار يدوي'),
        ],
        'closed' => __('مغلقة'),
        'no_gates' => __('لا توجد بوابات بعد'),
        'no_scans' => __('لا يوجد مسح'),
        'devices' => __(':online من :total'),
        'per_min' => __(':n في الدقيقة'),
        'oldest' => __('أقدم رسالة تنتظر منذ :time'),
        'oldest_job' => __('أقدم مهمة تنتظر منذ :time'),
        'empty_queue' => __('لا شيء في الانتظار'),
        'chart_label' => __('التذاكر الصادرة كل 5 دقائق خلال الساعتين الماضيتين'),
        'gate_chart' => __('الدخول كل 5 دقائق خلال الساعة الماضية'),
        'two_hours' => __('قبل ساعتين'), 'one_hour' => __('قبل ساعة'), 'now' => __('الآن'),
        'fullscreen' => __('ملء الشاشة'), 'exit_fullscreen' => __('الخروج من ملء الشاشة'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0F1115">
    <title>{{ __('غرفة العمليات التقنية') }} · {{ $brandName }}</title>
    @if($tt['icon'])<link rel="icon" href="{{ $tt['icon'] }}">@endif
    @include('partials.theme-tokens', ['themeTenant' => $brand ?? null])
    {{-- This screen is always dark, whatever the admin's light/dark choice. --}}
    <script>document.documentElement.setAttribute('data-theme', 'dark'); document.documentElement.setAttribute('data-bs-theme', 'dark');</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/ops-room.css') }}?v={{ @filemtime(public_path('css/ops-room.css')) }}">
</head>
<body class="ops">
    <header class="ops-top">
        <div class="ops-brand">
            @if($logo)<img src="{{ $logo }}" alt="">@endif
            <div>
                <b>{{ $brandName }}</b>
                <span>{{ __('غرفة العمليات التقنية') }}</span>
            </div>
        </div>
        <div class="ops-live">
            <span class="ops-pill ok" id="opsLive"><span class="ops-dot" aria-hidden="true"></span>{{ __('مباشر') }}</span>
            <span class="ops-muted num" id="opsUpdated"></span>
        </div>
        <div class="ops-clock">
            <b class="num" id="opsClock">--:--</b>
            <span id="opsDate"></span>
        </div>
        <div class="ops-actions">
            <button type="button" class="ops-btn" id="opsFullscreen" title="{{ __('ملء الشاشة') }}" aria-label="{{ __('ملء الشاشة') }}"><i class="bi bi-arrows-fullscreen"></i></button>
            <a class="ops-btn" href="{{ route('admin.dashboard') }}" title="{{ __('العودة إلى لوحة التحكم') }}" aria-label="{{ __('العودة إلى لوحة التحكم') }}"><i class="bi bi-grid-1x2"></i></a>
        </div>
    </header>

    <div class="ops-banner" id="opsBanner" role="alert" hidden></div>

    <section class="ops-kpis" aria-label="{{ __('المؤشرات الرئيسية') }}">
        <div class="ops-kpi"><i class="bi bi-ticket-perforated"></i><span>{{ __('تذاكر اليوم') }}</span><b class="num" data-k="tickets.today">0</b><small class="num" data-k="tickets.hint"></small></div>
        <div class="ops-kpi"><i class="bi bi-box-arrow-in-right"></i><span>{{ __('الدخول اليوم') }}</span><b class="num" data-k="gates.in">0</b><small class="num" data-k="gates.hint"></small></div>
        <div class="ops-kpi"><i class="bi bi-people"></i><span>{{ __('داخل الموقع الآن') }}</span><b class="num" data-k="gates.inside">0</b><small>{{ __('الدخول ناقص الخروج') }}</small></div>
        <div class="ops-kpi"><i class="bi bi-speedometer2"></i><span>{{ __('الدخول في الدقيقة') }}</span><b class="num" data-k="gates.per_minute">0</b><small>{{ __('متوسط آخر 15 دقيقة') }}</small></div>
        <div class="ops-kpi" data-tone="sms"><i class="bi bi-chat-dots"></i><span>{{ __('رسائل في الانتظار') }}</span><b class="num" data-k="sms.pending">0</b><small class="num" data-k="sms.hint"></small></div>
        <div class="ops-kpi" data-tone="orders"><i class="bi bi-hourglass-split"></i><span>{{ __('طلبات تذاكر في الانتظار') }}</span><b class="num" data-k="orders.waiting">0</b><small>{{ __('على صفحة الدفع أو برابط لم يُدفع') }}</small></div>
    </section>

    <main class="ops-grid">
        <section class="ops-panel ops-gates" aria-labelledby="gatesTitle">
            <header class="ops-head">
                <h2 id="gatesTitle"><i class="bi bi-door-open"></i> {{ __('البوابات الأكثر ازدحامًا') }}</h2>
                <span class="ops-muted">{{ __('مرتبة حسب الدخول في آخر 15 دقيقة') }}</span>
            </header>
            <div class="ops-gate-cols" aria-hidden="true">
                <span></span>
                <span>{{ __('البوابة') }}</span>
                <span>{{ __('آخر ساعة') }}</span>
                <span>{{ __('آخر 15 دقيقة') }}</span>
                <span>{{ __('اليوم') }}</span>
                <span>{{ __('مرفوض') }}</span>
                <span>{{ __('الأجهزة') }}</span>
                <span>{{ __('آخر مسح') }}</span>
            </div>
            <ol class="ops-gate-list" id="gateList"></ol>
        </section>

        <section class="ops-panel ops-queue" id="smsPanel" aria-labelledby="smsTitle">
            <header class="ops-head">
                <h2 id="smsTitle"><i class="bi bi-chat-dots"></i> {{ __('طابور الرسائل النصية') }}</h2>
                <span class="ops-pill" data-k="sms.status"></span>
            </header>
            <div class="ops-big">
                <b class="num" data-k="sms.pending">0</b>
                <span>{{ __('رسالة في الانتظار') }}</span>
            </div>
            <div class="ops-wait num" data-k="sms.wait"></div>
            <dl class="ops-stats">
                <div><dt>{{ __('قيد الإرسال') }}</dt><dd class="num" data-k="sms.reserved">0</dd></div>
                <div><dt>{{ __('مؤجلة') }}</dt><dd class="num" data-k="sms.delayed">0</dd></div>
                <div><dt>{{ __('أُرسلت هذه الساعة') }}</dt><dd class="num" data-k="sms.sent_hour">0</dd></div>
                <div><dt>{{ __('أُرسلت اليوم') }}</dt><dd class="num" data-k="sms.sent_today">0</dd></div>
                <div data-alert="sms.failed_today"><dt>{{ __('تعذّر إرسالها') }}</dt><dd class="num" data-k="sms.failed_today">0</dd></div>
                <div><dt>{{ __('انتظار آخر رسالة') }}</dt><dd class="num" data-k="sms.last_wait">-</dd></div>
            </dl>
            <p class="ops-foot ops-muted num" data-k="sms.foot"></p>
        </section>

        <section class="ops-panel ops-queue" id="ordersPanel" aria-labelledby="ordersTitle">
            <header class="ops-head">
                <h2 id="ordersTitle"><i class="bi bi-cart-check"></i> {{ __('طابور طلبات التذاكر') }}</h2>
                <span class="ops-pill" data-k="orders.status"></span>
            </header>
            <div class="ops-big">
                <b class="num" data-k="orders.waiting">0</b>
                <span>{{ __('طلب بانتظار الدفع') }}</span>
            </div>
            <div class="ops-wait num" data-k="orders.wait"></div>
            <dl class="ops-stats">
                <div><dt>{{ __('على صفحة الدفع') }}</dt><dd class="num" data-k="orders.checkout">0</dd></div>
                <div><dt>{{ __('روابط دفع لم تُدفع') }}</dt><dd class="num" data-k="orders.links">0</dd></div>
                <div><dt>{{ __('معالجة المدفوعات') }}</dt><dd class="num" data-k="orders.jobs">0</dd></div>
                <div data-alert="orders.unissued"><dt>{{ __('مدفوعة بلا تذاكر') }}</dt><dd class="num" data-k="orders.unissued">0</dd></div>
                <div data-warn="orders.review"><dt>{{ __('بانتظار المراجعة') }}</dt><dd class="num" data-k="orders.review">0</dd></div>
                <div data-alert="orders.failed_jobs"><dt>{{ __('مهام متعثرة (24 ساعة)') }}</dt><dd class="num" data-k="orders.failed_jobs">0</dd></div>
            </dl>
            <p class="ops-foot ops-muted num" data-k="orders.foot"></p>
        </section>

        <section class="ops-panel ops-tickets" aria-labelledby="ticketsTitle">
            <header class="ops-head">
                <h2 id="ticketsTitle"><i class="bi bi-ticket-perforated"></i> {{ __('التذاكر الصادرة') }}</h2>
                <span class="ops-muted">{{ __('منذ بداية يوم التشغيل') }}</span>
            </header>
            <div class="ops-tickets-body">
                <dl class="ops-ticket-nums">
                    <div class="lead"><dt>{{ __('اليوم') }}</dt><dd class="num" data-k="tickets.today">0</dd></div>
                    <div><dt>{{ __('عدد الأشخاص') }}</dt><dd class="num" data-k="tickets.people">0</dd></div>
                    <div><dt>{{ __('آخر 15 دقيقة') }}</dt><dd class="num" data-k="tickets.last_15m">0</dd></div>
                    <div><dt>{{ __('آخر ساعة') }}</dt><dd class="num" data-k="tickets.last_hour">0</dd></div>
                </dl>
                <figure class="ops-chart">
                    <div class="ops-bars" id="ticketBars" role="img"></div>
                    <figcaption class="ops-axis"><span id="axisFrom"></span><span id="axisMid"></span><span id="axisNow"></span></figcaption>
                </figure>
                <ul class="ops-channels" id="ticketChannels" aria-label="{{ __('حسب قناة البيع') }}"></ul>
            </div>
        </section>
    </main>

    <footer class="ops-health" aria-label="{{ __('حالة الأنظمة') }}">
        <span class="ops-health-item" data-k="system.worker"><i class="bi bi-cpu"></i> {{ __('معالج الطوابير') }} <b></b></span>
        <span class="ops-health-item" data-k="system.horizon" hidden><i class="bi bi-layers"></i> Horizon <b></b></span>
        <span class="ops-health-item" data-k="system.scheduler"><i class="bi bi-clock-history"></i> {{ __('المجدول') }} <b></b></span>
        <span class="ops-health-item" data-k="system.devices"><i class="bi bi-upc-scan"></i> {{ __('أجهزة المسح المتصلة') }} <b class="num"></b></span>
        <span class="ops-health-item" data-k="system.rejects"><i class="bi bi-x-octagon"></i> {{ __('مسح مرفوض اليوم') }} <b class="num"></b></span>
        <span class="ops-health-item ops-muted"><i class="bi bi-diagram-3"></i> {{ __('الطوابير') }} <b class="num" data-k="system.driver"></b></span>
        <span class="ops-health-item ops-muted">{{ __('الطوابير والرسائل على مستوى المنصة، والتذاكر والبوابات لهذه الجهة') }}</span>
    </footer>

    <script>
        window.OPS_ROOM = {
            T: @json($i18n),
            state: @json($payload),
            dataUrl: @json(route('admin.ops-room.data')),
            loginUrl: @json(route('admin.login.form')),
            poll: 10000
        };
    </script>
    <script src="{{ asset('js/ops-room.js') }}?v={{ @filemtime(public_path('js/ops-room.js')) }}"></script>
</body>
</html>
