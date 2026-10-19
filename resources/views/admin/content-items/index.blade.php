@extends('layouts.admin')

@section('title', $collection->t('name'))
@section('page-title', $collection->t('name'))
@section('page-subtitle', 'عناصر المجموعة كما تظهر في التطبيق')
@php
    $pageIcon = 'collection';
    $breadcrumbs = ['المحتوى' => null, 'المجموعات' => route('admin.content-collections.index'), $collection->t('name') => null];
    $isLogos = $collection->layout === 'logos';
@endphp
@section('page-actions')
    <a href="{{ route('admin.content-collections.edit', $collection) }}" class="btn btn-tonal"><i class="bi bi-gear"></i> {{ __('إعدادات المجموعة') }}</a>
    <a href="{{ route('admin.content-items.create', $collection) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة عنصر') }}</a>
@endsection

@section('content')
    @include('admin.discover._module-notice', ['module' => 'content'])
    <x-admin.data-table title="العناصر" icon="list-ul" :count="$items->total()" search="q" search-placeholder="ابحث بالعنوان أو الوسم" :paginator="$items">
        <x-slot:filters>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="published" @selected(request('status') === 'published')>{{ __('منشور') }}</option>
                <option value="draft" @selected(request('status') === 'draft')>{{ __('مسودة') }}</option>
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th class="drag-col"><span class="visually-hidden">{{ __('الترتيب') }}</span></th>
            <th>{{ __('العنصر') }}</th>
            <th>{{ __('الوسوم') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @forelse($items as $item)
            @php $img = \App\Support\MediaPresenter::image($item->getFirstMedia('image')); @endphp
            <tr data-id="{{ $item->id }}">
                <td class="drag-col">@if($sortable)<span class="drag-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span>@endif</td>
                <td>
                    <div class="cell-media">
                        <span class="ic-tile {{ $isLogos ? 'logo' : '' }}">@if($img)<img src="{{ $img['thumb_url'] }}" alt="">@else<span class="msr">{{ $item->icon ?: ($collection->icon ?: 'article') }}</span>@endif</span>
                        <div style="min-width:0">
                            <a class="cell-title text-decoration-none" href="{{ route('admin.content-items.edit', [$collection, $item]) }}">{{ $item->t('title') }}</a>
                            @if($item->featured)<i class="bi bi-star-fill text-warning ms-1" title="{{ __('مميز') }}"></i>@endif
                            <div class="cell-sub text-truncate" style="max-width: 360px">{{ $item->t('subtitle') }}</div>
                        </div>
                    </div>
                </td>
                <td>@foreach((array) $item->tags as $tag)<span class="pill pill-neutral pill-sm me-1">{{ $tag }}</span>@endforeach</td>
                <td>@if($item->status === 'published')<x-admin.status-pill variant="success" label="منشور" />@else<x-admin.status-pill variant="neutral" label="مسودة" />@endif</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.content-items.edit', [$collection, $item]) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.content-items.destroy', [$collection, $item]) }}" method="POST" data-confirm="{{ __('سيُحذف العنصر «:name» نهائيًا.', ['name' => $item->t('title')]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="5" :icon="$isLogos ? 'award' : 'list-ul'" :title="$isLogos ? 'لم يُضف أي شريك بعد' : 'لا توجد عناصر'" :text="$isLogos ? 'أضف الشركاء والرعاة مع شعاراتهم.' : 'أضف أول عنصر في هذه المجموعة.'">
                <a href="{{ route('admin.content-items.create', $collection) }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة عنصر') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
    @if($sortable)
        @push('scripts')<script>(function(){var t=document.querySelector('.dt tbody');if(t&&t.querySelector('tr[data-id]')){t.setAttribute('data-sortable-rows','');t.setAttribute('data-url',@json(route('admin.content-items.reorder', $collection)));}})();</script>@endpush
    @endif
@endsection

@include('admin.discover._assets')
