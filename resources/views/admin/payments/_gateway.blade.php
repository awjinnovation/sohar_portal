{{-- Gateway records of one payment (docs/payments_thawani.md §4): review
     flag, gateway state, timeline, sessions, API calls, webhooks.
     Expects $payment and $gateway (events, sessions, calls, webhooks, refund). --}}
@use('App\Support\AdminLabels')
@php
    $isThawani = $payment->gateway === 'thawani';
    $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $errorTypes = ['failed', 'amount_mismatch', 'gateway_refund_failed', 'gateway_refund_needs_review', 'late_capture', 'duplicate_capture', 'points_retake_failed'];
    $mutedTypes = ['gateway_checked', 'checkout_opened', 'webhook_received', 'check_requested'];
@endphp

@if($payment->needs_review)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="flex-grow-1">
            <div class="fw-bold">{{ __('تحتاج هذه الدفعة إلى مراجعة') }}</div>
            <div class="small">{{ AdminLabels::label(AdminLabels::PAYMENT_REVIEW_REASONS, $payment->review_reason) }}</div>
        </div>
        @can('refund payments')
            <form action="{{ route('admin.payments.review', $payment) }}" method="POST" class="d-flex gap-2 align-items-center">
                @csrf
                <input type="text" name="note" class="form-control form-control-sm" placeholder="{{ __('ملاحظة (اختياري)') }}" maxlength="500">
                <button type="submit" class="btn btn-sm btn-outline-dark text-nowrap" data-busy><i class="bi bi-check2"></i> {{ __('تمت المراجعة') }}</button>
            </form>
        @endcan
    </div>
@endif

