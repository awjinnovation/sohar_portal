@extends('layouts.admin')

@section('title', __('طلب استرداد #:id', ['id' => $refund->id]))
@section('page-title', __('طلب استرداد #:id', ['id' => $refund->id]))
@section('page-subtitle', ($refund->user?->name ?? '-').' · '.\App\Support\AdminLabels::label(\App\Support\AdminLabels::REFUND_METHODS, $refund->method))
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'arrow-counterclockwise';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'طلبات الاسترداد' => route('admin.refund-requests.index'), __('طلب #:id', ['id' => $refund->id]) => null];
    $net = max(0, $refund->amount_minor - $refund->fee_minor);
    $open = in_array($refund->status, ['pending', 'approved'], true);
@endphp
@section('page-actions')
    @can('manage wallets')
        <a href="{{ route('admin.wallets.show', $refund->wallet_id) }}" class="btn"><i class="bi bi-wallet2"></i> {{ __('عرض المحفظة') }}</a>
    @endcan
    <a href="{{ route('admin.refund-requests.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.card title="تفاصيل الطلب" icon="file-earmark-text">
                <x-slot:actions><x-admin.status-pill domain="refund" :status="$refund->status" /></x-slot:actions>

                <div class="kv-grid mb-3">
                    <div class="kv"><div class="k">{{ __('المبلغ المطلوب') }}</div><div class="v"><x-admin.money :minor="$refund->amount_minor" /></div></div>
                    <div class="kv"><div class="k">{{ __('الرسوم') }}</div><div class="v"><x-admin.money :minor="$refund->fee_minor" :direction="$refund->fee_minor ? 'debit' : null" tone="muted" /></div></div>
                    <div class="kv"><div class="k">{{ __('صافي المبلغ المستحق') }}</div><div class="v"><x-admin.money :minor="$net" tone="credit" /></div></div>
                </div>

                <dl class="dl">
                    <dt>{{ __('المستخدم') }}</dt>
                    <dd>
                        <div class="cell-title">{{ $refund->user?->name ?? '-' }}</div>
                        <div class="cell-sub ltr text-start">{{ collect([$refund->user?->phone_number, $refund->user?->email])->filter()->implode(' · ') }}</div>
                    </dd>
                    <dt>{{ __('الطريقة') }}</dt><dd>{{ AdminLabels::label(AdminLabels::REFUND_METHODS, $refund->method) }}</dd>
                    @if($refund->method === 'bank_transfer')
                        <dt>{{ __('البنك') }}</dt><dd>{{ $refund->bank_name ?: '—' }}</dd>
                        <dt>IBAN</dt>
                        <dd>
                            @if($refund->bank_iban)
                                <span class="font-monospace ltr d-inline-block px-2 py-1 rounded bg-light">{{ $refund->bank_iban }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    @endif
                    <dt>{{ __('سبب المستخدم') }}</dt><dd>{{ $refund->reason ?: '—' }}</dd>
                    <dt>{{ __('تاريخ الطلب') }}</dt><dd class="num"><span class="ltr">{{ $refund->created_at?->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('القرار بواسطة') }}</dt>
                    <dd>
                        {{ $refund->decider?->name ?? '—' }}
                        @if($refund->decided_at)<span class="text-muted small num ms-1"><span class="ltr">{{ $refund->decided_at->format('Y-m-d H:i') }}</span></span>@endif
                    </dd>
                    @if($refund->paid_at)
                        <dt>{{ __('تاريخ الدفع') }}</dt><dd class="num"><span class="ltr">{{ $refund->paid_at->format('Y-m-d H:i') }}</span></dd>
                    @endif
                    <dt>{{ __('ملاحظة الإدارة') }}</dt><dd>{{ $refund->admin_note ?: '—' }}</dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الإجراءات" icon="lightning-charge">
                @if($open)
                    <div class="alert alert-info small d-flex gap-2 align-items-start">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ __('المبلغ محجوز من رصيد المستخدم النقدي. الرفض يعيده إلى المحفظة.') }}</span>
                    </div>

                    @if($refund->status === 'pending')
                        <form action="{{ route('admin.refund-requests.approve', $refund) }}" method="POST" class="mb-4"
                              data-confirm="{{ __('الموافقة على طلب الاسترداد؟ يبقى المبلغ محجوزًا حتى تسجيل التحويل.') }}" data-confirm-ok="{{ __('موافقة') }}" data-confirm-tone="primary">
                            @csrf
                            <x-admin.form-field name="admin_note" id="approve_note" label="ملاحظة الموافقة" type="textarea" rows="2" placeholder="ملاحظة (اختياري)" />
                            <button type="submit" class="btn btn-primary w-100" data-busy><i class="bi bi-check2"></i> {{ __('موافقة') }}</button>
                        </form>
                    @endif

                    <form action="{{ route('admin.refund-requests.paid', $refund) }}" method="POST" class="mb-4"
                          data-confirm="{{ __('تأكيد أنه تم تحويل المبلغ للمستخدم؟') }}" data-confirm-ok="{{ __('تسجيل كمدفوع') }}" data-confirm-tone="primary">
                        @csrf
                        <x-admin.form-field name="admin_note" id="paid_note" label="مرجع التحويل" type="textarea" rows="2" placeholder="مرجع التحويل / ملاحظة (اختياري)" />
                        <button type="submit" class="btn btn-success w-100" data-busy><i class="bi bi-cash-coin"></i> {{ __('تسجيل كمدفوع') }}</button>
                    </form>

                    <form action="{{ route('admin.refund-requests.reject', $refund) }}" method="POST"
                          data-confirm="{{ __('رفض الطلب وإعادة المبلغ إلى المحفظة؟') }}" data-confirm-ok="{{ __('رفض') }}">
                        @csrf
                        <x-admin.form-field name="admin_note" id="reject_note" label="سبب الرفض" type="textarea" rows="2" placeholder="سبب الرفض (اختياري)" />
                        <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-x-lg"></i> {{ __('رفض') }}</button>
                    </form>
                @else
                    <x-admin.empty-state icon="check2-all" title="تمت معالجة الطلب" text="لا توجد إجراءات متاحة لهذا الطلب." class="py-3" />
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
