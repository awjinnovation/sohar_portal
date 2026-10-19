@extends('layouts.admin')

@section('title', 'تعديل الوسم')
@section('page-title', 'تعديل الوسم')
@section('page-subtitle', $eventTag->tag_ar)
@php
    $pageIcon = 'tags';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الوسوم' => route('admin.event-tags.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.event-tags.show', $eventTag) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.event-tags.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.event-tags.update', $eventTag) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="بيانات الوسم" icon="tag" subtitle="يظهر الاسم العربي أو الإنجليزي بحسب لغة الزائر">
                    @include('admin.event-tags._form')
                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('admin.event-tags.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                            <button type="submit" class="btn btn-primary" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                <x-admin.card title="السجل" icon="clock-history">
                    <dl class="dl">
                        <dt>{{ __('المعرّف') }}</dt>
                        <dd class="num">{{ $eventTag->id }}</dd>
                        <dt>{{ __('تاريخ الإنشاء') }}</dt>
                        <dd><span class="num" dir="ltr">{{ $eventTag->created_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                        <dt>{{ __('آخر تحديث') }}</dt>
                        <dd><span class="num" dir="ltr">{{ $eventTag->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                    </dl>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
