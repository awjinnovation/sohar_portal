@extends('layouts.admin')

@section('title', 'طلبات العرض التوضيحي')
@section('page-title', 'طلبات العرض التوضيحي')
@section('page-subtitle', 'طلبات «احجز عرضًا» من الصفحة التعريفية لمنصة موسم')
@php
    $pageIcon = 'envelope-paper';
    $breadcrumbs = ['المنصة' => null, 'طلبات العرض التوضيحي' => null];
    $tabs = ['all' => 'الكل', 'new' => 'جديد', 'contacted' => 'تم التواصل', 'closed' => 'مغلق'];
    $cnt = fn (string $k) => $k === 'all' ? (int) $counts->sum() : (int) ($counts[$k] ?? 0);
    $lc = app()->getLocale();
@endphp

@push('styles')
<style>
    .seg-scroll { max-width: calc(100vw - 66px); overflow-x: auto; scrollbar-width: none; }
    .seg-scroll .segmented { flex-wrap: nowrap; }
</style>
@endpush

@section('page-actions')
    <a href="{{ url('/') }}" class="btn" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('الصفحة التعريفية') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="طلبات جديدة" :value="$cnt('new')" icon="envelope-exclamation" :tone="$cnt('new') ? 'warning' : 'neutral'"
            :href="route('admin.demo-requests.index', ['status' => 'new'])" />
        <x-admin.stat-tile label="تم التواصل" :value="$cnt('contacted')" icon="chat-dots" tone="info"
            :href="route('admin.demo-requests.index', ['status' => 'contacted'])" />
        <x-admin.stat-tile label="مغلقة" :value="$cnt('closed')" icon="archive" tone="neutral"
            :href="route('admin.demo-requests.index', ['status' => 'closed'])" />
    </div>

    <x-admin.data-table :title="$tabs[$status] ?? 'الطلبات'" icon="envelope-paper" :count="$requests->total()" :paginator="$requests"
        search="q" search-placeholder="ابحث بالاسم أو الجهة أو وسيلة التواصل">
        <x-slot:filters>
            @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        </x-slot:filters>
        <x-slot:actions>
            <nav class="seg-scroll" aria-label="{{ __('التصفية بحسب الحالة') }}"><div class="segmented">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('admin.demo-requests.index', array_filter(['status' => $key, 'q' => $q ?: null])) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>
                        {{ __($label) }}@if($cnt($key))<span class="cnt">{{ $cnt($key) }}</span>@endif
                    </a>
                @endforeach
            </div></nav>
        </x-slot:actions>
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('مقدم الطلب') }}</th>
            <th>{{ __('نوع الفعالية') }}</th>
            <th>{{ __('الزوار المتوقعون') }}</th>
            <th>{{ __('التواصل') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($requests as $dr)
            <tr>
                <td class="num text-muted">{{ $dr->id }}</td>
                <td>
                    <div class="cell-media">
                        <span class="avatar">{{ mb_substr($dr->organization, 0, 1) }}</span>
                        <div>
                            <a href="{{ route('admin.demo-requests.show', $dr) }}" class="cell-title d-block"><bdi>{{ $dr->organization }}</bdi></a>
                            <div class="cell-sub"><bdi>{{ $dr->name }}</bdi></div>
                        </div>
                    </div>
                </td>
                <td>{{ $dr->eventTypeLabel($lc) }}</td>
                <td class="text-nowrap"><bdi>{{ $dr->visitorsLabel($lc) }}</bdi></td>
                <td>
                    <span class="d-inline-flex align-items-center gap-1 text-nowrap">
                        <i class="bi {{ $dr->contact_email ? 'bi-envelope' : 'bi-telephone' }} text-muted"></i>
                        <span class="ltr">{{ $dr->contact }}</span>
                    </span>
                </td>
                <td><x-admin.status-pill domain="demo" :status="$dr->status" /></td>
                <td class="num text-muted"><span class="ltr">{{ $dr->submittedAtLocal()?->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    <div class="row-actions">
                        @if($dr->status === 'new')
                            <form action="{{ route('admin.demo-requests.status', $dr) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="contacted">
                                <button type="submit" class="icon-btn brand" title="{{ __('تسجيل التواصل') }}"><i class="bi bi-chat-dots"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('admin.demo-requests.show', $dr) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" icon="envelope-paper" title="لا توجد طلبات"
                :text="$q !== '' || $status !== 'all' ? 'لا توجد طلبات مطابقة للتصفية.' : 'ستظهر هنا طلبات «احجز عرضًا» من الصفحة التعريفية.'" />
        @endforelse
    </x-admin.data-table>
@endsection
