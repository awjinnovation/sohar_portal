@extends('layouts.admin')

@section('title', 'خريطة الأماكن')
@section('page-title', 'خريطة الأماكن')
@section('page-subtitle', 'جميع الأماكن على الخريطة، وانقر على أي دبوس لتعديل المكان')
@php
    $pageIcon = 'map';
    $breadcrumbs = ['المحتوى' => null, 'الأماكن' => route('admin.places.index'), 'خريطة الأماكن' => null];
    $tenantForMap = $currentTenant ?? $brand;
    $mapCenter = ['lat' => (float) $tenantForMap?->setting('map.center.lat', 24.35), 'lng' => (float) $tenantForMap?->setting('map.center.lng', 56.72), 'zoom' => 10];
    $rows = $places->map(fn ($p) => \App\Http\Controllers\Admin\PlaceController::mapRow($p))->values();
    $uncategorized = $places->whereNull('category_id')->count();
@endphp
@section('page-actions')
    <a href="{{ route('admin.places.index') }}" class="btn btn-tonal"><i class="bi bi-list-ul"></i> {{ __('قائمة الأماكن') }}</a>
    <a href="{{ route('admin.places.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة مكان') }}</a>
@endsection

@section('content')
    @include('admin.discover._module-notice', ['module' => 'places'])
    <div class="pm-layout" data-places-map data-places='@json($rows)' data-center='@json($mapCenter)'>
        <div class="pm-map" data-pm-map dir="ltr" role="application" aria-label="{{ __('خريطة الأماكن') }}"></div>
        <aside>
            <x-admin.card title="الفئات" icon="tags" class="pm-legend" subtitle="أظهر الفئات أو أخفها على الخريطة">
                @foreach($categories as $cat)
                    <label class="legend-row mb-0">
                        <input class="form-check-input mt-0" type="checkbox" checked data-pm-toggle="{{ $cat->id }}">
                        <span class="ic-tile sm" style="--ic: {{ $cat->color }}"><span class="msr">{{ $cat->icon }}</span></span>
                        <span class="flex-grow-1">{{ $cat->t('name') }}</span>
                        <span class="pill pill-neutral pill-sm num">{{ $cat->places_count }}</span>
                    </label>
                @endforeach
                @if($uncategorized)
                    <label class="legend-row mb-0">
                        <input class="form-check-input mt-0" type="checkbox" checked data-pm-toggle="0">
                        <span class="ic-tile sm"><span class="msr">place</span></span>
                        <span class="flex-grow-1">{{ __('بلا فئة') }}</span>
                        <span class="pill pill-neutral pill-sm num">{{ $uncategorized }}</span>
                    </label>
                @endif
            </x-admin.card>
            <x-admin.card title="الأماكن" icon="pin-map" class="pm-legend">
                <div class="pm-list">
                    @forelse($places as $p)
                        <a href="#" data-pm-focus="{{ $p->id }}">
                            <span class="ic-tile sm" style="--ic: {{ $p->displayColor() }}"><span class="msr">{{ $p->displayIcon() }}</span></span>
                            <span class="flex-grow-1 text-truncate">{{ $p->t('name') }}</span>
                            @if($p->status !== 'published')<span class="pill pill-neutral pill-sm">{{ __('مسودة') }}</span>@endif
                        </a>
                    @empty
                        <p class="text-muted small mb-0">{{ __('لا توجد أماكن بعد.') }}</p>
                    @endforelse
                </div>
            </x-admin.card>
        </aside>
    </div>
@endsection

@include('admin.discover._assets')
