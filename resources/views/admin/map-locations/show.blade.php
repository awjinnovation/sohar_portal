@extends('layouts.admin')

@section('title', 'تفاصيل الموقع')
@section('page-title', 'تفاصيل الموقع')
@section('page-subtitle', $mapLocation->name_ar ?: $mapLocation->name)
@php
    $pageIcon = 'geo-alt';
    $breadcrumbs = ['المحتوى' => null, 'مواقع الخريطة' => route('admin.map-locations.index'), 'تفاصيل الموقع' => null];
    $locTypes = \App\Models\MapLocation::getTypes();
    $biIcons = ['pin-map-fill', 'geo-alt-fill', 'star-fill', 'flag-fill', 'cup-hot-fill', 'shop', 'building', 'hospital', 'life-preserver', 'p-square-fill'];
    $pinHex = $mapLocation->color ? sprintf('#%06X', ((int) $mapLocation->color) & 0xFFFFFF) : null;
    $pinIcon = in_array($mapLocation->icon, $biIcons, true) ? $mapLocation->icon : 'geo-alt-fill';
    $lat = (float) $mapLocation->latitude;
    $lng = (float) $mapLocation->longitude;
@endphp
@section('page-actions')
    <a href="{{ route('admin.map-locations.edit', $mapLocation) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.map-locations.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@push('styles')
<style>
    .pin-hero { width: 56px; height: 56px; flex: none; border-radius: 50%; display: grid; place-items: center; font-size: 24px;
        background: color-mix(in srgb, var(--pin, var(--muted)) 16%, var(--surface)); color: color-mix(in srgb, var(--pin, var(--muted)) 78%, var(--text)); }
    .pin-dot { display: inline-block; width: 18px; height: 18px; border-radius: 50%; background: var(--pin); box-shadow: 0 0 0 2px var(--surface), 0 0 0 3px var(--hairline); vertical-align: middle; }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="pin-hero" @if($pinHex) style="--pin: {{ $pinHex }}" @endif><i class="bi bi-{{ $pinIcon }}"></i></span>
                    <div class="flex-grow-1" style="min-width: 0">
                        <h2 class="h5 fw-bold mb-1">{{ $mapLocation->name_ar }}</h2>
                        <div class="text-muted ltr text-start">{{ $mapLocation->name }}</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <x-admin.status-pill :variant="in_array($mapLocation->type, ['emergency', 'first_aid'], true) ? 'error' : 'neutral'" :label="$locTypes[$mapLocation->type][app()->getLocale() === 'en' ? 'en' : 'ar'] ?? $mapLocation->type" />
                        <x-admin.status-pill domain="active" :status="$mapLocation->is_active" />
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="الوصف" icon="translate">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-4">
                    <div class="tab-pane fade show active lh-lg" id="ar" role="tabpanel">{!! nl2br(e($mapLocation->description_ar)) !!}</div>
                    <div class="tab-pane fade lh-lg" id="en" role="tabpanel" dir="ltr">{!! nl2br(e($mapLocation->description)) !!}</div>
                </div>
            </x-admin.card>

            <x-admin.card title="التفاصيل" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('المعرّف') }}</dt>
                    <dd class="num">#{{ $mapLocation->id }}</dd>
                    <dt>{{ __('النوع') }}</dt>
                    <dd>
                        @isset($locTypes[$mapLocation->type])
                            {{ $locTypes[$mapLocation->type]['ar'] }} <span class="text-muted">({{ $locTypes[$mapLocation->type]['en'] }})</span>
                        @else
                            <code>{{ $mapLocation->type }}</code>
                        @endisset
                    </dd>
                    <dt>{{ __('الأيقونة') }}</dt>
                    <dd><i class="bi bi-{{ $pinIcon }} me-1"></i> <code>{{ $mapLocation->icon }}</code></dd>
                    <dt>{{ __('اللون') }}</dt>
                    <dd>
                        @if($pinHex)
                            <span class="pin-dot" style="--pin: {{ $pinHex }}"></span>
                            <code class="ms-2 ltr">{{ $pinHex }}</code>
                        @else
                            <span class="text-muted">{{ __('لا يوجد') }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt>
                    <dd class="num">{{ $mapLocation->created_at?->format('Y-m-d H:i') ?? '—' }} <span class="text-muted">· {{ $mapLocation->created_at?->locale(app()->getLocale())->diffForHumans() }}</span></dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $mapLocation->updated_at?->format('Y-m-d H:i') ?? '—' }} <span class="text-muted">· {{ $mapLocation->updated_at?->locale(app()->getLocale())->diffForHumans() }}</span></dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الموقع الجغرافي" icon="map">
                <div class="kv-grid mb-3">
                    <div class="kv"><div class="k">{{ __('خط العرض') }}</div><div class="v num ltr text-start">{{ $lat }}</div></div>
                    <div class="kv"><div class="k">{{ __('خط الطول') }}</div><div class="v num ltr text-start">{{ $lng }}</div></div>
                </div>
                <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="btn btn-tonal w-100">
                    <i class="bi bi-box-arrow-up-left dir-icon"></i> {{ __('عرض على Google Maps') }}
                </a>
                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <a href="{{ route('admin.map-locations.edit', $mapLocation) }}" class="btn btn-tonal"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                        <form action="{{ route('admin.map-locations.destroy', $mapLocation) }}" method="POST" class="m-0" data-confirm="{{ __('سيُحذف الموقع «:name» من الخريطة نهائيًا.', ['name' => $mapLocation->name_ar ?: $mapLocation->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('حذف') }}</button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-admin.card>
        </div>
    </div>
@endsection
