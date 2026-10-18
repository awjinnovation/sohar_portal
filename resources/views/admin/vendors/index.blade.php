@extends('layouts.admin')

@section('title', 'التجار')
@section('page-title', 'إدارة التجار')
@section('page-subtitle', 'نقاط البيع التي تستقبل الدفع من محافظ الزوار')
@php
    $pageIcon = 'shop-window';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'التجار' => null];
    $categories = \App\Http\Controllers\Admin\VendorController::CATEGORIES;
@endphp
@section('page-actions')
    <a href="{{ route('admin.vendors.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة تاجر') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع التجار" icon="shop-window" :count="$vendors->total()" search="q"
        search-placeholder="بحث بالاسم أو التصنيف" :paginator="$vendors">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('التاجر') }}</th>
            <th>{{ __('التصنيف') }}</th>
            <th>{{ __('الموظفون') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($vendors as $vendor)
            <tr>
                <td class="num text-muted">{{ $vendor->id }}</td>
                <td>
                    <div class="cell-media">
                        @if($vendor->logoUrl())
                            <img src="{{ $vendor->logoUrl() }}" alt="">
                        @else
                            <span class="ph"><i class="bi bi-shop"></i></span>
                        @endif
                        <div>
                            <a href="{{ route('admin.vendors.show', $vendor) }}" class="cell-title d-block">{{ $vendor->name['ar'] ?? '' }}</a>
                            <div class="cell-sub ltr text-start">{{ $vendor->name['en'] ?? '' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    @if($vendor->category)
                        <span class="pill pill-neutral">{{ __($categories[$vendor->category] ?? $vendor->category) }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    <span class="d-inline-flex align-items-center gap-1 num"><i class="bi bi-people text-muted"></i> {{ $vendor->staff_count }}</span>
                </td>
                <td><x-admin.status-pill domain="active" :status="$vendor->is_active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.vendors.show', $vendor) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.vendors.edit', $vendor) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.vendors.destroy', $vendor) }}" method="POST"
                              data-confirm="{{ __('سيتم حذف التاجر «:name» نهائيًا. لا يمكن حذف تاجر لديه عمليات مالية.', ['name' => $vendor->name['ar'] ?? '']) }}" data-confirm-ok="{{ __('حذف') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="shop" title="لا يوجد تجار حتى الآن" text="أضف تاجرًا ليستقبل الدفع من محافظ الزوار.">
                <a href="{{ route('admin.vendors.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة تاجر') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
