@extends('layouts.admin')

@section('title', 'البوابات')
@section('page-title', 'البوابات')
@section('page-subtitle', 'مداخل ومخارج الموقع وقواعد الدخول لكل بوابة')
@php
    $pageIcon = 'door-open';
    $breadcrumbs = ['البوابات' => null];
    $L = \App\Services\Gate\GateLabels::class;
@endphp
@section('page-actions')
    <a href="{{ route('admin.gates.dashboard') }}" class="btn"><i class="bi bi-broadcast"></i> {{ __('لوحة البوابات المباشرة') }}</a>
    <a href="{{ route('admin.gates.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة بوابة') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع البوابات" icon="door-open" :count="$gates->total()" search="q" search-placeholder="ابحث بالاسم أو الرمز" :paginator="$gates">
        <x-slot:head>
            <th style="width: 64px">{{ __('الرمز') }}</th>
            <th>{{ __('البوابة') }}</th>
            <th>{{ __('الاتجاه') }}</th>
            <th>{{ __('الفعالية') }}</th>
            <th>{{ __('القواعد') }}</th>
            <th>{{ __('الأجهزة') }}</th>
            <th>{{ __('اليوم') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($gates as $gate)
            @php $t = $today->get($gate->id); @endphp
            <tr>
                <td><span class="gate-code">{{ $gate->code }}</span></td>
                <td>
                    <a href="{{ route('admin.gates.edit', $gate) }}" class="cell-title d-block">{{ $gate->name['ar'] ?? '' }}</a>
                    <div class="cell-sub ltr text-start">{{ $gate->name['en'] ?? '' }}</div>
                </td>
                <td><span class="pill pill-neutral"><i class="bi bi-{{ ['entry' => 'box-arrow-in-right', 'exit' => 'box-arrow-right', 'both' => 'arrow-left-right'][$gate->direction] ?? 'arrow-left-right' }} dir-icon"></i>{{ $L::label($L::GATE_DIRECTIONS, $gate->direction) }}</span></td>
                <td class="small">{{ $gate->event ? (app()->getLocale() === 'ar' ? ($gate->event->title_ar ?: $gate->event->title) : $gate->event->title) : __('كل الفعاليات') }}</td>
                <td class="small">
                    @if($gate->allow_reentry)<span class="pill pill-info pill-sm">{{ __('إعادة الدخول مسموحة') }}</span>@endif
                    @if($gate->ticket_types)<span class="pill pill-brand pill-sm ltr">{{ implode(', ', $gate->ticket_types) }}</span>@endif
                    @if($gate->can_sell)<span class="pill pill-success pill-sm"><i class="bi bi-cart-check"></i>{{ __('تبيع التذاكر') }}</span>@endif
                    @if(! $gate->allow_reentry && ! $gate->ticket_types && ! $gate->can_sell)<span class="text-muted">—</span>@endif
                </td>
                <td class="num">
                    <a href="{{ route('admin.gates.devices.index', ['gate' => $gate->id]) }}" class="d-inline-flex align-items-center gap-1"><i class="bi bi-upc-scan text-muted"></i> {{ $gate->active_devices_count }}</a>
                </td>
                <td class="num small">
                    <span title="{{ __('دخول') }}"><i class="bi bi-box-arrow-in-right text-success dir-icon"></i> {{ number_format($t['in'] ?? 0) }}</span>
                    <span class="ms-2" title="{{ __('مرفوض') }}"><i class="bi bi-x-octagon text-danger"></i> {{ number_format($t['rejects'] ?? 0) }}</span>
                </td>
                <td><x-admin.status-pill domain="active" :status="$gate->active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.gates.scans.index', ['gate' => $gate->id]) }}" class="icon-btn" title="{{ __('سجل المسح') }}"><i class="bi bi-list-check"></i></a>
                        <a href="{{ route('admin.gates.edit', $gate) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.gates.destroy', $gate) }}" method="POST"
                              data-confirm="{{ __('ستُحذف البوابة «:name»، وإن كان لها سجل مسح فستُعطَّل بدل الحذف.', ['name' => $gate->localizedName()]) }}" data-confirm-ok="{{ __('حذف') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="door-open" title="لا توجد بوابات بعد" text="أضف بوابات الموقع، ثم أقرِن بها أجهزة المسح.">
                <a href="{{ route('admin.gates.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة بوابة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection

@push('styles')
<style>
    .gate-code { display: inline-grid; place-items: center; min-width: 36px; height: 36px; padding: 0 8px; border-radius: var(--r-md); background: var(--brand-soft); color: var(--brand-text); font-family: var(--font-num), var(--font-ui); font-weight: 800; }
</style>
@endpush
