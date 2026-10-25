@extends('layouts.admin')

@section('title', 'الأماكن')
@section('page-title', 'الأماكن')
@section('page-subtitle', 'أماكن الزيارة التي تظهر في قسم اكتشف وعلى خريطة التطبيق')
@php
    $pageIcon = 'pin-map';
    $breadcrumbs = ['المحتوى' => null, 'الأماكن' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.places.map') }}" class="btn btn-tonal"><i class="bi bi-map"></i> {{ __('خريطة الأماكن') }}</a>
    <a href="{{ route('admin.places.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة مكان') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    @include('admin.discover._module-notice', ['module' => 'places'])

    <div class="stat-grid">
        <x-admin.stat-tile label="كل الأماكن" :value="$stats['total']" icon="pin-map" tone="brand" />
        <x-admin.stat-tile label="منشورة" :value="$stats['published']" icon="broadcast" tone="success" />
        <x-admin.stat-tile label="مسودات" :value="$stats['draft']" icon="pencil-square" tone="neutral" />
        <x-admin.stat-tile label="مميزة" :value="$stats['featured']" icon="star" tone="warning" />
    </div>

    <x-admin.data-table title="جميع الأماكن" icon="pin-map" :count="$places->total()" search="q" search-placeholder="ابحث بالاسم أو الولاية" :paginator="$places" data-places-table>
        <x-slot:filters>
            <select name="category" class="form-select" aria-label="{{ __('الفئة') }}">
                <option value="">{{ __('كل الفئات') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->t('name') }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="published" @selected(request('status') === 'published')>{{ __('منشور') }}</option>
                <option value="draft" @selected(request('status') === 'draft')>{{ __('مسودة') }}</option>
            </select>
            <select name="featured" class="form-select" aria-label="{{ __('مميز') }}">
                <option value="">{{ __('المميزة وغيرها') }}</option>
                <option value="1" @selected(request('featured') === '1')>{{ __('المميزة فقط') }}</option>
                <option value="0" @selected(request('featured') === '0')>{{ __('غير المميزة') }}</option>
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th class="drag-col"><span class="visually-hidden">{{ __('الترتيب') }}</span></th>
            <th>{{ __('المكان') }}</th>
            <th>{{ __('الفئة') }}</th>
            <th>{{ __('الولاية') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-center">{{ __('مميز') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @if($sortable && $places->count())
            @push('scripts')<script>(function(){var t=document.querySelector('[data-places-table] tbody');if(t){t.setAttribute('data-sortable-rows','');t.setAttribute('data-url',@json(route('admin.places.reorder')));}})();</script>@endpush
        @endif
        @forelse($places as $place)
            @php $cover = \App\Support\MediaPresenter::image($place->getFirstMedia('cover')); @endphp
            <tr data-id="{{ $place->id }}">
                <td class="drag-col">@if($sortable)<span class="drag-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span>@endif</td>
                <td>
                    <div class="cell-media">
                        <span class="ic-tile" style="--ic: {{ $place->displayColor() }}">@if($cover)<img src="{{ $cover['thumb_url'] }}" alt="">@else<span class="msr">{{ $place->displayIcon() }}</span>@endif</span>
                        <div style="min-width: 0">
                            <a class="cell-title text-decoration-none" href="{{ route('admin.places.edit', $place) }}">{{ $place->t('name') }}</a>
                            <div class="cell-sub text-truncate" style="max-width: 360px">{{ $place->t('summary') }}</div>
                        </div>
                    </div>
                </td>
                <td>@if($place->category)<span class="color-dot" style="--dot: {{ $place->category->color }}"></span> {{ $place->category->t('name') }}@else<span class="text-muted">—</span>@endif</td>
                <td>{{ $place->t('wilayat') ?: '—' }}</td>
                <td>
                    @if($place->status === 'published')
                        <x-admin.status-pill variant="success" label="منشور" />
                    @else
                        <x-admin.status-pill variant="neutral" label="مسودة" />
                    @endif
                </td>
                <td class="text-center">
                    <form method="POST" action="{{ route('admin.places.featured', $place) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="star-btn {{ $place->featured ? 'on' : '' }}" title="{{ __($place->featured ? 'إلغاء التمييز' : 'تمييز المكان') }}" aria-pressed="{{ $place->featured ? 'true' : 'false' }}">
                            <i class="bi bi-star{{ $place->featured ? '-fill' : '' }}"></i><span class="visually-hidden">{{ __('مميز') }}</span>
                        </button>
                    </form>
                </td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.places.edit', $place) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.places.destroy', $place) }}" method="POST" data-confirm="{{ __('سيُحذف المكان «:name» وصوره نهائيًا.', ['name' => $place->t('name')]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="pin-map" title="لا توجد أماكن" text="أضف مكانًا ليظهر للزوار في قسم اكتشف وعلى الخريطة.">
                <a href="{{ route('admin.places.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة مكان') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
    @if($sortable && $places->count() > 1)
        <p class="form-text"><i class="bi bi-grip-vertical"></i> {{ __('اسحب الصفوف لتغيير ترتيب الأماكن في التطبيق.') }}</p>
    @endif
@endsection

@include('admin.discover._assets')
