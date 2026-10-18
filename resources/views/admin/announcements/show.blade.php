@extends('layouts.admin')

@section('title', 'تفاصيل الإعلان')
@section('page-title', 'تفاصيل الإعلان')
@section('page-subtitle', $announcement->title_ar)
@php
    $pageIcon = 'megaphone';
    $breadcrumbs = ['المحتوى' => null, 'الإعلانات' => route('admin.announcements.index'), 'تفاصيل الإعلان' => null];
    $audiences = ['all' => __('الجميع'), 'visitors' => __('الزوار'), 'vendors' => __('البائعون'), 'staff' => __('الموظفون')];
    $extraTypes = ['alert' => ['error', 'تنبيه'], 'success' => ['success', 'نجاح']];
@endphp
@section('page-actions')
    <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.announcements.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="المحتوى" icon="translate" subtitle="النص كما يظهر للمستخدم حسب لغته">
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
                        <h3 class="h5 fw-bold mb-3">{{ $announcement->title_ar }}</h3>
                        <div class="lh-lg">{!! nl2br(e($announcement->content_ar)) !!}</div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                        <h3 class="h5 fw-bold mb-3">{{ $announcement->title }}</h3>
                        <div class="lh-lg">{!! nl2br(e($announcement->content)) !!}</div>
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="التفاصيل" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('النوع') }}</dt>
                    <dd>
                        @if(isset($extraTypes[$announcement->type]))
                            <x-admin.status-pill :variant="$extraTypes[$announcement->type][0]" :label="$extraTypes[$announcement->type][1]" />
                        @else
                            <x-admin.status-pill domain="announcement" :status="$announcement->type" />
                        @endif
                    </dd>
                    <dt>{{ __('الحالة') }}</dt>
                    <dd><x-admin.status-pill domain="active" :status="$announcement->is_active" /></dd>
                    <dt>{{ __('التثبيت') }}</dt>
                    <dd>
                        @if($announcement->is_pinned)
                            <x-admin.status-pill variant="info" icon="pin-angle-fill" label="مثبت" />
                        @else
                            <x-admin.status-pill variant="neutral" label="غير مثبت" />
                        @endif
                    </dd>
                    <dt>{{ __('الأولوية') }}</dt>
                    <dd class="num">{{ $announcement->priority ?? 0 }}</dd>
                    <dt>{{ __('الجمهور المستهدف') }}</dt>
                    <dd>{{ $audiences[$announcement->target_audience ?? 'all'] ?? $announcement->target_audience }}</dd>
                    <dt>{{ __('تاريخ البداية') }}</dt>
                    <dd class="num">{{ $announcement->start_datetime?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt>{{ __('تاريخ النهاية') }}</dt>
                    <dd class="num">{{ $announcement->end_datetime?->format('Y-m-d H:i') ?? '—' }}</dd>
                    @if($announcement->creator)
                        <dt>{{ __('أنشئ بواسطة') }}</dt>
                        <dd>{{ $announcement->creator->name }}</dd>
                    @endif
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $announcement->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-tonal"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                        <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" class="m-0" data-confirm="{{ __('سيتم حذف الإعلان «:name» نهائيًا.', ['name' => $announcement->title_ar]) }}">
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
