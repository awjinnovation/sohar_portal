@extends('layouts.admin')

@section('title', 'الفئات')
@section('page-title', 'إدارة الفئات')
@section('page-subtitle', 'تصنيفات الفعاليات وترتيب ظهورها في التطبيق')
@php
    $pageIcon = 'grid';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفئات' => null];
    // App icon names (Material / legacy) → bootstrap-icons, for the admin preview only.
    $iconMap = [
        'celebration' => 'stars', 'music_note' => 'music-note-beamed', 'music-note' => 'music-note-beamed', 'sports' => 'trophy',
        'trophy' => 'trophy', 'school' => 'mortarboard', 'book' => 'book', 'palette' => 'palette', 'groups' => 'people',
        'business' => 'briefcase', 'child_care' => 'balloon', 'heart' => 'heart', 'theater_comedy' => 'mask',
        'restaurant' => 'cup-hot', 'cup-hot' => 'cup-hot', 'shop' => 'shop', 'calendar' => 'calendar-event',
    ];
    $iconFor = fn ($name) => $iconMap[$name] ?? (str_starts_with((string) $name, 'bi-') ? substr($name, 3) : 'grid');
@endphp
@section('page-actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة فئة جديدة') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الفئات" icon="grid" :count="$categories->total()" :paginator="$categories">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('الفئة') }}</th>
            <th>{{ __('الأيقونة') }}</th>
            <th>{{ __('اللون') }}</th>
            <th>{{ __('الترتيب') }}</th>
            <th>{{ __('عدد الفعاليات') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($categories as $category)
            @php $hex = sprintf('#%06X', ((int) $category->color_value) & 0xFFFFFF); @endphp
            <tr>
                <td class="num text-muted">{{ $category->id }}</td>
                <td>
                    <div class="cell-media">
                        @if($category->image_url)
                            <img src="{{ $category->image_url }}" alt="" loading="lazy">
                        @else
                            <span class="ph"><i class="bi bi-{{ $iconFor($category->icon_name) }}"></i></span>
                        @endif
                        <div style="min-width: 0">
                            <a href="{{ route('admin.categories.show', $category) }}" class="cell-title d-block">{{ $category->name_ar }}</a>
                            <div class="cell-sub"><span dir="ltr">{{ $category->name }}</span></div>
                        </div>
                    </div>
                </td>
                <td><code dir="ltr">{{ $category->icon_name }}</code></td>
                <td class="nowrap">
                    <span class="d-inline-flex align-items-center gap-2">
                        <span class="rounded-circle border d-inline-block" style="width: 14px; height: 14px; background: {{ $hex }}" aria-hidden="true"></span>
                        <span class="num small text-muted" dir="ltr">{{ $hex }}</span>
                    </span>
                </td>
                <td class="num">{{ $category->display_order }}</td>
                <td class="num">{{ number_format($category->events_count ?? $category->events()->count()) }}</td>
                <td><x-admin.status-pill domain="active" :status="$category->is_active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.categories.show', $category) }}" class="icon-btn" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.categories.edit', $category) }}" class="icon-btn brand" title="{{ __('تعديل') }}" aria-label="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" data-confirm="{{ __('سيتم حذف الفئة «:name» نهائيًا.', ['name' => $category->name_ar]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}" aria-label="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" icon="grid" title="لا توجد فئات حتى الآن" text="أنشئ فئة لتصنيف الفعاليات في التطبيق.">
                <a href="{{ route('admin.categories.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة فئة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
