@extends('layouts.admin')

@section('title', 'فئات الأماكن')
@section('page-title', 'فئات الأماكن')
@section('page-subtitle', 'تحدد الفئة أيقونة المكان ولونه على الخريطة وفي قسم اكتشف')
@php
    $pageIcon = 'tags';
    $breadcrumbs = ['المحتوى' => null, 'الأماكن' => route('admin.places.index'), 'فئات الأماكن' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.place-categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة فئة') }}</a>
@endsection

@section('content')
    @include('admin.discover._module-notice', ['module' => 'places'])
    <x-admin.data-table title="الفئات" icon="tags" :count="$categories->count()">
        <x-slot:head>
            <th class="drag-col"><span class="visually-hidden">{{ __('الترتيب') }}</span></th>
            <th>{{ __('الفئة') }}</th>
            <th>{{ __('المفتاح') }}</th>
            <th>{{ __('اللون') }}</th>
            <th>{{ __('الأماكن') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @forelse($categories as $cat)
            <tr data-id="{{ $cat->id }}">
                <td class="drag-col"><span class="drag-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span></td>
                <td>
                    <div class="cell-media">
                        <span class="ic-tile" style="--ic: {{ $cat->color }}"><span class="msr">{{ $cat->icon }}</span></span>
                        <div><div class="cell-title">{{ $cat->t('name', 'ar') }}</div><div class="cell-sub" dir="ltr">{{ $cat->name['en'] ?? '' }}</div></div>
                    </div>
                </td>
                <td><code>{{ $cat->key }}</code></td>
                <td><span class="color-dot" style="--dot: {{ $cat->color }}"></span> <code>{{ $cat->color }}</code></td>
                <td class="num"><a href="{{ route('admin.places.index', ['category' => $cat->id]) }}">{{ $cat->places_count }}</a></td>
                <td><x-admin.status-pill domain="active" :status="$cat->is_active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.place-categories.edit', $cat) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.place-categories.destroy', $cat) }}" method="POST" data-confirm="{{ __('ستُحذف الفئة «:name»، وتبقى أماكنها بلا فئة.', ['name' => $cat->t('name')]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="tags" title="لا توجد فئات" text="أضف فئات مثل القلاع والشواطئ والأسواق.">
                <a href="{{ route('admin.place-categories.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة فئة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
    @push('scripts')<script>(function(){var t=document.querySelector('.dt tbody');if(t&&t.querySelector('tr[data-id]')){t.setAttribute('data-sortable-rows','');t.setAttribute('data-url',@json(route('admin.place-categories.reorder')));}})();</script>@endpush
@endsection

@include('admin.discover._assets')
