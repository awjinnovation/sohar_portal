@extends('layouts.admin')

@section('title', 'سجل بوابة الدفع')
@section('page-title', 'سجل بوابة الدفع')
@section('page-subtitle', 'كل إشعار يصل من البوابة وكل طلب يُرسل إليها، مع النتيجة والمدة')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'hdd-network';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المدفوعات' => route('admin.payments.index'), 'سجل بوابة الدفع' => null];
    $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
@section('page-actions')
    <a href="{{ route('admin.payments.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="طلبات آخر 24 ساعة" :value="$stats['calls_total']" icon="arrow-left-right" tone="brand" />
        <x-admin.stat-tile label="متوسط زمن الاستجابة" :value="$stats['avg_ms'].' ms'" icon="stopwatch" tone="info" />
        <x-admin.stat-tile label="طلبات فشلت (24 ساعة)" :value="$stats['calls_failed']" icon="wifi-off" :tone="$stats['calls_failed'] ? 'error' : 'neutral'"
            :href="route('admin.payments.gateway-log', ['tab' => 'calls', 'status' => 'server_error'])" />
        <x-admin.stat-tile label="إشعارات فشلت أو رُفضت (24 ساعة)" :value="$stats['webhooks_failed']" icon="bell-slash" :tone="$stats['webhooks_failed'] ? 'error' : 'neutral'"
            :href="route('admin.payments.gateway-log', ['tab' => 'webhooks', 'status' => 'failed'])" />
    </div>

    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item"><a class="nav-link {{ $tab === 'webhooks' ? 'active' : '' }}" href="{{ route('admin.payments.gateway-log', ['tab' => 'webhooks']) }}"><i class="bi bi-bell"></i> {{ __('الإشعارات الواردة') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'calls' ? 'active' : '' }}" href="{{ route('admin.payments.gateway-log', ['tab' => 'calls']) }}"><i class="bi bi-arrow-left-right"></i> {{ __('الطلبات الصادرة') }}</a></li>
    </ul>

    @if($tab === 'webhooks')
        <x-admin.data-table title="الإشعارات الواردة" icon="bell" :count="$rows->total()" :paginator="$rows">
            <x-slot:filters>
                <input type="hidden" name="tab" value="webhooks">
                <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                    <option value="">{{ __('كل الحالات') }}</option>
                    @foreach(AdminLabels::WEBHOOK_STATUSES as $sv => [$sl])
                        <option value="{{ $sv }}" @selected($filters['status'] === $sv)>{{ __($sl) }}</option>
                    @endforeach
                </select>
            </x-slot:filters>
            <x-slot:head>
                <th style="width: 56px">#</th>
                <th>{{ __('الحدث') }}</th>
                <th>{{ __('الدفعة') }}</th>
                <th>{{ __('التوقيع') }}</th>
                <th>{{ __('الحالة') }}</th>
                <th>{{ __('المحاولات') }}</th>
                <th>{{ __('الوقت') }}</th>
                <th class="text-end">{{ __('الإجراءات') }}</th>
            </x-slot:head>
            @forelse($rows as $hook)
                <tr>
                    <td class="num text-muted">{{ $hook->id }}</td>
                    <td>
                        <span class="font-monospace small ltr">{{ $hook->event_type ?? '—' }}</span>
                        <details class="small mt-1"><summary class="text-muted">{{ __('المحتوى') }}</summary>
                            <pre class="x-json">{{ $json($hook->payload()) }}</pre>
                            @if($hook->headers)<pre class="x-json">{{ $json($hook->headers) }}</pre>@endif
                            @if($hook->last_error)<div class="text-danger ltr text-start">{{ $hook->last_error }}</div>@endif
                        </details>
                    </td>
                    <td>
                        @if($hook->payment_id)
                            <a href="{{ route('admin.payments.show', $hook->payment_id) }}" class="num">#{{ $hook->payment_id }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($hook->signature_valid === true)<span class="pill pill-sm pill-success">{{ __('صحيح') }}</span>
                        @elseif($hook->signature_valid === false)<span class="pill pill-sm pill-error">{{ __('غير صحيح') }}</span>
                        @else<span class="pill pill-sm pill-neutral">{{ __('بلا مفتاح') }}</span>@endif
                    </td>
                    <td><x-admin.status-pill domain="webhook" :status="$hook->status" /></td>
                    <td class="num">{{ $hook->attempts }} · {{ $hook->deliveries }}</td>
                    <td class="num text-muted"><span class="ltr">{{ $hook->created_at?->format('Y-m-d H:i:s') }}</span></td>
                    <td class="text-end">
                        @can('refund payments')
                            @if(in_array($hook->status, ['failed', 'received', 'processed'], true))
                                <form action="{{ route('admin.payments.webhooks.replay', $hook) }}" method="POST" class="d-inline">@csrf
                                    <button type="submit" class="icon-btn brand" title="{{ __('أعد المعالجة') }}" data-busy><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <x-admin.empty-row :colspan="8" icon="bell" title="لا توجد إشعارات" text="ستظهر هنا إشعارات بوابة الدفع فور وصولها." />
            @endforelse
        </x-admin.data-table>
    @else
        <x-admin.data-table title="الطلبات الصادرة" icon="arrow-left-right" :count="$rows->total()" :paginator="$rows">
            <x-slot:filters>
                <input type="hidden" name="tab" value="calls">
                <select name="status" class="form-select" aria-label="{{ __('النتيجة') }}">
                    <option value="">{{ __('كل النتائج') }}</option>
                    @foreach(AdminLabels::GATEWAY_CALL_OUTCOMES as $ov => [$ol])
                        <option value="{{ $ov }}" @selected($filters['status'] === $ov)>{{ __($ol) }}</option>
                    @endforeach
                </select>
                <select name="operation" class="form-select ltr" aria-label="{{ __('العملية') }}">
                    <option value="">{{ __('كل العمليات') }}</option>
                    @foreach(['create_session', 'get_session', 'cancel_session', 'get_payments', 'create_refund'] as $op)
                        <option value="{{ $op }}" @selected($filters['operation'] === $op)>{{ $op }}</option>
                    @endforeach
                </select>
            </x-slot:filters>
            <x-slot:head>
                <th style="width: 56px">#</th>
                <th>{{ __('العملية') }}</th>
                <th>{{ __('الدفعة') }}</th>
                <th>{{ __('المحاولة') }}</th>
                <th>{{ __('النتيجة') }}</th>
                <th>HTTP</th>
                <th>{{ __('المدة') }}</th>
                <th>{{ __('الوقت') }}</th>
            </x-slot:head>
            @forelse($rows as $call)
                <tr>
                    <td class="num text-muted">{{ $call->id }}</td>
                    <td>
                        <span class="font-monospace small ltr">{{ $call->operation }}</span>
                        @if($call->mode)<span class="pill pill-sm {{ $call->mode === 'production' ? 'pill-success' : 'pill-neutral' }}">{{ $call->mode === 'production' ? __('تشغيل') : __('تجريبي') }}</span>@endif
                        <details class="small mt-1"><summary class="text-muted">{{ __('التفاصيل') }}</summary>
                            <div class="ltr text-start text-muted mt-1">{{ $call->method }} {{ $call->url }}</div>
                            @if($call->request_body)<pre class="x-json">{{ $json($call->request_body) }}</pre>@endif
                            @if($call->response_body)<pre class="x-json">{{ $call->response_body }}</pre>@endif
                            @if($call->error)<div class="text-danger ltr text-start">{{ $call->error }}</div>@endif
                        </details>
                    </td>
                    <td>
                        @if($call->payment_id)
                            <a href="{{ route('admin.payments.show', $call->payment_id) }}" class="num">#{{ $call->payment_id }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="num">{{ $call->attempt }}</td>
                    <td><x-admin.status-pill domain="gwcall" :status="$call->outcome" /></td>
                    <td class="num">{{ $call->response_status ?? '—' }}</td>
                    <td class="num text-muted">{{ $call->duration_ms !== null ? number_format($call->duration_ms).' ms' : '—' }}</td>
                    <td class="num text-muted"><span class="ltr">{{ $call->created_at?->format('Y-m-d H:i:s') }}</span></td>
                </tr>
            @empty
                <x-admin.empty-row :colspan="8" icon="arrow-left-right" title="لا توجد طلبات" text="ستظهر هنا الطلبات المرسلة إلى بوابة الدفع." />
            @endforelse
        </x-admin.data-table>
    @endif
@endsection