@if($payment->method === 'gateway')
<div class="row gx-4">
    <div class="col-xl-5">
        <x-admin.card title="بوابة الدفع" icon="hdd-network" :subtitle="$payment->gateway">
            @if($isThawani)
                <x-slot:actions>
                    <form action="{{ route('admin.payments.reconcile', $payment) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm" data-busy><i class="bi bi-arrow-repeat"></i> {{ __('تحقّق من البوابة الآن') }}</button>
                    </form>
                </x-slot:actions>
            @endif
            <dl class="dl">
                <dt>{{ __('رقم الجلسة') }}</dt><dd><span class="font-monospace small ltr">{{ $payment->gateway_session_id ?? '—' }}</span></dd>
                <dt>{{ __('رقم الدفعة لدى البوابة') }}</dt><dd><span class="font-monospace small ltr">{{ $payment->gateway_payment_id ?? '—' }}</span></dd>
                <dt>{{ __('آخر تحقق') }}</dt><dd class="num"><span class="ltr">{{ $payment->last_checked_at?->format('Y-m-d H:i:s') ?? '—' }}</span></dd>
                <dt>{{ __('التحقق التالي') }}</dt><dd class="num"><span class="ltr">{{ $payment->next_check_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                <dt>{{ __('مرات التحقق') }}</dt><dd class="num">{{ number_format((int) $payment->check_count) }}</dd>
            </dl>

            @if($gateway['sessions']->isNotEmpty())
                <div class="x-table-wrap mt-3">
                    <table class="table align-middle small mb-0">
                        <thead><tr><th>{{ __('الجلسة') }}</th><th>{{ __('البيئة') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('تنتهي') }}</th></tr></thead>
                        <tbody>
                            @foreach($gateway['sessions'] as $session)
                                <tr>
                                    <td><span class="font-monospace ltr">{{ \Illuminate\Support\Str::limit($session->session_id, 22) }}</span></td>
                                    <td><span class="pill pill-sm {{ $session->mode === 'production' ? 'pill-success' : 'pill-neutral' }}">{{ $session->mode === 'production' ? __('تشغيل') : __('تجريبي') }}</span></td>
                                    <td><x-admin.status-pill domain="gwsession" :status="$session->status" size="sm" /></td>
                                    <td class="num text-muted"><span class="ltr">{{ $session->expires_at?->format('m-d H:i') ?? '—' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="col-xl-7">
        <x-admin.card title="سجل الدفعة" icon="clock-history" :subtitle="__('الأحداث: :count', ['count' => $gateway['events']->count()])">
            @if($gateway['events']->isEmpty())
                <x-admin.empty-state icon="clock-history" title="لا توجد أحداث مسجلة" text="تُسجَّل الأحداث للدفعات الجديدة." class="py-3" />
            @else
                <ol class="x-timeline">
                    @foreach($gateway['events'] as $ev)
                        <li class="{{ in_array($ev->type, $errorTypes, true) ? 'tl-error' : (in_array($ev->type, $mutedTypes, true) ? 'tl-muted' : '') }}">
                            <div class="tl-head">
                                <span class="tl-title">{{ AdminLabels::label(AdminLabels::PAYMENT_EVENTS, $ev->type) }}</span>
                                <span class="tl-meta">{{ AdminLabels::label(AdminLabels::PAYMENT_EVENT_SOURCES, $ev->source) }}@if($ev->actor) · {{ $ev->actor->name }}@endif</span>
                                <span class="tl-meta num ms-auto"><span class="ltr">{{ $ev->created_at?->format('Y-m-d H:i:s') }}</span></span>
                            </div>
                            @if($ev->data)
                                <div class="tl-data ltr text-start font-monospace">{{ collect($ev->data)->map(fn ($v, $k) => $k.'='.(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))->implode('  ') }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-admin.card>
    </div>
</div>

<x-admin.card title="طلبات البوابة" icon="arrow-left-right" :flush="true" :subtitle="__('كل محاولة مسجلة، والمفاتيح لا تُحفظ.')">
    @if($gateway['calls']->isEmpty())
        <x-admin.empty-state icon="arrow-left-right" title="لا توجد طلبات" text="لم يُرسل أي طلب إلى البوابة لهذه الدفعة." />
    @else
    <div class="x-table-wrap">
        <table class="table align-middle small">
            <thead><tr><th>{{ __('العملية') }}</th><th>{{ __('المحاولة') }}</th><th>{{ __('النتيجة') }}</th><th>HTTP</th><th>{{ __('المدة') }}</th><th>{{ __('الوقت') }}</th></tr></thead>
            <tbody>
                @foreach($gateway['calls'] as $call)
                    <tr>
                        <td>
                            <span class="font-monospace ltr">{{ $call->operation }}</span>
                            <details class="mt-1">
                                <summary class="text-muted">{{ __('التفاصيل') }}</summary>
                                <div class="ltr text-start small text-muted mt-1">{{ $call->method }} {{ $call->url }}</div>
                                @if($call->request_body)<pre class="x-json">{{ $json($call->request_body) }}</pre>@endif
                                @if($call->response_body)<pre class="x-json">{{ $call->response_body }}</pre>@endif
                                @if($call->error)<div class="text-danger mt-1 ltr text-start">{{ $call->error }}</div>@endif
                            </details>
                        </td>
                        <td class="num">{{ $call->attempt }}</td>
                        <td><x-admin.status-pill domain="gwcall" :status="$call->outcome" size="sm" /></td>
                        <td class="num">{{ $call->response_status ?? '—' }}</td>
                        <td class="num text-muted">{{ $call->duration_ms !== null ? number_format($call->duration_ms).' ms' : '—' }}</td>
                        <td class="num text-muted"><span class="ltr">{{ $call->created_at?->format('m-d H:i:s') }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-admin.card>

<x-admin.card title="إشعارات البوابة" icon="bell" :flush="true">
    @if($gateway['webhooks']->isEmpty())
        <x-admin.empty-state icon="bell" title="لا توجد إشعارات" text="لم يصل من البوابة أي إشعار عن هذه الدفعة." />
    @else
    <div class="x-table-wrap">
        <table class="table align-middle small">
            <thead><tr><th>{{ __('الحدث') }}</th><th>{{ __('التوقيع') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('مرات الوصول') }}</th><th>{{ __('الوقت') }}</th><th class="text-end"></th></tr></thead>
            <tbody>
                @foreach($gateway['webhooks'] as $hook)
                    <tr>
                        <td>
                            <span class="font-monospace ltr">{{ $hook->event_type ?? '—' }}</span>
                            <details class="mt-1"><summary class="text-muted">{{ __('المحتوى') }}</summary><pre class="x-json">{{ $json($hook->payload()) }}</pre>
                                @if($hook->last_error)<div class="text-danger ltr text-start">{{ $hook->last_error }}</div>@endif
                            </details>
                        </td>
                        <td>
                            @if($hook->signature_valid === true)<span class="pill pill-sm pill-success">{{ __('صحيح') }}</span>
                            @elseif($hook->signature_valid === false)<span class="pill pill-sm pill-error">{{ __('غير صحيح') }}</span>
                            @else<span class="pill pill-sm pill-neutral">{{ __('بلا مفتاح') }}</span>@endif
                        </td>
                        <td><x-admin.status-pill domain="webhook" :status="$hook->status" size="sm" /></td>
                        <td class="num">{{ $hook->deliveries }}</td>
                        <td class="num text-muted"><span class="ltr">{{ $hook->created_at?->format('m-d H:i:s') }}</span></td>
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
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-admin.card>
@endif
