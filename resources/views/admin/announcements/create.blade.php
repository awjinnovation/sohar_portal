@extends('layouts.admin')

@section('title', 'إضافة إعلان')
@section('page-title', 'إضافة إعلان جديد')
@php
    $pageIcon = 'megaphone';
    $breadcrumbs = ['المحتوى' => null, 'الإعلانات' => route('admin.announcements.index'), 'إضافة إعلان' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.announcements.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.announcements.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="المحتوى" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي حسب لغة المستخدم">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3">
                        <div class="tab-pane fade show active" id="ar" role="tabpanel">
                            <x-admin.form-field name="title_ar" label="العنوان (العربية)" :value="old('title_ar')" required />
                            <x-admin.form-field name="content_ar" label="المحتوى (العربية)" type="textarea" rows="5" :value="old('content_ar')" required />
                        </div>
                        <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                            <x-admin.form-field name="title" label="Title (English)" :value="old('title')" required />
                            <x-admin.form-field name="content" label="Content (English)" type="textarea" rows="5" :value="old('content')" required />
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="الإعدادات" icon="sliders">
                    <x-admin.form-field name="type" label="النوع" type="select" required placeholder="اختر النوع" :value="old('type')"
                        :options="\App\Models\Announcement::TYPE_LABELS" />
                    <div class="row">
                        <x-admin.form-field col="col-6" name="priority" label="الأولوية" type="number" min="0" :value="old('priority', 0)" />
                        <x-admin.form-field col="col-6" name="target_audience" label="الجمهور المستهدف" type="select" :value="old('target_audience')"
                            :options="['all' => 'الجميع', 'visitors' => 'الزوار', 'vendors' => 'البائعون', 'staff' => 'الموظفون']" />
                    </div>
                    <x-admin.form-field name="start_datetime" label="تاريخ ووقت البداية" type="datetime-local" :value="old('start_datetime')" required />
                    <x-admin.form-field name="end_datetime" label="تاريخ ووقت النهاية" type="datetime-local" :value="old('end_datetime')" />
                    <x-admin.toggle name="is_pinned" id="is_pinned" label="تثبيت الإعلان" description="يظهر أولاً في القائمة" :checked="old('is_pinned')" />
                    <x-admin.toggle name="is_active" id="is_active" label="نشط" description="مرئي في التطبيق" :checked="old('is_active', true)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ') }}</button>
                            <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
