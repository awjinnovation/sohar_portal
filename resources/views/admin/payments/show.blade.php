@extends('layouts.admin')

@section('title', __('الدفعة #:id', ['id' => $payment->id]))
@section('page-title', __('الدفعة #:id', ['id' => $payment->id]))
@section('page-subtitle', \App\Support\AdminLabels::label(\App\Support\AdminLabels::PAYMENT_TYPES, $payment->payment_type).' · '.\App\Support\AdminLabels::label(\App\Support\AdminLabels::PAYMENT_METHODS, $payment->method))
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'credit-card-2-front';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المدفوعات' => route('admin.payments.index'), __('الدفعة #:id', ['id' => $payment->id]) => null];
    $canRefund = $payment->status === 'completed' && $payment->payment_type === \App\Models\Payment::TYPE_TICKET;
@endphp
@section('page-actions')
    <a href="{{ route('admin.payments.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.card title="تفاصيل الدفعة" icon="receipt">
                <x-slot:actions><x-admin.status-pill domain="payment" :status="$payment->status" /></x-slot:actions>

                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 pb-3 mb-1 border-bottom">
                    <div>
                        <div class="text-muted small fw-semibold">{{ __('المبلغ') }}</div>
                        <x-admin.money :minor="(int) $payment->amount_minor" size="lg" />
                    </div>
                    <div class="text-end">
                        <div class="text-muted small fw-semibold">{{ __('رقم المعاملة') }}</div>
                        <span class="font-monospace ltr">{{ $payment->transaction_id }}</span>
                    </div>
                </div>

                <dl class="dl">
                    <dt>{{ __('المستخدم') }}</dt>
                    <dd>
                        <div class="cell-title">{{ $payment->user?->name ?? '-' }}</div>
                        <div class="cell-sub ltr text-start">{{ collect([$payment->user?->phone_number, $payment->user?->email])->filter()->implode(' · ') }}</div>
                    </dd>
                    <dt>{{ __('النوع') }}</dt><dd>{{ AdminLabels::label(AdminLabels::PAYMENT_TYPES, $payment->payment_type) }}</dd>
                    <dt>{{ __('الطريقة') }}</dt>
                    <dd>{{ AdminLabels::label(AdminLabels::PAYMENT_METHODS, $payment->method) }} @if($payment->gateway)<span class="text-muted small">({{ $payment->gateway }})</span>@endif</dd>
                    @if(!empty($payment->metadata['points_used']))
                        <dt>{{ __('نقاط مستخدمة') }}</dt><dd class="num">{{ number_format($payment->metadata['points_used']) }}</dd>
                    @endif
                    @if($topup)
                        <dt>{{ __('شحن المحفظة') }}</dt>
                        <dd class="d-flex flex-wrap align-items-center gap-2">
                            <span class="pill pill-neutral pill-sm">{{ __('نقدي') }} <x-admin.money :minor="(int) $topup->amount_minor" /></span>
                            @if($topup->bonus_minor)
                                <span class="pill pill-accent pill-sm">{{ __('ترويجي') }} <x-admin.money :minor="(int) $topup->bonus_minor" :signed="true" tone="muted" /></span>
                            @endif
                            <x-admin.status-pill domain="payment" :status="$topup->status" size="sm" />
                        </dd>
                    @endif
                    <dt>{{ __('تاريخ الإنشاء') }}</dt><dd class="num"><span class="ltr">{{ $payment->created_at?->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('تاريخ الدفع') }}</dt><dd class="num"><span class="ltr">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                    <dt>{{ __('تاريخ الاسترداد') }}</dt><dd class="num"><span class="ltr">{{ $payment->refunded_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الاسترداد" icon="arrow-counterclockwise">
                @can('refund payments')
                    @if($canRefund)
                        <p class="text-muted small">
                            {{ __('ستُلغى التذاكر، ويعود إلى المحفظة ما دُفع منها إن وُجد، وتُرجَع التأمينات وتُستعاد النقاط.') }}
                        </p>
                        @if($payment->method === 'gateway' && $payment->gateway === 'thawani')
                            <div class="alert alert-info small d-flex gap-2 align-items-start">
                                <i class="bi bi-info-circle-fill"></i><span>{{ __('يُعاد المبلغ إلى البطاقة تلقائيًا عبر ثواني، وتُعاد المحاولة إذا تعذّر الاتصال.') }}</span>
                            </div>
                        @elseif($payment->method === 'gateway')
                            <div class="alert alert-warning small d-flex gap-2 align-items-start">
                                <i class="bi bi-info-circle-fill"></i><span>{{ __('تُسترد مدفوعات البطاقة يدويًا عبر بوابة الدفع.') }}</span>
                            </div>
                        @endif
                        <form action="{{ route('admin.payments.refund', $payment) }}" method="POST"
                              data-confirm="{{ __('ستُلغى التذاكر المرتبطة ويُعاد المبلغ، ولا يمكن التراجع عن هذا الإجراء.') }}" data-confirm-title="{{ __('تأكيد استرداد الدفعة') }}" data-confirm-ok="{{ __('استرداد') }}">
                            @csrf
                            <x-admin.form-field name="reason" label="السبب" type="textarea" rows="2" placeholder="السبب (اختياري)" />
                            <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-arrow-counterclockwise"></i> {{ __('استرداد الدفعة') }}</button>
                        </form>
                    @elseif($payment->payment_type === \App\Models\Payment::TYPE_TOPUP)
                        <x-admin.empty-state icon="wallet2" title="عبر طلبات الاسترداد" text="تُسترد عمليات الشحن عبر طلبات استرداد المحفظة." class="py-3" />
                    @else
                        <x-admin.empty-state icon="slash-circle" title="غير متاح" text="لا يمكن استرداد هذه الدفعة." class="py-3" />
                    @endif
                @else
                    <x-admin.empty-state icon="shield-lock" title="غير مسموح" text="ليست لديك صلاحية الاسترداد." class="py-3" />
                @endcan

                @if($refund = $gateway['refund'])
                    <div class="border-top mt-3 pt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold">{{ __('الإعادة إلى البطاقة') }}</span>
                            <x-admin.status-pill domain="gwrefund" :status="$refund->status" size="sm" />
                        </div>
                        <dl class="dl small mb-2">
                            <dt>{{ __('المبلغ') }}</dt><dd><x-admin.money :minor="(int) $refund->amount_minor" /></dd>
                            <dt>{{ __('المحاولات') }}</dt><dd class="num">{{ $refund->attempts }}</dd>
                            @if($refund->gateway_refund_id)<dt>{{ __('رقم الاسترداد') }}</dt><dd><span class="font-monospace ltr">{{ $refund->gateway_refund_id }}</span></dd>@endif
                            @if($refund->completed_at)<dt>{{ __('تاريخ الإعادة') }}</dt><dd class="num"><span class="ltr">{{ $refund->completed_at->format('Y-m-d H:i') }}</span></dd>@endif
                        </dl>
                        @if($refund->last_error)<div class="alert alert-danger small ltr text-start mb-2">{{ $refund->last_error }}</div>@endif
                        @can('refund payments')
                            @if(in_array($refund->status, ['failed', 'needs_review'], true))
                                <form action="{{ route('admin.payments.refund.retry', $payment) }}" method="POST">
                                    @csrf
                                    @if($refund->status === 'needs_review')
                                        <div class="form-check small mb-2">
                                            <input class="form-check-input" type="checkbox" name="confirm_checked" value="1" id="confirm_checked" required>
                                            <label class="form-check-label" for="confirm_checked">{{ __('تأكدتُ من بوابة ثواني أن المبلغ لم يُرد بعد') }}</label>
                                        </div>
                                    @endif
                                    <button type="submit" class="btn btn-outline-primary w-100" data-busy><i class="bi bi-arrow-repeat"></i> {{ __('أعد إرسال الاسترداد') }}</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>

    @include('admin.payments._gateway')

    <x-admin.card title="التذاكر" icon="ticket-perforated" :subtitle="$payment->tickets->count() ? __('التذاكر: :count', ['count' => $payment->tickets->count()]) : null" :flush="true">
        @if($payment->tickets->isEmpty())
            <x-admin.empty-state icon="ticket-perforated" title="لا توجد تذاكر" text="هذه الدفعة غير مرتبطة بتذاكر." />
        @else
        <div class="x-table-wrap">
            <table class="table align-middle">
                <thead><tr><th>{{ __('رقم التذكرة') }}</th><th>{{ __('الفعالية') }}</th><th>{{ __('الحامل') }}</th><th>{{ __('النوع') }}</th><th>{{ __('الحالة') }}</th></tr></thead>
                <tbody>
                    @foreach($payment->tickets as $ticket)
                        <tr>
                            <td><span class="font-monospace small ltr">{{ $ticket->ticket_number }}</span></td>
                            <td class="cell-title">{{ $ticket->event?->title_ar ?? $ticket->event?->title ?? '-' }}</td>
                            <td>{{ $ticket->holder_name ?? '-' }}</td>
                            <td>{{ $ticket->ticket_type }}</td>
                            <td><x-admin.status-pill domain="ticket" :status="$ticket->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-admin.card>

    <x-admin.card title="معاملات المحفظة المرتبطة" icon="wallet2" :flush="true">
        @if($walletTransactions->isEmpty())
            <x-admin.empty-state icon="wallet2" title="لا توجد معاملات على المحفظة" text="دُفعت هذه العملية دون حركة على المحفظة." />
        @else
        <div class="x-table-wrap">
            <table class="table align-middle">
                <thead><tr><th>#</th><th>{{ __('المعاملة') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @foreach($walletTransactions as $tx)
                        <tr>
                            <td class="num text-muted">{{ $tx->id }}</td>
                            <td>
                                <div class="cell-title text-nowrap">{{ AdminLabels::label(AdminLabels::TX_TYPES, $tx->type) }}</div>
                                <div class="cell-sub">{{ AdminLabels::label(AdminLabels::BUCKETS, $tx->bucket()) }}</div>
                            </td>
                            <td><x-admin.money :minor="$tx->amount_minor" :direction="$tx->direction" /></td>
                            <td><x-admin.status-pill domain="tx" :status="$tx->status" /></td>
                            <td class="num text-muted"><span class="ltr">{{ $tx->created_at?->format('Y-m-d H:i') }}</span></td>
                            <td class="text-end">
                                @can('manage wallets')
                                    <a href="{{ route('admin.wallets.show', $tx->wallet_id) }}" class="icon-btn brand" title="{{ __('فتح المحفظة') }}"><i class="bi bi-wallet2"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-admin.card>
@endsection
