{{-- Admin → Gates → Morning reconciliation (GateReconciliationController, docs/gate_reconciliation.md). --}}
@extends('layouts.admin')
@use('App\Support\AdminLabels')

@section('title', 'مطابقة مبيعات البوابات')
@section('page-title', 'مطابقة مبيعات البوابات')
@section('page-subtitle', 'مراجعة الصباح ليوم عمل البوابات: المبالغ حسب البوابة والموظف وطريقة الدفع، والحالات التي تحتاج إلى معالجة')
@php
    $pageIcon = 'clipboard-check';
    $breadcrumbs = $canGates
        ? ['البوابات' => route('admin.gates.index'), 'مبيعات البوابات' => route('admin.gates.sales.index'), 'مطابقة مبيعات البوابات' => null]
        : ['البوابات' => null, 'مطابقة مبيعات البوابات' => null];
    $tz = \App\Support\TenantTime::zone($brand ?? null);
    $channelLabels = ['link_thawani' => 'رابط الدفع عبر ثواني', 'link_amwal' => 'رابط الدفع عبر أموال', 'pos' => 'جهاز البطاقة', 'other' => 'أخرى'];
    $kindLabels = [
        'paid_no_tickets' => ['دُفع المبلغ ولم تصدر تذاكر', 'error', 'استردّ المبلغ من صفحة الطلب أو الدفعة، وإن كان الدفع بجهاز البطاقة فأعده على الجهاز.'],
        'payment_review' => ['دفعة تحتاج إلى مراجعة', 'warning', 'طابق الدفعة مع بوابة تجار ثواني أو أموال، ثم سجّل مراجعتها أو استردّ المبلغ.'],
        'open_attempt' => ['محاولة دفع مفتوحة لدى بوابة الدفع', 'warning', 'اضغط «تحقّق من بوابة الدفع الآن». تُلغى المحاولة غير المدفوعة تلقائيًا بعد ساعتين، وتبقى محاولة أموال مراقبة.'],
        'order_pending' => ['طلب ما زال بانتظار الدفع', 'neutral', 'ينتهي تلقائيًا عند انتهاء صلاحية الرابط.'],
        'pos_unverified' => ['بيع بجهاز البطاقة بإيصال لم يُفحص كاملًا', 'warning', 'طابق الإيصال مع كشف الجهاز، ثم سجّل المراجعة أو ألغِ العملية من صفحة الطلب.'],
        'pos_held' => ['إيصال ينتظر قرار المشرف', 'warning', 'إن خُصم المبلغ فاعتمد الإيصال من صفحة الطلب، وإلا سجّل المراجعة.'],
        'pos_rejected' => ['رُفض الإيصال ولم يُدفع الطلب', 'error', 'راجع كشف الجهاز: إن خُصم المبلغ فاعتمد الإيصال أو أعد المبلغ للزائر.'],
        'pos_no_receipt' => ['بيع بجهاز البطاقة بلا صورة إيصال', 'warning', 'اسأل الموظف وراجع كشف الجهاز، وإن خُصم المبلغ فأعده للزائر.'],
    ];
    $checkableKinds = \App\Services\Gate\GateReconciliation::CHECKABLE_KINDS;
    $channels = $report['channels_used'];
    $exceptions = $report['exceptions'];
    $prev = \Carbon\Carbon::parse($date)->subDay()->toDateString();
    $next = \Carbon\Carbon::parse($date)->addDay()->toDateString();
    $fromLocal = $report['from']->copy()->setTimezone($tz);
    $toLocal = $report['to']->copy()->setTimezone($tz);
@endphp

