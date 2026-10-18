@extends('layouts.admin')

@section('title', 'عرض المطعم')
@section('page-title', 'تفاصيل المطعم')
@section('page-subtitle', $restaurant->name_ar)
@php
    $pageIcon = 'cup-hot';
    $breadcrumbs = ['المحتوى' => null, 'المطاعم والأكشاك' => route('admin.restaurants.index'), 'تفاصيل المطعم' => null];
    $features = collect($restaurant->features ?? [])->map(fn ($f) => is_array($f) ? ($f['feature_ar'] ?? $f['ar'] ?? $f['name_ar'] ?? $f['feature'] ?? $f['name'] ?? null) : $f)->filter()->values();
    $hours = collect($restaurant->opening_hours ?? [])->filter(fn ($h) => is_array($h))->values();
    $gallery = collect($restaurant->images ?? [])->map(fn ($i) => is_array($i) ? ($i['url'] ?? $i['image_url'] ?? null) : $i)
        ->merge($restaurant->getMedia('images')->map->getUrl())
        ->filter()->unique()->values();
    $lat = $restaurant->latitude !== null ? (float) $restaurant->latitude : null;
    $lng = $restaurant->longitude !== null ? (float) $restaurant->longitude : null;
@endphp
@section('page-actions')
    <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.restaurants.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@push('styles')
<style>
    .rest-cover { width: 100%; aspect-ratio: 16 / 6; object-fit: cover; display: block; background: var(--surface-low); border-start-start-radius: var(--r-lg); border-start-end-radius: var(--r-lg); }
    .rest-gallery { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .rest-gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--r-md); background: var(--surface-low); display: block; }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card :flush="true">
                @if($restaurant->image_url)
                    <img src="{{ $restaurant->image_url }}" alt="" class="rest-cover">
                @endif
                <div class="p-4">
                    <div class="d-flex flex-wrap align-items-start gap-3">
                        <div class="flex-grow-1" style="min-width: 0">
                            <h2 class="h4 fw-bold mb-1">{{ $restaurant->name_ar }}</h2>
                            <div class="text-muted ltr text-start">{{ $restaurant->name }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <x-admin.status-pill domain="active" :status="$restaurant->is_active" />
                            @if($restaurant->is_featured)
                                <x-admin.status-pill variant="warning" icon="star-fill" label="مميز" />
                            @endif
                            @if($restaurant->is_open)
                                <x-admin.status-pill variant="info" label="مفتوح" />
                            @else
                                <x-admin.status-pill variant="neutral" label="مغلق" />
                            @endif
                        </div>
                    </div>
                    <div class="kv-grid mt-4">
                        <div class="kv">
                            <div class="k">{{ __('التقييم') }}</div>
                            <div class="v num"><i class="bi bi-star-fill text-warning"></i> {{ $restaurant->rating ?? '—' }}
                                <span class="text-muted small fw-normal">({{ __(':count تقييم', ['count' => number_format((int) $restaurant->total_ratings)]) }})</span></div>
                        </div>
                        <div class="kv"><div class="k">{{ __('نوع المطبخ') }}</div><div class="v">{{ $restaurant->cuisine_ar }}</div></div>
                        <div class="kv"><div class="k">{{ __('الموقع') }}</div><div class="v">{{ $restaurant->location_ar }}</div></div>
                        <div class="kv"><div class="k">{{ __('مستوى السعر') }}</div><div class="v num ltr text-start">{{ $restaurant->price_range }}</div></div>
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
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <div class="lh-lg">{!! nl2br(e($restaurant->description_ar)) !!}</div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                        <div class="lh-lg">{!! nl2br(e($restaurant->description)) !!}</div>
                        <dl class="dl mt-3">
                            <dt>Cuisine</dt><dd>{{ $restaurant->cuisine ?: '—' }}</dd>
                            <dt>Location</dt><dd>{{ $restaurant->location ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="التواصل" icon="telephone">
                <dl class="dl">
                    <dt>{{ __('الهاتف') }}</dt>
                    <dd>@if($restaurant->phone)<a href="tel:{{ preg_replace('/\s+/', '', $restaurant->phone) }}" class="ltr d-inline-block">{{ $restaurant->phone }}</a>@else<span class="text-muted">{{ __('غير محدد') }}</span>@endif</dd>
                    <dt>{{ __('الموقع الإلكتروني') }}</dt>
                    <dd>@if($restaurant->website)<a href="{{ $restaurant->website }}" target="_blank" rel="noopener" class="ltr d-inline-block">{{ $restaurant->website }}</a>@else<span class="text-muted">{{ __('غير محدد') }}</span>@endif</dd>
                    <dt>{{ __('الإحداثيات') }}</dt>
                    <dd>
                        @if($lat !== null && $lng !== null)
                            <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="num ltr d-inline-block">{{ $lat }}, {{ $lng }}</a>
                        @else
                            <span class="text-muted">{{ __('غير محدد') }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $restaurant->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="ساعات العمل" icon="clock">
                @if($hours->isNotEmpty())
                    <dl class="dl">
                        @foreach($hours as $h)
                            <dt>{{ $h['day_ar'] ?? $h['day'] ?? $h['day_of_week'] ?? '—' }}</dt>
                            <dd class="num">
                                @if(! empty($h['is_closed']))
                                    <span class="text-danger">{{ __('مغلق') }}</span>
                                @else
                                    <span class="ltr d-inline-block">{{ $h['opening'] ?? $h['open_time'] ?? '' }} – {{ $h['closing'] ?? $h['close_time'] ?? '' }}</span>
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                @else
                    <p class="text-muted text-center mb-0">{{ __('لم يتم تحديد ساعات العمل') }}</p>
                @endif
            </x-admin.card>

            <x-admin.card title="المميزات" icon="check2-square">
                @if($features->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($features as $feature)
                            <x-admin.status-pill variant="neutral" :label="$feature" />
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center mb-0">{{ __('لا توجد مميزات محددة') }}</p>
                @endif
                @if($restaurant->has_delivery || $restaurant->has_parking || $restaurant->has_wifi)
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if($restaurant->has_delivery)<x-admin.status-pill variant="neutral" icon="truck" label="توصيل" />@endif
                        @if($restaurant->has_parking)<x-admin.status-pill variant="neutral" icon="p-square" label="مواقف" />@endif
                        @if($restaurant->has_wifi)<x-admin.status-pill variant="neutral" icon="wifi" label="واي فاي" />@endif
                    </div>
                @endif
            </x-admin.card>

            @if($gallery->isNotEmpty())
                <x-admin.card title="الصور" icon="images">
                    <div class="rest-gallery">
                        @foreach($gallery->take(4) as $img)
                            <img src="{{ $img }}" alt="{{ __('صورة المطعم') }}" loading="lazy">
                        @endforeach
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card>
                <div class="d-flex gap-2 justify-content-between align-items-center">
                    <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-tonal"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                    <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST" class="m-0" data-confirm="{{ __('سيتم حذف المطعم «:name» نهائيًا.', ['name' => $restaurant->name_ar]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('حذف') }}</button>
                    </form>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
