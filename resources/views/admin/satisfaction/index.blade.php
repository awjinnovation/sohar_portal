@extends('layouts.admin')

@section('title', 'راضي لقياس رضا الزوار')
@section('page-title', 'راضي لقياس رضا الزوار')
@section('page-subtitle', 'إجابات الزوار عن استبيان الرضا في التطبيق، بحسب الفترة والفعالية')
@php
    $pageIcon = 'emoji-smile';
    $breadcrumbs = ['رضا الزوار' => null];
    // score => [label, bootstrap icon, tone]
    $faces = [
        5 => ['راضٍ جدًا', 'emoji-laughing', 'success'],
        4 => ['راضٍ', 'emoji-smile', 'success'],
        3 => ['محايد', 'emoji-neutral', 'neutral'],
        2 => ['غير راضٍ', 'emoji-frown', 'warning'],
        1 => ['غير راضٍ إطلاقًا', 'emoji-angry', 'error'],
    ];
    $distTotal = max(1, array_sum($distribution));
    $fmtAvg = fn (?float $v) => $v === null ? '—' : number_format($v, 1);
    // Isolated so the stat tile shows "4.3" as text (it would round a numeric value).
    $fmtStat = fn (?float $v) => $v === null ? '—' : "\u{2066}".number_format($v, 1)."\u{2069}";
    $i18n = [
        'responses' => __('الإجابات'),
        'average' => __('متوسط التقييم'),
    ];
