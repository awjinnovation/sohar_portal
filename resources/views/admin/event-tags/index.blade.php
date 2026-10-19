@extends('layouts.admin')

@section('title', 'وسوم الفعاليات')
@section('page-title', 'إدارة وسوم الفعاليات')
@section('page-subtitle', 'كلمات قصيرة تصف الفعاليات وتساعد الزوار على التصفية')
@php
    $pageIcon = 'tags';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الوسوم' => null];
    $filteredEvent = request()->filled('event_id') ? $tags->first()?->event : null;
@endphp
@section('page-actions')
    <a href="{{ route('admin.event-tags.create', request()->filled('event_id') ? ['event_id' => request('event_id')] : []) }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة وسم جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الوسوم" icon="tags" :count="$tags->total()" :paginator="$tags">
        @if(request()->filled('event_id'))
            <x-slot:actions>
                <span class="pill pill-brand">
                    <i class="bi bi-funnel"></i>
                    {{ $filteredEvent ? ($filteredEvent->title_ar ?: $filteredEvent->title) : __('فعالية محددة') }}
                    <a href="{{ route('admin.event-tags.index') }}" class="text-reset ms-1" aria-label="{{ __('إزالة التصفية') }}"><i class="bi bi-x-lg"></i></a>
                </span>
            </x-slot:actions>
        @endif
        <x-slot:head>
            <th>{{ __('الوسم') }}</th>
            <th>Tag</th>
            <th>{{ __('الفعالية') }}</th>
            <th>{{ __('تاريخ الإضافة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($tags as $tag)
            <tr>
                <td><span class="pill pill-neutral"><i class="bi bi-tag"></i>{{ $tag->tag_ar }}</span></td>
                <td><span dir="ltr">{{ $tag->tag }}</span></td>
                <td>
                    @if($tag->event)
                        <a href="{{ route('admin.events.show', $tag->event) }}" class="cell-title">{{ $tag->event->title_ar ?: $tag->event->title }}</a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="num text-muted">{{ $tag->created_at?->format('Y-m-d') ?? '—' }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.event-tags.show', $tag) }}" class="icon-btn" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.event-tags.edit', $tag) }}" class="icon-btn brand" title="{{ __('تعديل') }}" aria-label="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.event-tags.destroy', $tag) }}" method="POST" data-confirm="{{ __('سيُحذف الوسم «:name» نهائيًا.', ['name' => $tag->tag_ar]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}" aria-label="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="5" icon="tags" title="لا توجد وسوم" text="أضف وسومًا لوصف الفعاليات ومساعدة الزوار على التصفية.">
                <a href="{{ route('admin.event-tags.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة وسم') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
