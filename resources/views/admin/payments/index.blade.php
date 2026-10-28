@extends('layouts.admin')

@section('title', 'المدفوعات')
@section('page-title', 'المدفوعات')
@section('page-subtitle', 'مدفوعات التذاكر وشحن المحافظ عبر بوابة الدفع أو المحفظة')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'credit-card-2-front';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المدفوعات' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.payments.gateway-log') }}" class="btn"><i class="bi bi-hdd-network"></i> {{ __('سجل بوابة الدفع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="إجمالي المدفوعات المكتملة" :money="$summary['completed_sum']" icon="cash-coin" tone="success" />
        <x-admin.stat-tile label="عدد المدفوعات المكتملة" :value="$summary['completed_count']" icon="receipt" tone="brand" />
        <x-admin.stat-tile label="إجمالي المسترد" :money="$summary['refunded_sum']" icon="arrow-counterclockwise" tone="neutral"
            :href="route('admin.payments.index', ['status' => 'refunded'])" hint="عرض المدفوعات المستردة" />
        <x-admin.stat-tile label="تحتاج إلى مراجعة" :value="$summary['needs_review']" icon="exclamation-triangle" :tone="$summary['needs_review'] ? 'warning' : 'neutral'"
            :href="route('admin.payments.index', ['review' => 1])" hint="عرض المدفوعات التي تحتاج إلى مراجعة" />
    </div>

    <x-admin.data-table title="سجل المدفوعات" icon="credit-card-2-front" :count="$payments->total()" search="q"
        search-placeholder="رقم المعاملة أو هاتف المستخدم" :paginator="$payments">
        <x-slot:filters>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach(AdminLabels::PAYMENT_STATUSES as $sv => [$sl])
                    <option value="{{ $sv }}" @selected($filters['status'] === $sv)>{{ __($sl) }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select" aria-label="{{ __('النوع') }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(AdminLabels::PAYMENT_TYPES as $tv => $tl)
                    <option value="{{ $tv }}" @selected($filters['type'] === $tv)>{{ __($tl) }}</option>
                @endforeach
            </select>
            <select name="review" class="form-select" aria-label="{{ __('المراجعة') }}">
                <option value="">{{ __('كل المدفوعات') }}</option>
                <option value="1" @selected($filters['review'])>{{ __('تحتاج إلى مراجعة') }}</option>
            </select>
            <select name="method" class="form-select" aria-label="{{ __('الطريقة') }}">
                <option value="">{{ __('كل الطرق') }}</option>
                @foreach(AdminLabels::PAYMENT_METHODS as $mv => $ml)
                    <option value="{{ $mv }}" @selected($filters['method'] === $mv)>{{ __($ml) }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('رقم المعاملة') }}</th>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الطريقة') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($payments as $payment)
            <tr>
                <td class="num text-muted">{{ $payment->id }}</td>
                <td><a href="{{ route('admin.payments.show', $payment) }}" class="font-monospace small ltr">{{ $payment->transaction_id }}</a></td>
                <td>
                    <div class="cell-title text-nowrap">{{ $payment->user?->name ?? '-' }}</div>
                    <div class="cell-sub ltr text-start">{{ $payment->user?->phone_number }}</div>
                </td>
                <td>
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi {{ $payment->payment_type === 'topup' ? 'bi-wallet2' : 'bi-ticket-perforated' }} text-muted"></i>
                        {{ AdminLabels::label(AdminLabels::PAYMENT_TYPES, $payment->payment_type) }}
                    </span>
                </td>
                <td>{{ AdminLabels::label(AdminLabels::PAYMENT_METHODS, $payment->method) }}</td>
                <td><x-admin.money :minor="(int) $payment->amount_minor" class="fw-bold" /></td>
                <td>
                    <x-admin.status-pill domain="payment" :status="$payment->status" />
                    @if($payment->needs_review)<span class="pill pill-sm pill-warning" title="{{ AdminLabels::label(AdminLabels::PAYMENT_REVIEW_REASONS, $payment->review_reason) }}"><i class="bi bi-exclamation-triangle-fill"></i>{{ __('مراجعة') }}</span>@endif
                </td>
                <td class="num text-muted"><span class="ltr">{{ ($payment->paid_at ?? $payment->created_at)?->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.payments.show', $payment) }}" class="icon-btn brand" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="credit-card-2-front" title="لا توجد مدفوعات" text="جرّب تغيير معايير البحث أو التصفية." />
        @endforelse
    </x-admin.data-table>
@endsection