@endphp
@section('page-actions')
    @can('manage settings')
        <a href="{{ route('admin.branding.edit') }}#sec-radi" class="btn"><i class="bi bi-sliders"></i> {{ __('إعدادات الاستبيان') }}</a>
    @endcan
    <a href="{{ route('admin.satisfaction.export', $report->query()) }}" class="btn btn-tonal" data-export><i class="bi bi-download"></i> {{ __('تصدير CSV') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    @unless($moduleOn)
        <div class="alert alert-warning d-flex gap-2 align-items-start"><i class="bi bi-info-circle-fill"></i>
            <span>{{ __('استبيان الرضا غير مفعّل لدى هذه المنظمة، فلا يظهر في التطبيق. فعّل وحدة «راضي» من الهوية والإعدادات.') }}</span></div>
    @endunless

    <form method="GET" class="sat-filters x-card mb-4" role="search">
        <div class="field">
            <label class="form-label" for="satFrom">{{ __('من تاريخ') }}</label>
            <input type="date" name="from" id="satFrom" value="{{ $report->from }}" class="form-control">
        </div>
        <div class="field">
            <label class="form-label" for="satTo">{{ __('إلى تاريخ') }}</label>
            <input type="date" name="to" id="satTo" value="{{ $report->to }}" class="form-control">
        </div>
        <div class="field flex-grow-1">
            <label class="form-label" for="satEvent">{{ __('الفعالية') }}</label>
            <select name="event" id="satEvent" class="form-select">
                <option value="">{{ __('كل الفعاليات') }}</option>
                <option value="none" @selected($report->event === 'none')>{{ __('تقييم عام دون فعالية') }}</option>
                @foreach($events as $ev)
                    <option value="{{ $ev->id }}" @selected((string) $report->event === (string) $ev->id)>{{ \App\Services\Satisfaction\SatisfactionReport::eventTitle($ev) }}</option>
                @endforeach
            </select>
        </div>
        @if($report->score)<input type="hidden" name="score" value="{{ $report->score }}">@endif
        <div class="d-flex gap-2 align-items-end">
            <button class="btn btn-tonal" type="submit"><i class="bi bi-funnel"></i> {{ __('تطبيق') }}</button>
            @if(request()->query())
                <a href="{{ route('admin.satisfaction.index') }}" class="btn btn-link btn-sm">{{ __('مسح') }}</a>
            @endif
        </div>
    </form>

    <div class="stat-grid">
        <x-admin.stat-tile label="الإجابات" :value="$kpis['responses']" icon="chat-square-heart" tone="brand" :hint="__('اليوم: :count', ['count' => number_format($kpis['today'])])" />
        <x-admin.stat-tile label="متوسط التقييم" :value="$fmtStat($kpis['average'])" icon="star-half" tone="points" hint="من 5" />
        <x-admin.stat-tile label="نسبة الرضا" :value="$kpis['csat'] === null ? '—' : $kpis['csat'].'%'" icon="emoji-smile" tone="success" hint="نسبة من اختاروا راضٍ أو راضٍ جدًا" />
        <x-admin.stat-tile label="التعليقات" :value="$kpis['comments']" icon="chat-left-text" tone="info" :hint="__('يقبلون التواصل: :count', ['count' => number_format($kpis['contact'])])" />
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <x-admin.card title="الإجابات اليومية" icon="bar-chart" subtitle="عدد الإجابات ومتوسط التقييم لكل يوم في الفترة">
                @if($kpis['responses'] > 0)
                    <div class="chart-box" style="height: 280px"><canvas id="satChart" role="img" aria-label="{{ __('رسم بياني للإجابات اليومية ومتوسط التقييم') }}"></canvas></div>
                @else
                    <x-admin.empty-state icon="emoji-neutral" title="لا توجد إجابات في هذه الفترة" text="تظهر هنا إجابات الزوار فور إرسالها من التطبيق." />
                @endif
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="توزيع التقييمات" icon="bar-chart-steps" :flush="true">
                <ul class="tx-list">
                    @foreach($distribution as $score => $count)
                        @php [$fLabel, $fIcon, $fTone] = $faces[$score]; @endphp
                        <li class="tx-item d-block" data-score-row="{{ $score }}">
                            <div class="d-flex align-items-center gap-2">
                                <span class="tx-ico tone-{{ $fTone }} sat-face"><i class="bi bi-{{ $fIcon }}"></i></span>
                                <span class="fw-bold flex-grow-1">{{ __($fLabel) }}</span>
                                <span class="num">{{ number_format($count) }}</span>
                                <span class="num text-muted small sat-pct">{{ round($count * 100 / $distTotal) }}%</span>
                            </div>
                            <div class="sat-bar"><i class="tone-{{ $fTone }}" style="width: {{ round($count * 100 / $distTotal) }}%"></i></div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <x-admin.card title="متوسط كل جانب" icon="list-stars" subtitle="تقييم الزوار للجوانب التفصيلية، وهي اختيارية" :flush="true">
                <ul class="tx-list">
                    @forelse($aspects as $aspect)
                        <li class="tx-item d-block" data-aspect="{{ $aspect['key'] }}">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold flex-grow-1">{{ $aspect['label'] }}</span>
                                <span class="num fw-bold">{{ $fmtAvg($aspect['average']) }}</span>
                                <span class="text-muted small">{{ __('عدد التقييمات: :count', ['count' => number_format($aspect['count'])]) }}</span>
                            </div>
                            <div class="sat-bar"><i class="tone-brand" style="width: {{ $aspect['average'] ? round($aspect['average'] * 20) : 0 }}%"></i></div>
                        </li>
                    @empty
                        <li class="tx-item"><span class="text-muted">{{ __('لم تُضبط جوانب للتقييم.') }}</span></li>
                    @endforelse
                </ul>
            </x-admin.card>
        </div>
        <div class="col-xl-6">
            <x-admin.card title="حسب الفعالية" icon="calendar-event" :flush="true">
                <div class="x-table-wrap table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __('الفعالية') }}</th><th class="text-end">{{ __('الإجابات') }}</th><th class="text-end">{{ __('المتوسط') }}</th><th class="text-end">{{ __('نسبة الرضا') }}</th></tr></thead>
                        <tbody>
                        @forelse($perEvent as $row)
                            <tr data-event-row="{{ $row['event_id'] ?? 'none' }}">
                                <td class="cell-title">
                                    <a href="{{ route('admin.satisfaction.index', $report->query(['event' => $row['event_id'] ?? 'none', 'score' => null])) }}">{{ $row['title'] ?? __('تقييم عام دون فعالية') }}</a>
                                </td>
                                <td class="num text-end">{{ number_format($row['responses']) }}</td>
                                <td class="num text-end">{{ $fmtAvg($row['average']) }}</td>
                                <td class="num text-end">{{ $row['csat'] }}%</td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="4" icon="calendar-event" title="لا توجد إجابات" />
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.data-table title="تعليقات الزوار" icon="chat-left-text" :count="$comments->total()" :paginator="$comments">
        <x-slot:filters>
            <input type="hidden" name="from" value="{{ $report->from }}">
            <input type="hidden" name="to" value="{{ $report->to }}">
            @if($report->event)<input type="hidden" name="event" value="{{ $report->event }}">@endif
            <select name="score" class="form-select" aria-label="{{ __('التقييم العام') }}">
                <option value="">{{ __('كل التقييمات') }}</option>
                @foreach($faces as $score => [$fLabel])
                    <option value="{{ $score }}" @selected($report->score === $score)>{{ __($fLabel) }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th>{{ __('التقييم') }}</th>
            <th>{{ __('التعليق') }}</th>
            <th>{{ __('الفعالية') }}</th>
            <th>{{ __('التواصل') }}</th>
            <th>{{ __('الوقت') }}</th>
        </x-slot:head>

        @forelse($comments as $r)
            @php [$fLabel, $fIcon, $fTone] = $faces[$r->score] ?? $faces[3]; @endphp
            <tr data-response="{{ $r->id }}">
                <td class="text-nowrap"><span class="pill pill-sm pill-{{ $fTone === 'success' ? 'success' : ($fTone === 'error' ? 'error' : ($fTone === 'warning' ? 'warning' : 'neutral')) }}"><i class="bi bi-{{ $fIcon }}"></i>{{ __($fLabel) }}</span></td>
                <td class="sat-comment">
                    <div>{{ $r->comment }}</div>
                    @if($r->aspects)
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach((array) $r->aspects as $ak => $av)
                                <span class="pill pill-sm pill-neutral">{{ $aspectLabels[$ak] ?? $ak }} <span class="num">{{ $av }}</span></span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="small">{{ \App\Services\Satisfaction\SatisfactionReport::eventTitle($r->event) ?? __('تقييم عام دون فعالية') }}</td>
                <td class="small">
                    @if($r->contact_ok && $r->user)
                        <div class="cell-title">{{ $r->user->name ?: __('زائر') }}</div>
                        <div class="cell-sub ltr text-start num text-nowrap">{{ $r->user->phone_number }}</div>
                    @else
                        <span class="text-muted">{{ __('لا يطلب التواصل') }}</span>
                    @endif
                </td>
                <td class="num small text-muted text-nowrap"><span class="ltr">{{ $r->created_at->copy()->setTimezone($tz)->format('Y-m-d H:i') }}</span></td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="5" icon="chat-left-text" title="لا توجد تعليقات" text="تظهر هنا التعليقات التي يكتبها الزوار مع تقييمهم." />
        @endforelse
    </x-admin.data-table>
@endsection

@push('styles')
<style>
    .sat-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; padding: 16px; }
    .sat-filters .field { margin: 0; min-width: 150px; }
    .sat-face { width: 32px; height: 32px; font-size: 17px; }
    .sat-pct { min-width: 38px; text-align: end; }
    .sat-bar { height: 6px; border-radius: 3px; background: var(--surface-high, #E5E7EB); overflow: hidden; margin-top: 8px; }
    .sat-bar i { display: block; height: 100%; border-radius: 3px; }
    .sat-bar i.tone-success { background: var(--success, #16A34A); }
    .sat-bar i.tone-warning { background: var(--warning, #F59E0B); }
    .sat-bar i.tone-error { background: var(--error, #DC2626); }
    .sat-bar i.tone-neutral { background: var(--muted, #6B7280); }
    .sat-bar i.tone-brand { background: var(--brand, #2F8E80); }
    .sat-comment { min-width: 260px; max-width: 560px; white-space: pre-line; }
</style>
@endpush

@if($kpis['responses'] > 0)
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var T = @json($i18n);
    var trend = @json($trend);
    var css = function (v, f) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim() || f; };
    var chart = null;
    function render() {
        var el = document.getElementById('satChart');
        if (!el || !window.Chart) return;
        Chart.defaults.font.family = css('--font-num', 'Inter') + ', ' + css('--font-ar', 'Cairo') + ', sans-serif';
        chart = new Chart(el, {
            data: {
                labels: trend.labels,
                datasets: [
                    { type: 'bar', label: T.responses, data: trend.responses, backgroundColor: css('--brand', '#2F8E80'), borderRadius: 3, maxBarThickness: 18, yAxisID: 'y' },
                    { type: 'line', label: T.average, data: trend.average, borderColor: css('--accent', '#E5883C'), backgroundColor: css('--accent', '#E5883C'), pointRadius: 2, borderWidth: 2, tension: .3, spanGaps: true, yAxisID: 'y1' }
                ]
            },
            options: {
                maintainAspectRatio: false, animation: false, locale: 'en-US',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, color: css('--muted', '#6B7280') } }, tooltip: { rtl: document.dir === 'rtl', mode: 'index', intersect: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: css('--muted', '#6B7280'), maxTicksLimit: 12 } },
                    y: { beginAtZero: true, position: 'left', ticks: { precision: 0, color: css('--muted', '#6B7280') }, grid: { color: css('--hairline', '#E5E7EB') } },
                    y1: { min: 1, max: 5, position: 'right', ticks: { stepSize: 1, color: css('--muted', '#6B7280') }, grid: { display: false } }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });
    }
    render();
    document.addEventListener('themechange', function () { if (chart) { chart.destroy(); chart = null; render(); } });
})();
</script>
@endpush
@endif
