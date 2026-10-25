@extends('layouts.admin')

@section('title', 'عرض الفئة')
@section('page-title', 'تفاصيل الفئة')
@section('page-subtitle', $category->name_ar)
@php
    $pageIcon = 'grid';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفئات' => route('admin.categories.index'), 'التفاصيل' => null];
    $iconMap = [
        'celebration' => 'stars', 'music_note' => 'music-note-beamed', 'music-note' => 'music-note-beamed', 'sports' => 'trophy',
        'trophy' => 'trophy', 'school' => 'mortarboard', 'book' => 'book', 'palette' => 'palette', 'groups' => 'people',
        'business' => 'briefcase', 'child_care' => 'balloon', 'heart' => 'heart', 'theater_comedy' => 'mask',
        'restaurant' => 'cup-hot', 'cup-hot' => 'cup-hot', 'shop' => 'shop', 'calendar' => 'calendar-event',
    ];
    $biIcon = $iconMap[$category->icon_name] ?? (str_starts_with((string) $category->icon_name, 'bi-') ? substr($category->icon_name, 3) : 'grid');
    $hex = sprintf('#%06X', ((int) $category->color_value) & 0xFFFFFF);
@endphp
@section('page-actions')
    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.categories.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="معلومات الفئة" icon="info-circle">
                <x-slot:actions>
                    <x-admin.status-pill domain="active" :status="$category->is_active" />
                </x-slot:actions>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="cell-sub mb-1">{{ __('الاسم بالعربية') }}</div>
                        <h3 class="h5 mb-2">{{ $category->name_ar }}</h3>
                        <div class="cell-sub mb-1">{{ __('الوصف بالعربية') }}</div>
                        <p class="mb-0" style="white-space: pre-line">{{ $category->description_ar }}</p>
                    </div>
                    <div class="col-md-6" dir="ltr">
                        <div class="cell-sub mb-1">Name (English)</div>
                        <h3 class="h5 mb-2">{{ $category->name }}</h3>
                        <div class="cell-sub mb-1">Description (English)</div>
                        <p class="mb-0" style="white-space: pre-line">{{ $category->description }}</p>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="المظهر" icon="palette">
                <div class="kv-grid">
                    <div class="kv">
                        <div class="k">{{ __('الأيقونة') }}</div>
                        <div class="v d-flex align-items-center gap-2"><i class="bi bi-{{ $biIcon }}"></i><code dir="ltr">{{ $category->icon_name }}</code></div>
                    </div>
                    <div class="kv">
                        <div class="k">{{ __('اللون') }}</div>
                        <div class="v d-flex align-items-center gap-2">
                            <span class="rounded border d-inline-block" style="width: 22px; height: 22px; background: {{ $hex }}" aria-hidden="true"></span>
                            <span class="num" dir="ltr">{{ $hex }}</span>
                        </div>
                    </div>
                    <div class="kv">
                        <div class="k">{{ __('الترتيب') }}</div>
                        <div class="v num">{{ $category->display_order }}</div>
                    </div>
                </div>
                @if($category->image_url)
                    <div class="ratio ratio-21x9 rounded overflow-hidden border bg-light mt-3">
                        <img src="{{ $category->image_url }}" alt="{{ $category->name_ar }}" class="object-fit-cover">
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الفعاليات المرتبطة" icon="calendar-event" subtitle="آخر 10 فعاليات" :flush="true">
                @if($category->events && $category->events->count() > 0)
                    <ul class="tx-list">
                        @foreach($category->events as $event)
                            <li class="tx-item">
                                <span class="tx-ico tone-neutral"><i class="bi bi-calendar-event"></i></span>
                                <div class="tx-main">
                                    <a href="{{ route('admin.events.show', $event) }}" class="tx-title d-block text-reset">{{ $event->title_ar }}</a>
                                    <div class="tx-sub">{{ $event->location_ar }}</div>
                                </div>
                                <span class="tx-sub num">{{ $event->start_time->format('Y-m-d') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-admin.empty-state icon="calendar-x" title="لا توجد فعاليات مرتبطة" />
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
