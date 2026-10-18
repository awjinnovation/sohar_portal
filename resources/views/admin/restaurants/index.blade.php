@extends('layouts.admin')

@section('title', 'المطاعم')
@section('page-title', 'إدارة المطاعم')
@section('page-subtitle', 'المطاعم والأكشاك التي تظهر للزوار في التطبيق')
@php
    $pageIcon = 'cup-hot';
    $breadcrumbs = ['المحتوى' => null, 'المطاعم والأكشاك' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.restaurants.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة مطعم جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع المطاعم" icon="cup-hot" :count="$restaurants->total()" :paginator="$restaurants">
        <x-slot:head>
            <th>{{ __('المطعم') }}</th>
            <th>{{ __('المطبخ') }}</th>
            <th>{{ __('الموقع') }}</th>
            <th>{{ __('التقييم') }}</th>
            <th>{{ __('السعر') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($restaurants as $restaurant)
            <tr>
                <td>
                    <div class="cell-media">
                        @if($restaurant->image_url)
                            <img src="{{ $restaurant->image_url }}" alt="" loading="lazy">
                        @else
                            <span class="ph"><i class="bi bi-cup-hot"></i></span>
                        @endif
                        <div style="min-width: 0">
                            <div class="cell-title">
                                {{ $restaurant->name_ar }}
                                @if($restaurant->is_featured)
                                    <i class="bi bi-star-fill text-warning" title="{{ __('مميز') }}"></i>
                                @endif
                            </div>
                            <div class="cell-sub">{{ $restaurant->name }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $restaurant->cuisine_ar }}</td>
                <td class="text-muted">{{ $restaurant->location_ar }}</td>
                <td class="num nowrap">
                    @if($restaurant->rating !== null)
                        <i class="bi bi-star-fill text-warning"></i> {{ $restaurant->rating }}
                        @if($restaurant->total_ratings)<span class="text-muted small">({{ number_format($restaurant->total_ratings) }})</span>@endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="num ltr text-start">{{ $restaurant->price_range }}</td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <x-admin.status-pill domain="active" :status="$restaurant->is_active" size="sm" />
                        @if($restaurant->is_open)
                            <x-admin.status-pill variant="info" label="مفتوح" size="sm" />
                        @else
                            <x-admin.status-pill variant="neutral" label="مغلق" size="sm" />
                        @endif
                    </div>
                </td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST" data-confirm="{{ __('سيتم حذف المطعم «:name» نهائيًا.', ['name' => $restaurant->name_ar]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="cup-hot" title="لا توجد مطاعم حتى الآن" text="أضف مطعمًا أو كشكًا ليظهر للزوار في التطبيق.">
                <a href="{{ route('admin.restaurants.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة مطعم') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