@section('page-actions')
    <a href="{{ route('admin.gates.reconciliation.export', ['date' => $date]) }}" class="btn" data-export><i class="bi bi-filetype-csv"></i> {{ __('تصدير CSV') }}</a>
    <form action="{{ route('admin.gates.reconciliation.check', ['date' => $date]) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary" data-busy data-check-count="{{ $checkable }}" @disabled($checkable === 0)>
            <i class="bi bi-arrow-repeat"></i> {{ __('تحقّق من بوابة الدفع الآن') }} <span class="num">({{ $checkable }})</span>
        </button>
    </form>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.card :flush="false">
        <form method="GET" action="{{ route('admin.gates.reconciliation') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label class="form-label" for="recDate">{{ __('يوم العمل') }}</label>
                <input type="date" id="recDate" name="date" value="{{ $date }}" max="{{ $today }}" class="form-control">
            </div>
            <button type="submit" class="btn">{{ __('عرض') }}</button>
            <a href="{{ route('admin.gates.reconciliation', ['date' => $prev]) }}" class="btn btn-link"><i class="bi bi-chevron-right"></i> {{ __('اليوم السابق') }}</a>
            @if($next <= $today)
                <a href="{{ route('admin.gates.reconciliation', ['date' => $next]) }}" class="btn btn-link">{{ __('اليوم التالي') }} <i class="bi bi-chevron-left"></i></a>
            @endif
        </form>
        <p class="form-text mb-0 mt-2">
            {{ __('يشمل الطلبات المنشأة من :from حتى :to بتوقيت المنظمة، ويبدأ يوم عمل البوابات عند الساعة :hour.', ['from' => $fromLocal->format('Y-m-d H:i'), 'to' => $toLocal->format('Y-m-d H:i'), 'hour' => sprintf('%02d:00', $rollover)]) }}
        </p>
    </x-admin.card>

    <div class="stat-grid">
        <x-admin.stat-tile label="إجمالي المبيعات المدفوعة" :money="$report['totals']['total']" icon="cash-coin" tone="success" :hint="__('الطلبات المدفوعة: :count', ['count' => number_format($report['totals']['paid'])])" />
        @foreach(['link_thawani' => 'qr-code', 'link_amwal' => 'qr-code', 'pos' => 'credit-card'] as $ch => $icon)
            <x-admin.stat-tile :label="$channelLabels[$ch]" :money="$report['totals']['amounts'][$ch]" :icon="$icon" tone="brand"
                :hint="__('الطلبات: :count', ['count' => number_format($report['totals']['counts'][$ch])])" data-channel="{{ $ch }}" />
        @endforeach
        <x-admin.stat-tile label="حالات تحتاج إلى معالجة" :value="count($exceptions)" icon="exclamation-triangle" :tone="$exceptions ? 'warning' : 'success'" data-exceptions="{{ count($exceptions) }}" />
    </div>

    {{-- The to-do list first. --}}
    <x-admin.card title="حالات تحتاج إلى معالجة" icon="list-check" :flush="true" subtitle="تختفي الحالة من القائمة بعد معالجتها في صفحتها">
        <div class="x-table-wrap table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr>
                    <th>{{ __('الحالة') }}</th><th>{{ __('رقم الطلب') }}</th><th>{{ __('الدفعة') }}</th><th class="text-end">{{ __('المبلغ') }}</th>
                    <th>{{ __('البوابة') }}</th><th>{{ __('الوقت') }}</th><th class="text-end">{{ __('الإجراءات') }}</th>
                </tr></thead>
                <tbody>
                @forelse($exceptions as $e)
                    @php
                        [$label, $tone, $help] = $kindLabels[$e['kind']];
                        $o = $e['order'];
                        $p = $e['payment'];
                    @endphp
                    <tr data-exception="{{ $e['kind'] }}" data-order="{{ $o?->reference }}" data-payment="{{ $p?->transaction_id }}">
                        <td>
                            <x-admin.status-pill :variant="$tone" :label="__($label)" size="sm" />
                            @if($e['kind'] === 'payment_review' && $e['reason'])
                                <div class="small mt-1">{{ AdminLabels::label(AdminLabels::PAYMENT_REVIEW_REASONS, $e['reason']) }}</div>
                            @elseif($e['kind'] === 'pos_rejected' && $e['reason'])
                                <div class="small mt-1 ltr text-start text-muted">{{ $e['reason'] }}</div>
                            @endif
                            <div class="cell-sub">{{ __($help) }}</div>
                        </td>
                        <td class="text-nowrap">
                            @if($o && $canGates)<a href="{{ route('admin.gates.sales.show', $o) }}" class="font-monospace small ltr">{{ $o->reference }}</a>@elseif($o)<span class="font-monospace small ltr">{{ $o->reference }}</span>@else — @endif
                        </td>
                        <td class="text-nowrap small">
                            @if($p)
                                @if($canPayments)<a href="{{ route('admin.payments.show', $p) }}" class="font-monospace ltr">{{ $p->transaction_id }}</a>@else<span class="font-monospace ltr">{{ $p->transaction_id }}</span>@endif
                                <div class="cell-sub">{{ $p->method === 'pos' ? __('جهاز البطاقة') : (\App\Services\Payments\PaymentGatewayManager::NAMES[$p->gateway]['ar'] ?? $p->gateway) }}</div>
                            @else — @endif
                        </td>
                        <td class="text-end"><x-admin.money :minor="$e['amount_minor']" class="fw-bold" /></td>
                        <td class="small">{{ $o?->gate?->localizedName() ?? '—' }}<div class="cell-sub">{{ $o?->staff?->name }}</div></td>
                        <td class="num small text-muted text-nowrap"><span class="ltr">{{ ($p?->created_at ?? $o?->created_at)?->copy()->setTimezone($tz)->format('H:i') }}</span></td>
                        <td class="text-end">
                            <div class="row-actions">
                                @if($o && $canGates)<a href="{{ route('admin.gates.sales.show', $o) }}" class="icon-btn brand" title="{{ __('عرض الطلب') }}"><i class="bi bi-eye"></i></a>@endif
                                @if($p && $canPayments)<a href="{{ route('admin.payments.show', $p) }}" class="icon-btn" title="{{ __('عرض الدفعة') }}"><i class="bi bi-credit-card-2-front"></i></a>@endif
                                @if($o && in_array($e['kind'], $checkableKinds, true))
                                    <form action="{{ route('admin.gates.reconciliation.checked', $o) }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="date" value="{{ $date }}">
                                        <button type="submit" class="btn btn-sm" data-mark-checked="{{ $o->reference }}">{{ __('سجّل المراجعة') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="7" icon="check2-circle" title="لا توجد حالات تحتاج إلى معالجة" text="طابق الإجماليات أدناه مع بوابات تجار الدفع وكشوف أجهزة البطاقة." />
                @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="row g-4 mb-4">
        @foreach([['per_gate', 'حسب البوابة', 'door-open', 'البوابة', 'بلا بوابة'], ['per_staff', 'حسب الموظف', 'person-badge', 'الموظف', 'غير معروف']] as [$key, $title, $icon, $col, $none])
            <div class="col-12">
                <x-admin.card :title="$title" :icon="$icon" :flush="true">
                    <div class="x-table-wrap table-responsive">
                        <table class="table align-middle mb-0" data-totals="{{ $key }}">
                            <thead><tr>
                                <th>{{ __($col) }}</th><th class="text-end">{{ __('عدد الطلبات') }}</th><th class="text-end">{{ __('المدفوعة') }}</th>
                                @foreach($channels as $ch)<th class="text-end">{{ __($channelLabels[$ch]) }}</th>@endforeach
                                <th class="text-end">{{ __('الإجمالي') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($report[$key] as $row)
                                <tr>
                                    <td class="cell-title">{{ $row['label'] ?? __($none) }}</td>
                                    <td class="num text-end">{{ number_format($row['orders']) }}</td>
                                    <td class="num text-end">{{ number_format($row['paid']) }}</td>
                                    @foreach($channels as $ch)
                                        <td class="text-end"><x-admin.money :minor="$row['amounts'][$ch]" /><div class="cell-sub num">{{ number_format($row['counts'][$ch]) }}</div></td>
                                    @endforeach
                                    <td class="text-end"><x-admin.money :minor="$row['total']" class="fw-bold" /></td>
                                </tr>
                            @empty
                                <x-admin.empty-row :colspan="4 + count($channels)" icon="cart" title="لا توجد مبيعات" />
                            @endforelse
                            </tbody>
                            @if($report[$key])
                                <tfoot><tr class="fw-bold">
                                    <td>{{ __('الإجمالي') }}</td>
                                    <td class="num text-end">{{ number_format($report['totals']['orders']) }}</td>
                                    <td class="num text-end">{{ number_format($report['totals']['paid']) }}</td>
                                    @foreach($channels as $ch)<td class="text-end" data-total="{{ $key }}-{{ $ch }}"><x-admin.money :minor="$report['totals']['amounts'][$ch]" /></td>@endforeach
                                    <td class="text-end"><x-admin.money :minor="$report['totals']['total']" /></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                </x-admin.card>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <x-admin.card title="المطابقة مع بوابات تجار الدفع" icon="bank" :flush="true" subtitle="كل محاولة دفعتها بوابة الدفع لطلبات هذا اليوم، ومنها المدفوعة مرتين أو بعد انتهاء الصلاحية">
                <div class="x-table-wrap table-responsive">
                    <table class="table align-middle mb-0" data-portal>
                        <thead><tr><th>{{ __('بوابة الدفع') }}</th><th class="text-end">{{ __('المحاولات المدفوعة') }}</th><th class="text-end">{{ __('المبلغ المدفوع') }}</th><th class="text-end">{{ __('المسترد عبر بوابة الدفع') }}</th><th class="text-end">{{ __('الصافي') }}</th></tr></thead>
                        <tbody>
                        @foreach(['thawani' => 'ثواني', 'amwal' => 'أموال'] as $g => $gName)
                            @php $pt = $report['portal'][$g]; @endphp
                            <tr data-portal-gateway="{{ $g }}">
                                <td class="cell-title">{{ __($gName) }}</td>
                                <td class="num text-end">{{ number_format($pt['captured_count']) }}</td>
                                <td class="text-end"><x-admin.money :minor="$pt['captured_minor']" /></td>
                                <td class="text-end"><x-admin.money :minor="$pt['refunded_minor']" /><div class="cell-sub num">{{ number_format($pt['refunded_count']) }}</div></td>
                                <td class="text-end"><x-admin.money :minor="$pt['captured_minor'] - $pt['refunded_minor']" class="fw-bold" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-xl-6">
            <x-admin.card title="المطابقة مع كشوف أجهزة البطاقة" icon="credit-card" :flush="true" subtitle="الإيصالات المقبولة حسب رقم الجهاز المقروء من الإيصال">
                <div class="x-table-wrap table-responsive">
                    <table class="table align-middle mb-0" data-terminals>
                        <thead><tr><th>{{ __('رقم الجهاز') }}</th><th class="text-end">{{ __('عدد العمليات') }}</th><th class="text-end">{{ __('المبلغ') }}</th><th class="text-end">{{ __('ألغيت لاحقًا') }}</th></tr></thead>
                        <tbody>
                        @forelse($report['terminals'] as $t)
                            <tr data-terminal="{{ $t['terminal_id'] ?? 'unknown' }}">
                                <td class="font-monospace ltr text-start">{{ $t['terminal_id'] ?? __('لم يُقرأ') }}</td>
                                <td class="num text-end">{{ number_format($t['count']) }}</td>
                                <td class="text-end"><x-admin.money :minor="$t['minor']" class="fw-bold" /></td>
                                <td class="text-end"><x-admin.money :minor="$t['refunded_minor']" /><div class="cell-sub num">{{ number_format($t['refunded_count']) }}</div></td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="4" icon="credit-card" title="لا توجد مبيعات بجهاز البطاقة" />
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
