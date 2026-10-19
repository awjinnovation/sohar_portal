@extends('layouts.admin')

@section('title', 'تفاصيل وسم الفعالية')
@section('page-title', 'تفاصيل وسم الفعالية')
@section('page-subtitle', $eventTag->tag_ar)
@php
    $pageIcon = 'tags';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الوسوم' => route('admin.event-tags.index'), 'التفاصيل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.event-tags.edit', $eventTag) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.event-tags.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="بيانات الوسم" icon="tag">
                <dl class="dl">
                    <dt>{{ __('المعرّف') }}</dt>
                    <dd class="num">{{ $eventTag->id }}</dd>
                    <dt>{{ __('الوسم بالعربية') }}</dt>
                    <dd><span class="pill pill-neutral"><i class="bi bi-tag"></i>{{ $eventTag->tag_ar }}</span></dd>
                    <dt>Tag (English)</dt>
                    <dd><span dir="ltr">{{ $eventTag->tag }}</span></dd>
                    <dt>{{ __('الفعالية') }}</dt>
                    <dd>
                        @if($eventTag->event)
                            <a href="{{ route('admin.events.show', $eventTag->event) }}">{{ $eventTag->event->title_ar ?: $eventTag->event->title }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt>
                    <dd><span class="num" dir="ltr">{{ $eventTag->created_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd><span class="num" dir="ltr">{{ $eventTag->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                </dl>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="إجراءات" icon="lightning">
                <div class="d-grid gap-2">
                    @if($eventTag->event)
                        <a href="{{ route('admin.event-tags.index', ['event_id' => $eventTag->event_id]) }}" class="btn btn-tonal"><i class="bi bi-tags"></i> {{ __('وسوم هذه الفعالية') }}</a>
                    @endif
                    <form action="{{ route('admin.event-tags.destroy', $eventTag) }}" method="POST" data-confirm="{{ __('سيُحذف الوسم «:name» نهائيًا.', ['name' => $eventTag->tag_ar]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> {{ __('حذف الوسم') }}</button>
                    </form>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
