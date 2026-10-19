@extends('layouts.admin')

@section('title', 'لوحة البوابات المباشرة')
@section('page-title', 'لوحة البوابات المباشرة')
@section('page-subtitle', 'الدخول والخروج والرفض لكل بوابة، وتتحدّث كل 15 ثانية')
@php
    $pageIcon = 'broadcast';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'لوحة البوابات المباشرة' => null];
    $L = \App\Services\Gate\GateLabels::class;
    $i18n = [
        'results' => collect($L::RESULTS)->map(fn ($v) => __($v[0])),
        'variants' => collect($L::RESULTS)->map(fn ($v) => $v[1]),
        'exit' => __('خروج'),
        'in' => __('دخول'), 'out' => __('خروج'), 'rejects' => __('مرفوض'),
        'devices_online' => __('الأجهزة المتصلة: :online من :total'),
        'no_devices' => __('لا توجد أجهزة'),
        'last_scan' => __('آخر مسح :time'),
        'no_scans' => __('لا يوجد مسح بعد'),
        'closed' => __('مغلقة'),
        'reentry' => __('إعادة الدخول'),
        'updated' => __('آخر تحديث :time'),
        'offline' => __('دون اتصال'),
        'first' => __('دخلت أولًا من :gate الساعة :time'),
        'all_events' => __('كل الفعاليات'),
        'directions' => collect($L::GATE_DIRECTIONS)->map(fn ($v) => __($v)),
        'sources' => collect($L::SOURCES)->map(fn ($v) => __($v)),
        'refresh_failed' => __('تعذّر التحديث، وستُعاد المحاولة'),
        'ai_cached' => __('من الذاكرة المؤقتة'),
    ];
