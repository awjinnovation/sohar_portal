@extends('layouts.admin')

@section('title', 'تعديل الإعلان')
@section('page-title', 'تعديل الإعلان')
@section('page-subtitle', $announcement->title_ar)
@php
    $pageIcon = 'megaphone';
    $breadcrumbs = ['المحتوى' => null, 'الإعلانات' => route('admin.announcements.index'), 'تعديل' => null];
    $typeOptions = \App\Models\Announcement::TYPE_LABELS;
    // Keep a stored type selectable even if it is not one of the standard choices.
    $legacyTypes = ['alert' => 'تنبيه', 'success' => 'نجاح', 'critical' => 'عاجل', 'promo' => 'ترويجي'];
    if ($announcement->type && ! isset($typeOptions[$announcement->type])) {
        $typeOptions[$announcement->type] = $legacyTypes[$announcement->type] ?? $announcement->type;
    }
@endphp
@section('page-actions')
    <a href="{{ route('admin.announcements.show', $announcement) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.announcements.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="المحتوى" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي بحسب لغة الزائر">
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
                            <x-admin.form-field name="title_ar" label="العنوان بالعربية" :value="old('title_ar', $announcement->title_ar)" required />
                            <x-admin.form-field name="content_ar" label="المحتوى بالعربية" type="textarea" rows="5" :value="old('content_ar', $announcement->content_ar)" required />
                        </div>
                        <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                            <x-admin.form-field name="title" label="Title (English)" :value="old('title', $announcement->title)" required />
                            <x-admin.form-field name="content" label="Content (English)" type="textarea" rows="5" :value="old('content', $announcement->content)" required />
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                @include('admin.ai._announcement-copilot')
                <x-admin.card title="الإعدادات" icon="sliders">
                    <x-admin.form-field name="type" label="النوع" type="select" required placeholder="اختر النوع" :value="old('type', $announcement->type)"
                        :options="$typeOptions" />
                    <div class="row">
                        <x-admin.form-field col="col-6" name="priority" label="الأولوية" type="number" min="0" :value="old('priority', $announcement->priority)" />
                        <x-admin.form-field col="col-6" name="target_audience" label="الجمهور المستهدف" type="select" :value="old('target_audience', $announcement->target_audience)"
                            :options="['all' => 'الجميع', 'visitors' => 'الزوار', 'vendors' => 'التجار', 'staff' => 'الموظفون']" />
                    </div>
                    <x-admin.form-field name="start_datetime" label="تاريخ ووقت البداية" type="datetime-local" required
                        :value="old('start_datetime', $announcement->start_datetime?->format('Y-m-d\TH:i'))" />
                    <x-admin.form-field name="end_datetime" label="تاريخ ووقت النهاية" type="datetime-local"
                        :value="old('end_datetime', $announcement->end_datetime?->format('Y-m-d\TH:i'))" />
                    <x-admin.toggle name="is_pinned" id="is_pinned" label="تثبيت الإعلان" description="يظهر أولًا في القائمة" :hidden="true" :checked="old('is_pinned', $announcement->is_pinned)" />
                    <x-admin.toggle name="is_active" id="is_active" label="نشط" description="مرئي في التطبيق" :hidden="true" :checked="old('is_active', $announcement->is_active)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                            <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
