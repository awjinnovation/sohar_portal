@extends('layouts.admin')

@section('title', 'المجموعات')
@section('page-title', 'المجموعات')
@section('page-subtitle', 'قوائم المحتوى مثل خدمات الزوار والشركاء ودليل الزائر')
@php
    $pageIcon = 'collection';
    $breadcrumbs = ['المحتوى' => null, 'المجموعات' => null];
    $layoutLabels = ['list' => 'قائمة', 'grid' => 'شبكة', 'logos' => 'شعارات', 'cards' => 'بطاقات'];
@endphp
@section('page-actions')
    <a href="{{ route('admin.content-collections.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إضافة مجموعة') }}</a>
@endsection

@section('content')
    @include('admin.discover._module-notice', ['module' => 'content'])
    <x-admin.data-table title="المجموعات" icon="collection" :count="$collections->count()">
        <x-slot:head>
            <th class="drag-col"><span class="visually-hidden">{{ __('الترتيب') }}</span></th>
            <th>{{ __('المجموعة') }}</th>
            <th>{{ __('المفتاح') }}</th>
            <th>{{ __('طريقة العرض') }}</th>
            <th>{{ __('العناصر') }}</th>
            <th>{{ __('في قسم اكتشف') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @forelse($collections as $c)
            <tr data-id="{{ $c->id }}">
                <td class="drag-col"><span class="drag-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span></td>
                <td>
                    <div class="cell-media">
                        <span class="ic-tile"><span class="msr">{{ $c->icon ?: 'list' }}</span></span>
                        <div style="min-width:0">
                            <a class="cell-title text-decoration-none" href="{{ route('admin.content-items.index', $c) }}">{{ $c->t('name') }}</a>
                            <div class="cell-sub text-truncate" style="max-width: 320px">{{ $c->t('description') }}</div>
                        </div>
                    </div>
                </td>
                <td><code>{{ $c->key }}</code></td>
                <td>{{ __($layoutLabels[$c->layout] ?? $c->layout) }}</td>
                <td class="num">
                    <a href="{{ route('admin.content-items.index', $c) }}">{{ $c->published_items_count }} / {{ $c->items_count }}</a>
                    @if($c->items_count === 0)<div class="cell-sub">{{ __('فارغة') }}</div>@endif
                </td>
                <td>@if($c->show_in_discover)<i class="bi bi-check-circle-fill text-success"></i>@else<span class="text-muted">—</span>@endif</td>
                <td><x-admin.status-pill domain="active" :status="$c->is_active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.content-items.index', $c) }}" class="icon-btn" title="{{ __('العناصر') }}"><i class="bi bi-list-ul"></i></a>
                        <a href="{{ route('admin.content-collections.edit', $c) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.content-collections.destroy', $c) }}" method="POST" data-confirm="{{ __('ستُحذف المجموعة «:name» وجميع عناصرها نهائيًا.', ['name' => $c->t('name')]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" icon="collection" title="لا توجد مجموعات" text="أنشئ مجموعة مثل خدمات الزوار أو الشركاء.">
                <a href="{{ route('admin.content-collections.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة مجموعة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
    <p class="form-text">{!! str_replace(':code', '<code dir="ltr">collection:'.e($collections->first()->key ?? 'services').'</code>', e(__('لعرض مجموعة في الصفحة الرئيسية للتطبيق أضف :code إلى قائمة home_blocks في الهوية والإعدادات.'))) !!}</p>
    @push('scripts')<script>(function(){var t=document.querySelector('.dt tbody');if(t&&t.querySelector('tr[data-id]')){t.setAttribute('data-sortable-rows','');t.setAttribute('data-url',@json(route('admin.content-collections.reorder')));}})();</script>@endpush
@endsection

@include('admin.discover._assets')
