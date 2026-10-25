@extends('layouts.admin')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@php
    // $stats (counts), $walletStats and $m (KPIs/series/recent) come from
    // DashboardController → App\Services\Admin\DashboardMetrics.
    $w = $walletStats ?? [];
    $user = auth()->user();
    $hour = (int) now($brand?->setting('tenant.timezone') ?: config('app.timezone'))->format('G');
    $greeting = $hour < 12 ? __('صباح الخير') : __('مساء الخير');
    $revDelta = $m['revenue_yesterday'] > 0 ? round(($m['revenue_today'] - $m['revenue_yesterday']) * 100 / $m['revenue_yesterday']) : null;
    $txIcons = [
        'topup' => ['cash-coin', 'tone-success'], 'ticket_purchase' => ['ticket-perforated', 'tone-brand'], 'vendor_payment' => ['shop-window', 'tone-neutral'],
        'deposit_hold' => ['lock', 'tone-info'], 'deposit_release' => ['unlock', 'tone-success'], 'deposit_forfeit' => ['slash-circle', 'tone-neutral'],
        'refund' => ['arrow-counterclockwise', 'tone-warning'], 'refund_reversal' => ['arrow-clockwise', 'tone-success'],
        'points_redemption' => ['stars', 'tone-points'], 'adjustment' => ['sliders', 'tone-neutral'],
    ];
    $can = fn ($ability) => $user?->can($ability);
    $link = fn ($route) => \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;
@endphp
@section('page-subtitle', __(':greeting، :name. هذا ملخص نشاط :tenant اليوم.', ['greeting' => $greeting, 'name' => $user->name ?? '', 'tenant' => $brand?->localizedName(app()->getLocale()) ?? config('app.name')]))
@section('page-actions')
    @if($can('manage events') && $link('admin.events.create'))
        <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('فعالية جديدة') }}</a>
    @endif
    @if($can('manage announcements') && $link('admin.announcements.create'))
        <a href="{{ route('admin.announcements.create') }}" class="btn"><i class="bi bi-megaphone"></i> {{ __('إعلان') }}</a>
    @endif
@endsection

