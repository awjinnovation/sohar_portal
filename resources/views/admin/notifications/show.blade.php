@extends('layouts.admin')

@section('title', 'تفاصيل الإشعار')
@section('page-title', 'تفاصيل الإشعار')
@section('page-subtitle', $notification->title_ar ?: $notification->title)
@php
    $pageIcon = 'bell';
    $breadcrumbs = ['المحتوى' => null, 'الإشعارات' => route('admin.notifications.index'), 'تفاصيل الإشعار' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.notifications.edit', $notification) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.notifications.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="نص الإشعار" icon="translate">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-4">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <h3 class="h5 fw-bold mb-3">{{ $notification->title_ar }}</h3>
                        <div class="lh-lg">{!! nl2br(e($notification->body_ar)) !!}</div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                        <h3 class="h5 fw-bold mb-3">{{ $notification->title }}</h3>
                        <div class="lh-lg">{!! nl2br(e($notification->body)) !!}</div>
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="التفاصيل" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('المعرف') }}</dt>
                    <dd class="num">#{{ $notification->id }}</dd>
                    <dt>{{ __('المستلم') }}</dt>
                    <dd>
                        @if($recipient)
                            <div class="fw-semibold">{{ $recipient->name }}</div>
                            <div class="cell-sub ltr text-start">{{ $recipient->email ?: $recipient->phone_number }}</div>
                        @else
                            <x-admin.status-pill variant="neutral" icon="broadcast" label="عام" />
                        @endif
                    </dd>
                    <dt>{{ __('النوع') }}</dt>
                    <dd><code>{{ $notification->type ?: 'general' }}</code></dd>
                    <dt>{{ __('الحالة') }}</dt>
                    <dd>
                        @if($notification->is_read)
                            <x-admin.status-pill variant="success" label="مقروء" />
                        @else
                            <x-admin.status-pill variant="info" label="غير مقروء" />
                        @endif
                    </dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt>
                    <dd class="num">{{ $notification->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $notification->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <a href="{{ route('admin.notifications.edit', $notification) }}" class="btn btn-tonal"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                        <form action="{{ route('admin.notifications.destroy', $notification) }}" method="POST" class="m-0" data-confirm="{{ __('سيتم حذف هذا الإشعار نهائيًا.') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('حذف') }}</button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-admin.card>
        </div>
    </div>
@endsection
