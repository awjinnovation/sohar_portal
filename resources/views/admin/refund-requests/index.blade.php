@extends('layouts.admin')

@section('title', 'طلبات الاسترداد')
@section('page-title', 'طلبات الاسترداد')
@section('page-subtitle', 'مراجعة طلبات استرداد الرصيد النقدي والموافقة عليها أو رفضها')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'arrow-counterclockwise';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'طلبات الاسترداد' => null];
    $tabs = ['pending' => 'قيد الانتظار', 'approved' => 'موافق عليها', 'paid' => 'مدفوعة', 'rejected' => 'مرفوضة', 'all' => 'الكل'];
    $cnt = fn (string $k) => (int) ($counts[$k]->c ?? 0);
    $sum = fn (string $k) => (int) ($counts[$k]->total ?? 0);
@endphp

@push('styles')
<style>
    .seg-scroll { max-width: calc(100vw - 66px); overflow-x: auto; scrollbar-width: none; }
    .seg-scroll .segmented { flex-wrap: nowrap; }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="بانتظار المراجعة" :money="$sum('pending')" icon="hourglass-split" :tone="$cnt('pending') ? 'warning' : 'neutral'"
            :hint="__(':count طلب', ['count' => $cnt('pending')])" :href="route('admin.refund-requests.index', ['status' => 'pending'])" />
        <x-admin.stat-tile label="موافق عليها (بانتظار التحويل)" :money="$sum('approved')" icon="check2-circle" tone="info"
            :hint="__(':count طلب', ['count' => $cnt('approved')])" :href="route('admin.refund-requests.index', ['status' => 'approved'])" />
        <x-admin.stat-tile label="مدفوعة" :money="$sum('paid')" icon="cash-coin" tone="success"
            :hint="__(':count طلب', ['count' => $cnt('paid')])" :href="route('admin.refund-requests.index', ['status' => 'paid'])" />
    </div>

    <x-admin.data-table :title="$tabs[$status] ?? 'الطلبات'" icon="inbox" :count="$requests->total()" :paginator="$requests">
        <x-slot:actions>
            <nav class="seg-scroll" aria-label="{{ __('تصفية حسب الحالة') }}"><div class="segmented">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('admin.refund-requests.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>
                        {{ __($label) }}@if($key !== 'all' && $cnt($key))<span class="cnt">{{ $cnt($key) }}</span>@endif
                    </a>
                @endforeach
            </div></nav>
        </x-slot:actions>
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('الرسوم') }}</th>
            <th>{{ __('الصافي') }}</th>
            <th>{{ __('الطريقة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($requests as $rr)
            <tr>
                <td class="num text-muted">{{ $rr->id }}</td>
                <td>
                    <div class="cell-media">
                        <span class="avatar">{{ mb_substr($rr->user?->name ?? '?', 0, 1) }}</span>
                        <div>
                            <a href="{{ route('admin.refund-requests.show', $rr) }}" class="cell-title d-block text-nowrap">{{ $rr->user?->name ?? '-' }}</a>
                            <div class="cell-sub ltr text-start">{{ $rr->user?->phone_number ?: $rr->user?->email }}</div>
                        </div>
                    </div>
                </td>
                <td><x-admin.money :minor="$rr->amount_minor" class="fw-bold" /></td>
                <td><x-admin.money :minor="$rr->fee_minor" tone="muted" /></td>
                <td><x-admin.money :minor="max(0, $rr->amount_minor - $rr->fee_minor)" /></td>
                <td>
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi {{ $rr->method === 'bank_transfer' ? 'bi-bank' : 'bi-credit-card' }} text-muted"></i>
                        {{ AdminLabels::label(AdminLabels::REFUND_METHODS, $rr->method) }}
                    </span>
                </td>
                <td><x-admin.status-pill domain="refund" :status="$rr->status" /></td>
                <td class="num text-muted"><span class="ltr">{{ $rr->created_at?->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    <div class="row-actions">
                        @if($rr->status === 'pending')
                            <form action="{{ route('admin.refund-requests.approve', $rr) }}" method="POST"
                                  data-confirm="{{ __('الموافقة على طلب استرداد :name؟ يبقى المبلغ محجوزًا حتى تسجيل التحويل.', ['name' => $rr->user?->name]) }}" data-confirm-ok="{{ __('موافقة') }}" data-confirm-tone="primary">
                                @csrf
                                <button type="submit" class="icon-btn brand" title="{{ __('موافقة') }}"><i class="bi bi-check2"></i></button>
                            </form>
                        @endif
                        @if($rr->status === 'approved')
                            <form action="{{ route('admin.refund-requests.paid', $rr) }}" method="POST"
                                  data-confirm="{{ __('تأكيد أنه تم تحويل المبلغ للمستخدم :name؟', ['name' => $rr->user?->name]) }}" data-confirm-ok="{{ __('تسجيل كمدفوع') }}" data-confirm-tone="primary">
                                @csrf
                                <button type="submit" class="icon-btn brand" title="{{ __('تسجيل كمدفوع') }}"><i class="bi bi-cash-coin"></i></button>
                            </form>
                        @endif
                        @if(in_array($rr->status, ['pending', 'approved'], true))
                            <form action="{{ route('admin.refund-requests.reject', $rr) }}" method="POST"
                                  data-confirm="{{ __('رفض الطلب وإعادة المبلغ إلى محفظة :name؟', ['name' => $rr->user?->name]) }}" data-confirm-ok="{{ __('رفض') }}">
                                @csrf
                                <button type="submit" class="icon-btn danger" title="{{ __('رفض') }}"><i class="bi bi-x-lg"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('admin.refund-requests.show', $rr) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="inbox" title="لا توجد طلبات"
                :text="$status === 'pending' ? 'لا توجد طلبات بانتظار المراجعة حاليًا.' : 'لا توجد طلبات بهذه الحالة.'" />
        @endforelse
    </x-admin.data-table>
@endsection