@section('content')
    @include('admin.partials.alerts')

    {{-- KPI tiles --}}
    <div class="stat-grid">
        <x-admin.stat-tile label="إيرادات اليوم" :money="$m['revenue_today']" icon="graph-up-arrow" tone="brand" :href="$can('view payments') ? $link('admin.payments.index') : null">
            @if($revDelta !== null)
                <span class="pill pill-sm {{ $revDelta >= 0 ? 'pill-success' : 'pill-neutral' }}"><i class="bi bi-arrow-{{ $revDelta >= 0 ? 'up' : 'down' }}"></i><span class="num">{{ abs($revDelta) }}%</span></span> {{ __('مقارنة بالأمس') }}
            @else
                {{ __('مدفوعات مكتملة (تذاكر وشحن)') }}
            @endif
        </x-admin.stat-tile>
        <x-admin.stat-tile label="التذاكر المباعة اليوم" :value="$m['tickets_today']" icon="ticket-perforated" tone="accent" :href="$can('manage tickets') ? $link('admin.tickets.index') : null"
            :hint="__('الإجمالي :total · مستخدمة :used', ['total' => number_format($m['tickets_total']), 'used' => number_format($m['tickets_used'])])" />
        <x-admin.stat-tile label="شحن المحافظ اليوم" :money="$m['topups_today']" icon="wallet2" tone="success" :href="$can('manage wallets') ? $link('admin.wallets.index') : null"
            :hint="__('عمليات الشحن: :count', ['count' => number_format($m['topups_count_today'])])" />
        <x-admin.stat-tile label="طلبات استرداد معلّقة" :value="$m['refunds_pending']" icon="arrow-counterclockwise" :tone="$m['refunds_pending'] ? 'warning' : 'neutral'" :href="$can('manage refunds') ? $link('admin.refund-requests.index') : null">
            <x-admin.money :minor="$m['refunds_pending_minor']" tone="muted" /> {{ __('بانتظار المراجعة') }}
        </x-admin.stat-tile>
        <x-admin.stat-tile label="التجار النشطون" :value="$m['vendors_active']" icon="shop-window" tone="info" :href="$can('manage vendors') ? $link('admin.vendors.index') : null"
            :hint="__('من أصل :count', ['count' => number_format($m['vendors_total'])])" />
    </div>

    @include('admin.ai._briefing-card')

    <div class="row g-4">
        {{-- Trends --}}
        <div class="col-xl-8">
            <x-admin.card title="النشاط خلال 14 يومًا" icon="bar-chart" subtitle="المدفوعات المكتملة يوميًا بتوقيت المنظمة">
                <x-slot:actions>
                    <div class="segmented" role="tablist" data-chart-switch>
                        <button type="button" class="active" data-series="ticket_revenue">{{ __('مبيعات التذاكر') }}</button>
                        <button type="button" data-series="topups">{{ __('شحن المحافظ') }}</button>
                        <button type="button" data-series="tickets">{{ __('عدد التذاكر') }}</button>
                    </div>
                </x-slot:actions>
                <div class="chart-box"><canvas id="trendChart" aria-label="{{ __('رسم بياني للنشاط اليومي') }}" role="img"></canvas></div>
                <details class="mt-3 small">
                    <summary class="text-muted">{{ __('عرض البيانات في جدول') }}</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('اليوم') }}</th><th>{{ __('مبيعات التذاكر') }}</th><th>{{ __('شحن المحافظ') }}</th><th>{{ __('التذاكر') }}</th></tr></thead>
                            <tbody>
                            @foreach($m['series']['labels'] as $i => $label)
                                <tr><td class="num">{{ $label }}</td><td><x-admin.money :minor="$m['series']['ticket_revenue'][$i]" /></td><td><x-admin.money :minor="$m['series']['topups'][$i]" /></td><td class="num">{{ $m['series']['tickets'][$i] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </x-admin.card>
        </div>

        {{-- Wallet overview --}}
        <div class="col-xl-4">
            <div class="wallet-card mb-4">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="wc-label">{{ __('إجمالي أرصدة المحافظ') }}</span>
                    <i class="bi bi-wallet2 fs-5 opacity-75"></i>
                </div>
                <div class="mt-1"><x-admin.money :minor="(int) ($w['balances'] ?? 0)" size="hero" /></div>
                <div class="wc-row">
                    <span class="wc-chip"><i class="bi bi-lock"></i> {{ __('تأمينات محجوزة') }} <x-admin.money :minor="(int) ($w['held_deposits'] ?? 0)" /></span>
                    <span class="wc-chip"><i class="bi bi-shop"></i> {{ __('مبيعات التجار اليوم') }} <x-admin.money :minor="(int) ($w['vendor_sales_today'] ?? 0)" /></span>
                </div>
            </div>

            <x-admin.card title="طلبات الاسترداد" icon="arrow-counterclockwise" :flush="true">
                @if($can('manage refunds') && $link('admin.refund-requests.index'))
                    <x-slot:actions><a href="{{ route('admin.refund-requests.index') }}" class="btn btn-link btn-sm">{{ __('عرض الكل') }} <i class="bi bi-chevron-left dir-icon"></i></a></x-slot:actions>
                @endif
                @if($m['pending_refunds_list']->isEmpty())
                    <x-admin.empty-state icon="check2-circle" title="لا توجد طلبات معلّقة" text="ستظهر هنا طلبات استرداد الرصيد الجديدة." />
                @else
                    <ul class="tx-list">
                        @foreach($m['pending_refunds_list'] as $rr)
                            <li class="tx-item">
                                <span class="tx-ico tone-warning"><i class="bi bi-hourglass-split"></i></span>
                                <div class="tx-main">
                                    <div class="tx-title">{{ $rr->user?->name ?? ('#'.$rr->user_id) }}</div>
                                    <div class="tx-sub">{{ \App\Support\AdminLabels::label(\App\Support\AdminLabels::REFUND_METHODS, $rr->method) }} · {{ $rr->created_at?->locale(app()->getLocale())->diffForHumans() }}</div>
                                </div>
                                <div class="tx-amt">
                                    <x-admin.money :minor="(int) $rr->amount_minor" />
                                    @if($can('manage refunds'))
                                        <div><a href="{{ route('admin.refund-requests.show', $rr) }}" class="small">{{ __('مراجعة') }}</a></div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        </div>

        {{-- Recent wallet activity --}}
        <div class="col-xl-7">
            <x-admin.card title="أحدث حركات المحافظ" icon="clock-history" :flush="true">
                @if($can('manage wallets') && $link('admin.wallets.index'))
                    <x-slot:actions><a href="{{ route('admin.wallets.index') }}" class="btn btn-link btn-sm">{{ __('كل المحافظ') }} <i class="bi bi-chevron-left dir-icon"></i></a></x-slot:actions>
                @endif
                @if($m['recent']->isEmpty())
                    <x-admin.empty-state icon="receipt" title="لا توجد حركات بعد" text="ستظهر عمليات الشحن والدفع والتأمينات هنا فور حدوثها." />
                @else
                    <ul class="tx-list">
                        @foreach($m['recent'] as $tx)
                            @php
                                [$txIcon, $txTone] = $txIcons[$tx->type] ?? ['arrow-left-right', 'tone-neutral'];
                                $bucket = data_get($tx->meta, 'bucket');
                            @endphp
                            <li class="tx-item">
                                <span class="tx-ico {{ $txTone }}"><i class="bi bi-{{ $txIcon }}"></i></span>
                                <div class="tx-main">
                                    <div class="tx-title">
                                        {{ \App\Support\AdminLabels::label(\App\Support\AdminLabels::TX_TYPES, $tx->type) }}
                                        @if($bucket === 'promo')<span class="pill pill-sm pill-points">{{ __('ترويجي') }}</span>@endif
                                    </div>
                                    <div class="tx-sub">
                                        {{ $tx->user?->name ?? '—' }}
                                        @if($tx->vendor) · {{ \App\View\Fmt::t($tx->vendor->name, app()->getLocale()) }} @endif
                                        · {{ $tx->created_at?->locale(app()->getLocale())->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="tx-amt">
                                    <x-admin.money :minor="(int) $tx->amount_minor" :direction="$tx->direction === 'credit' ? 'credit' : 'debit'" />
                                    @if($tx->status !== 'completed')
                                        <div><x-admin.status-pill domain="tx" :status="$tx->status" size="sm" /></div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        </div>

        {{-- Recent payments + content shortcuts --}}
        <div class="col-xl-5">
            @if($can('view payments'))
                <x-admin.card title="أحدث المدفوعات" icon="credit-card-2-front" :flush="true">
                    @if($link('admin.payments.index'))
                        <x-slot:actions><a href="{{ route('admin.payments.index') }}" class="btn btn-link btn-sm">{{ __('عرض الكل') }} <i class="bi bi-chevron-left dir-icon"></i></a></x-slot:actions>
                    @endif
                    @if($m['recent_payments']->isEmpty())
                        <x-admin.empty-state icon="credit-card" title="لا توجد مدفوعات" />
                    @else
                        <ul class="tx-list">
                            @foreach($m['recent_payments'] as $p)
                                <li class="tx-item">
                                    <span class="tx-ico {{ $p->payment_type === 'topup' ? 'tone-success' : 'tone-brand' }}"><i class="bi bi-{{ $p->payment_type === 'topup' ? 'cash-coin' : 'ticket-perforated' }}"></i></span>
                                    <div class="tx-main">
                                        <div class="tx-title">{{ \App\Support\AdminLabels::label(\App\Support\AdminLabels::PAYMENT_TYPES, $p->payment_type) }} · {{ $p->user?->name ?? '—' }}</div>
                                        <div class="tx-sub"><span class="ltr">{{ $p->transaction_id }}</span></div>
                                    </div>
                                    <div class="tx-amt">
                                        <x-admin.money :minor="(int) $p->amount_minor" />
                                        <div><x-admin.status-pill domain="payment" :status="$p->status" size="sm" /></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.card>
            @endif

            <x-admin.card title="المحتوى" icon="collection">
                <div class="quick-links">
                    @foreach([
                        ['admin.events.index', 'calendar-event', 'الفعاليات', $stats['main']['events'] ?? 0, 'manage events'],
                        ['admin.categories.index', 'grid', 'الفئات', $stats['catalog']['categories'], 'manage events'],
                        ['admin.event-tags.index', 'tags', 'الوسوم', $stats['catalog']['event_tags'], 'manage events'],
                        ['admin.restaurants.index', 'cup-hot', 'المطاعم', $stats['catalog']['restaurants'], 'manage restaurants'],
                        ['admin.map-locations.index', 'geo-alt', 'مواقع الخريطة', $stats['locations']['all_locations'] ?? 0, 'manage locations'],
                        ['admin.announcements.index', 'megaphone', 'الإعلانات', $stats['communication']['announcements'] ?? 0, 'manage announcements'],
                        ['admin.users.index', 'people', 'المستخدمون', $stats['main']['users'] ?? 0, 'manage users'],
                    ] as [$qlRoute, $qlIcon, $qlLabel, $qlCount, $qlCan])
                        @if($link($qlRoute) && $can($qlCan))
                            <a class="quick-link" href="{{ route($qlRoute) }}"><i class="bi bi-{{ $qlIcon }}"></i><span>{{ __($qlLabel) }}</span><span class="n">{{ number_format($qlCount) }}</span></a>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('trendChart');
    if (!el || !window.Chart) return;
    var data = @json($m['series']);
    var currency = @json(\App\View\Fmt::currency());
    var decimals = {{ \App\View\Fmt::decimals() }};
    var current = 'ticket_revenue';
    var labels = { ticket_revenue: @json(__('مبيعات التذاكر')), topups: @json(__('شحن المحافظ')), tickets: @json(__('عدد التذاكر')) };
    var ticketsTpl = @json(__('التذاكر: :count'));
    var isMoney = function (k) { return k !== 'tickets'; };
    var css = function (v) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); };
    var fmt = function (minor) {
        var v = (minor / Math.pow(10, decimals)).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        return currency + ' ' + v;
    };
    Chart.defaults.font.family = css('--font-num') + ', ' + css('--font-ar') + ', sans-serif';
    var chart = new Chart(el, {
        type: 'bar',
        data: { labels: data.labels, datasets: [{ label: labels[current], data: data[current], backgroundColor: css('--brand'), hoverBackgroundColor: css('--brand-hover'), borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 22 }] },
        options: {
            maintainAspectRatio: false,
            locale: 'en-US',
            plugins: {
                legend: { display: false },
                tooltip: { rtl: document.dir === 'rtl', displayColors: false, padding: 10, callbacks: { label: function (c) { return isMoney(current) ? fmt(c.raw) : ticketsTpl.replace(':count', c.raw); } } }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, border: { display: false }, ticks: { maxTicksLimit: 5, callback: function (v) { return isMoney(current) ? (v / Math.pow(10, decimals)).toLocaleString('en-US') : v; } } }
            },
            interaction: { mode: 'index', intersect: false }
        }
    });
    function paint() {
        var ds = chart.data.datasets[0];
        ds.backgroundColor = css('--brand');
        ds.hoverBackgroundColor = css('--brand-hover');
        chart.options.scales.x.ticks.color = css('--muted');
        chart.options.scales.y.ticks.color = css('--muted');
        chart.options.scales.y.grid.color = css('--hairline');
        chart.options.plugins.tooltip.backgroundColor = css('--inverse');
        chart.options.plugins.tooltip.titleColor = css('--on-inverse');
        chart.options.plugins.tooltip.bodyColor = css('--on-inverse');
        chart.update();
    }
    paint();
    document.addEventListener('themechange', paint);
    document.querySelectorAll('[data-chart-switch] button').forEach(function (b) {
        b.addEventListener('click', function () {
            current = b.dataset.series;
            document.querySelectorAll('[data-chart-switch] button').forEach(function (x) { x.classList.toggle('active', x === b); });
            chart.data.datasets[0].data = data[current];
            chart.data.datasets[0].label = labels[current];
            chart.update();
        });
    });
})();
</script>
@endpush
