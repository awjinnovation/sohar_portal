@extends('layouts.admin')

@section('title', 'الصفحات')
@section('page-title', 'الصفحات')
@section('page-subtitle', 'صفحات مستقلة مثل التعريف بالمحافظة وإرشادات الزيارة')
@php
    $pageIcon = 'file-earmark-richtext';
    $breadcrumbs = ['المحتوى' => null, 'الصفحات' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.content-pages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة صفحة') }}</a>
@endsection

@section('content')
    @include('admin.discover._module-notice', ['module' => 'content'])
    <x-admin.data-table title="الصفحات" icon="file-earmark-richtext" :count="$pages->count()">
        <x-slot:head>
            <th class="drag-col"><span class="visually-hidden">{{ __('الترتيب') }}</span></th>
            <th>{{ __('الصفحة') }}</th>
            <th>{{ __('المعرّف في الرابط') }}</th>
            <th>{{ __('في قائمة المزيد') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('آخر تحديث') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @forelse($pages as $page)
            @php $cover = \App\Support\MediaPresenter::image($page->getFirstMedia('cover')); @endphp
            <tr data-id="{{ $page->id }}">
                <td class="drag-col"><span class="drag-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span></td>
                <td>
                    <div class="cell-media">
                        <span class="ic-tile">@if($cover)<img src="{{ $cover['thumb_url'] }}" alt="">@else<span class="msr">{{ $page->icon ?: 'article' }}</span>@endif</span>
                        <div style="min-width:0">
                            <a class="cell-title text-decoration-none" href="{{ route('admin.content-pages.edit', $page) }}">{{ $page->t('title') }}</a>
                            <div class="cell-sub text-truncate" style="max-width: 360px">{{ \App\Support\HtmlSanitizer::toText($page->t('body'), 90) }}</div>
                        </div>
                    </div>
                </td>
                <td><code>{{ $page->slug }}</code></td>
                <td>@if($page->show_in_more)<i class="bi bi-check-circle-fill text-success"></i>@else<span class="text-muted">—</span>@endif</td>
                <td>@if($page->status === 'published')<x-admin.status-pill variant="success" label="منشور" />@else<x-admin.status-pill variant="neutral" label="مسودة" />@endif</td>
                <td class="num text-muted">{{ $page->updated_at?->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.content-pages.edit', $page) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.content-pages.destroy', $page) }}" method="POST" data-confirm="{{ __('ستُحذف الصفحة «:name» نهائيًا.', ['name' => $page->t('title')]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="file-earmark-richtext" title="لا توجد صفحات" text="أضف صفحة تعريفية أو صفحة إرشادات للزوار.">
                <a href="{{ route('admin.content-pages.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة صفحة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
    @push('scripts')<script>(function(){var t=document.querySelector('.dt tbody');if(t&&t.querySelector('tr[data-id]')){t.setAttribute('data-sortable-rows','');t.setAttribute('data-url',@json(route('admin.content-pages.reorder')));}})();</script>@endpush
@endsection

@include('admin.discover._assets')