@endphp
@section('page-actions')
    <form method="GET" class="d-flex gap-2 align-items-center m-0" id="gateDateForm">
        <input type="date" name="date" value="{{ $date }}" max="{{ $today }}" class="form-control" style="width:auto" aria-label="{{ __('اليوم') }}" onchange="this.form.submit()">
        @if($date !== $today)
            <a href="{{ route('admin.gates.dashboard') }}" class="btn">{{ __('اليوم') }}</a>
        @endif
    </form>
    <a href="{{ route('admin.gates.scans.index', ['date' => $date]) }}" class="btn"><i class="bi bi-list-check"></i> {{ __('سجل المسح') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid gate-stats">
        <x-admin.stat-tile label="دخول" value="0" icon="box-arrow-in-right" tone="success" id="tIn" />
        <x-admin.stat-tile label="خروج" value="0" icon="box-arrow-right" tone="info" id="tOut" />
        <x-admin.stat-tile label="داخل الموقع الآن" value="0" icon="people" tone="brand" id="tInside" hint="الدخول ناقص الخروج" />
        <x-admin.stat-tile label="مرفوض" value="0" icon="x-octagon" tone="error" id="tRejects" />
        <x-admin.stat-tile label="تعارضات" value="0" icon="exclamation-diamond" tone="warning" id="tConflicts" hint="تذاكر دخلت من مكانين" />
        <x-admin.stat-tile label="الأجهزة المتصلة" value="0" icon="upc-scan" tone="neutral" id="tDevices" />
    </div>

    {{-- AI insights (contract §A6) — hidden unless the endpoint answers with content --}}
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3 small text-muted">
        @if($date === $today)
            <span class="pill pill-success pill-sm"><span class="live-dot" aria-hidden="true"></span>{{ __('مباشر') }}</span>
        @else
            <span class="pill pill-neutral pill-sm"><i class="bi bi-calendar3"></i>{{ __('يوم سابق') }} · <span class="num">{{ $date }}</span></span>
        @endif
        <span id="gUpdated"></span>
        <span class="text-danger" id="gError" hidden></span>
    </div>

    <x-admin.card title="تحليلات المساعد الذكي" icon="stars" subtitle="ملخص تدفق الزوار وتوصيات تشغيلية من البيانات المجمّعة فقط" id="aiInsights" hidden>
        <x-slot:actions>
            <span class="small text-muted" id="aiMeta"></span>
            <button type="button" class="btn btn-tonal btn-sm" id="aiRefresh"><i class="bi bi-arrow-repeat"></i> {{ __('إعادة التوليد') }}</button>
        </x-slot:actions>
        <p class="mb-3" id="aiSummary"></p>
        <div class="row g-3">
            <div class="col-md-6" id="aiRecsWrap" hidden>
                <h3 class="h6 fw-bold"><i class="bi bi-lightbulb text-warning"></i> {{ __('توصيات') }}</h3>
                <ul class="mb-0 ps-3" id="aiRecs"></ul>
            </div>
            <div class="col-md-6" id="aiAnomWrap" hidden>
                <h3 class="h6 fw-bold"><i class="bi bi-exclamation-triangle text-danger"></i> {{ __('حالات غير اعتيادية') }}</h3>
                <ul class="mb-0 ps-3" id="aiAnoms"></ul>
            </div>
        </div>
    </x-admin.card>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="الدخول كل 5 دقائق" icon="bar-chart" subtitle="لكل بوابة، مع عدد المرفوضين">
                <div class="chart-box" style="height: 300px"><canvas id="gateChart" role="img" aria-label="{{ __('رسم بياني للدخول كل 5 دقائق') }}"></canvas></div>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="أسباب الرفض" icon="x-octagon" :flush="true">
                <ul class="tx-list" id="gReasons"></ul>
                <div id="gReasonsEmpty" hidden><x-admin.empty-state icon="check2-circle" title="لا يوجد رفض" text="ستظهر هنا أسباب رفض التذاكر." /></div>
            </x-admin.card>
        </div>
    </div>

    <h2 class="h6 fw-bold mt-2 mb-3"><i class="bi bi-door-open text-primary"></i> {{ __('البوابات') }}</h2>
    <div class="gate-grid mb-4" id="gGates"></div>

    <div class="row g-4">
        <div class="col-xl-6">
            <x-admin.card title="التعارضات" icon="exclamation-diamond" subtitle="مسح دون اتصال لتذكرة دخلت من مكان آخر أولًا" :flush="true">
                <ul class="tx-list" id="gConflicts"></ul>
                <div id="gConflictsEmpty" hidden><x-admin.empty-state icon="shield-check" title="لا توجد تعارضات" /></div>
            </x-admin.card>
        </div>
        <div class="col-xl-6">
            <x-admin.card title="آخر عمليات المسح" icon="clock-history" :flush="true">
                <x-slot:actions><a href="{{ route('admin.gates.scans.index', ['date' => $date]) }}" class="btn btn-link btn-sm">{{ __('عرض الكل') }} <i class="bi bi-chevron-left dir-icon"></i></a></x-slot:actions>
                <ul class="tx-list" id="gRecent"></ul>
                <div id="gRecentEmpty" hidden><x-admin.empty-state icon="upc-scan" title="لا يوجد مسح بعد" text="ستظهر هنا عمليات المسح فور وصولها." /></div>
            </x-admin.card>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success, #16A34A); box-shadow: 0 0 0 0 rgba(var(--success-rgb, 22,163,74), .6); animation: livePulse 1.6s infinite; }
    @keyframes livePulse { 70% { box-shadow: 0 0 0 8px rgba(var(--success-rgb, 22,163,74), 0); } 100% { box-shadow: 0 0 0 0 rgba(var(--success-rgb, 22,163,74), 0); } }
    @media (prefers-reduced-motion: reduce) { .live-dot { animation: none; } }
    .gate-stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .live-dot { display: inline-block; margin-inline-end: 2px; }
    .gate-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .gate-card { background: var(--surface); border: 1px solid var(--hairline); border-radius: var(--r-lg); padding: 16px; box-shadow: var(--e1); display: flex; flex-direction: column; gap: 10px; }
    .gate-card.closed { opacity: .65; }
    .gate-card .gc-head { display: flex; align-items: center; gap: 10px; }
    .gate-card .gc-code { min-width: 38px; height: 38px; padding: 0 8px; border-radius: var(--r-md); display: grid; place-items: center; background: var(--brand-soft); color: var(--brand-text); font-weight: 800; font-family: var(--font-num), var(--font-ui); }
    .gate-card .gc-name { font-weight: 700; line-height: 1.3; }
    .gate-card .gc-sub { font-size: 12px; color: var(--muted); }
    .gate-card .gc-nums { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
    .gate-card .gc-nums div { background: var(--surface-low); border-radius: var(--r-md); padding: 8px 4px; }
    .gate-card .gc-nums b { display: block; font-size: 22px; font-family: var(--font-num), var(--font-ui); line-height: 1.1; }
    .gate-card .gc-nums span { font-size: 11.5px; color: var(--muted); }
    .gate-card .gc-foot { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); gap: 8px; flex-wrap: wrap; }
    .reason-bar { height: 6px; border-radius: 3px; background: var(--surface-high, #E5E7EB); overflow: hidden; margin-top: 6px; }
    .reason-bar i { display: block; height: 100%; background: var(--error, #DC2626); }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var T = @json($i18n);
    var state = @json($payload);
    var dataUrl = @json(route('admin.gates.dashboard.data', ['date' => $date]));
    var insightsUrl = @json(url('/admin/gates/insights'));
    var date = @json($date);
    var live = @json($date === $today);
    var css = function (v, f) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim() || f; };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var fmt = function (n) { return Number(n || 0).toLocaleString('en-US'); };
    var hm = function (iso) { if (!iso) return ''; var m = String(iso).match(/T(\d{2}:\d{2})/); return m ? m[1] : iso; };
    var tile = function (id, v, hint) {
        var el = document.getElementById(id); if (!el) return;
        el.querySelector('.stat-value').textContent = v;
        if (hint !== undefined) { var h = el.querySelector('.stat-hint'); if (!h) { h = document.createElement('div'); h.className = 'stat-hint'; el.appendChild(h); } h.textContent = hint; }
    };
    var palette = function () { return [css('--brand', '#2F8E80'), css('--accent', '#E5883C'), css('--info', '#2563EB'), css('--points', '#7C3AED'), '#0EA5E9', '#DB2777', '#65A30D', '#B45309']; };
    var resultLabel = function (r, dir) { return r === 'valid' && dir === 'out' ? T.exit : (T.results[r] || r); };
    var resultTone = function (r, dir) { if (r === 'valid' && dir === 'out') return 'neutral'; var v = T.variants[r] || 'neutral'; return v === 'error' ? 'error' : v; };
    var resultIcon = function (r, dir) { if (dir === 'out') return 'box-arrow-right'; return { valid: 'check-lg', reentry_ok: 'arrow-repeat' }[r] || 'x-lg'; };

    var chart = null;
    function renderChart(s) {
        var el = document.getElementById('gateChart');
        if (!el || !window.Chart) return;
        var colors = palette();
        var sets = s.series.datasets.map(function (d, i) {
            return { type: 'bar', label: d.label, data: d.data, backgroundColor: colors[i % colors.length], stack: 'in', borderRadius: 3, maxBarThickness: 18 };
        });
        sets.push({ type: 'line', label: T.rejects, data: s.series.rejects, borderColor: css('--error', '#DC2626'), backgroundColor: css('--error', '#DC2626'), pointRadius: 0, borderWidth: 2, tension: .3, yAxisID: 'y' });
        if (chart) { chart.data.labels = s.series.labels; chart.data.datasets = sets; chart.update('none'); return; }
        Chart.defaults.font.family = css('--font-num', 'Inter') + ', ' + css('--font-ar', 'Cairo') + ', sans-serif';
        chart = new Chart(el, {
            data: { labels: s.series.labels, datasets: sets },
            options: {
                maintainAspectRatio: false, animation: false, locale: 'en-US',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, color: css('--muted', '#6B7280') } }, tooltip: { rtl: document.dir === 'rtl', mode: 'index', intersect: false } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { color: css('--muted', '#6B7280'), maxTicksLimit: 12 } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: css('--muted', '#6B7280') }, grid: { color: css('--hairline', '#E5E7EB') } }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });
    }

    function render(s) {
        var t = s.totals;
        tile('tIn', fmt(t.in)); tile('tOut', fmt(t.out)); tile('tInside', fmt(t.inside));
        tile('tRejects', fmt(t.rejects)); tile('tConflicts', fmt(t.conflicts));
        tile('tDevices', fmt(t.devices_online) + ' / ' + fmt(t.devices_total));

        document.getElementById('gGates').innerHTML = s.gates.map(function (g) {
            var dev = g.devices ? T.devices_online.replace(':online', g.devices_online).replace(':total', g.devices) : T.no_devices;
            return '<div class="gate-card' + (g.active ? '' : ' closed') + '">' +
                '<div class="gc-head"><span class="gc-code">' + esc(g.code) + '</span><div class="min-w-0"><div class="gc-name text-truncate">' + esc(g.name) + '</div>' +
                '<div class="gc-sub">' + esc(T.directions[g.direction] || g.direction) + ' · ' + esc(g.event || T.all_events) + (g.allow_reentry ? ' · ' + esc(T.reentry) : '') + (g.active ? '' : ' · ' + esc(T.closed)) + '</div></div></div>' +
                '<div class="gc-nums"><div><b class="text-success">' + fmt(g.in) + '</b><span>' + esc(T.in) + '</span></div><div><b>' + fmt(g.out) + '</b><span>' + esc(T.out) + '</span></div><div><b class="text-danger">' + fmt(g.rejects) + '</b><span>' + esc(T.rejects) + '</span></div></div>' +
                '<div class="gc-foot"><span><i class="bi bi-upc-scan"></i> ' + esc(dev) + '</span><span>' + esc(g.last_scan_at ? T.last_scan.replace(':time', hm(g.last_scan_at)) : T.no_scans) + '</span></div></div>';
        }).join('');

        var max = s.reasons.reduce(function (m, r) { return Math.max(m, r.count); }, 0) || 1;
        document.getElementById('gReasons').innerHTML = s.reasons.map(function (r) {
            return '<li class="tx-item d-block"><div class="d-flex justify-content-between"><span class="fw-bold">' + esc(r.label) + '</span><span class="num">' + fmt(r.count) + '</span></div>' +
                '<div class="reason-bar"><i style="width:' + Math.round(r.count * 100 / max) + '%"></i></div></li>';
        }).join('');
        document.getElementById('gReasonsEmpty').hidden = s.reasons.length > 0;

        document.getElementById('gConflicts').innerHTML = s.conflicts.map(function (c) {
            var first = c.first_gate || c.first_at ? T.first.replace(':gate', c.first_gate || '—').replace(':time', hm(c.first_at) || '—') : '';
            return '<li class="tx-item"><span class="tx-ico tone-warning"><i class="bi bi-exclamation-diamond"></i></span><div class="tx-main"><div class="tx-title ltr text-start">' + esc(c.ticket_number || '—') + '</div>' +
                '<div class="tx-sub">' + esc((c.gate || '—') + ' · ' + (c.device || '—')) + (first ? '<br>' + esc(first) : '') + '</div></div><div class="tx-amt num">' + esc(hm(c.scanned_at)) + '</div></li>';
        }).join('');
        document.getElementById('gConflictsEmpty').hidden = s.conflicts.length > 0;

        document.getElementById('gRecent').innerHTML = s.recent.map(function (r) {
            return '<li class="tx-item"><span class="tx-ico tone-' + resultTone(r.result, r.direction) + '"><i class="bi bi-' + resultIcon(r.result, r.direction) + '"></i></span><div class="tx-main"><div class="tx-title">' + esc(resultLabel(r.result, r.direction)) +
                (r.ticket_number ? ' · <span class="ltr">' + esc(r.ticket_number) + '</span>' : '') + '</div><div class="tx-sub">' + esc((r.gate || '—') + (r.device ? ' · ' + r.device : '') + ' · ' + (T.sources[r.source] || r.source)) +
                (r.offline ? ' · <i class="bi bi-cloud-slash"></i> ' + esc(T.offline) : '') + '</div></div><div class="tx-amt num">' + esc(hm(r.scanned_at)) + '</div></li>';
        }).join('');
        document.getElementById('gRecentEmpty').hidden = s.recent.length > 0;

        document.getElementById('gUpdated').textContent = T.updated.replace(':time', hm(s.generated_at));
        renderChart(s);
    }

    function poll() {
        fetch(dataUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (j) { state = j.data; render(state); document.getElementById('gError').hidden = true; })
            .catch(function () { var e = document.getElementById('gError'); e.textContent = T.refresh_failed; e.hidden = false; });
    }

    // AI insights card (A6): any failure / disabled AI / empty answer keeps the card hidden.
    function insights(refresh) {
        var card = document.getElementById('aiInsights');
        var btn = document.getElementById('aiRefresh');
        btn.disabled = true;
        fetch(insightsUrl + '?date=' + encodeURIComponent(date) + (refresh ? '&refresh=1' : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (j) {
                var d = j && j.success ? j.data : null;
                var text = function (v) { return v && typeof v === 'object' ? (v[document.documentElement.lang] || v.en || v.ar || '') : (v || ''); };
                var list = function (v) { return (Array.isArray(v) ? v : []).map(function (x) { return text(x && typeof x === 'object' && !('en' in x) && !('ar' in x) ? (x.text || x.title || '') : x); }).filter(Boolean); };
                var summary = d ? text(d.summary) : '';
                var recs = d ? list(d.recommendations) : [], anoms = d ? list(d.anomalies) : [];
                if (!d || d.ai === false || (!summary && !recs.length && !anoms.length)) { card.hidden = true; return; }
                document.getElementById('aiSummary').textContent = summary;
                document.getElementById('aiRecs').innerHTML = recs.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('');
                document.getElementById('aiAnoms').innerHTML = anoms.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('');
                document.getElementById('aiRecsWrap').hidden = !recs.length;
                document.getElementById('aiAnomWrap').hidden = !anoms.length;
                document.getElementById('aiMeta').textContent = (d.generated_at ? hm(d.generated_at) : '') + (d.cached ? ' · ' + T.ai_cached : '');
                card.hidden = false;
            })
            .catch(function () { card.hidden = true; })
            .finally(function () { btn.disabled = false; });
    }

    render(state);
    document.addEventListener('themechange', function () { if (chart) { chart.destroy(); chart = null; renderChart(state); } });
    document.getElementById('aiRefresh').addEventListener('click', function () { insights(true); });
    insights(false);
    if (live) {
        setInterval(function () { if (!document.hidden) poll(); }, 15000);
        setInterval(function () { if (!document.hidden) insights(false); }, 300000);
    }
})();
</script>
@endpush
