@extends('layouts.admin')
@php
    $isEdit = $page->exists;
    $pageIcon = 'file-earmark-richtext';
    $breadcrumbs = ['المحتوى' => null, 'الصفحات' => route('admin.content-pages.index'), ($isEdit ? 'تعديل صفحة' : 'إضافة صفحة') => null];
    $v = fn (string $field, string $lang) => old($field.'.'.$lang, $page->{$field}[$lang] ?? '');
    $cover = \App\Support\MediaPresenter::image($page->getFirstMedia('cover'));
@endphp
@section('title', $isEdit ? 'تعديل صفحة' : 'إضافة صفحة')
@section('page-title', $isEdit ? 'تعديل صفحة' : 'إضافة صفحة')
@section('page-subtitle', $isEdit ? $page->t('title') : 'صفحة مستقلة تظهر في قسم اكتشف وفي قائمة المزيد')
@section('page-actions')
    <a href="{{ route('admin.content-pages.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    @include('admin.discover._module-notice', ['module' => 'content'])
    <form action="{{ $isEdit ? route('admin.content-pages.update', $page) : route('admin.content-pages.store') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="نص الصفحة" icon="translate">
                    <x-admin.lang-tabs id="page">
                        <x-slot:ar>
                            <x-admin.form-field name="title[ar]" id="title_ar" label="العنوان" :value="$v('title', 'ar')" />
                            <x-admin.rich-text name="body[ar]" id="body_ar" label="النص" :value="$v('body', 'ar')" dir="rtl" rows="14" />
                        </x-slot:ar>
                        <x-slot:en>
                            <x-admin.form-field name="title[en]" id="title" label="Title" :value="$v('title', 'en')" />
                            <x-admin.rich-text name="body[en]" id="body" label="Text" :value="$v('body', 'en')" dir="ltr" rows="14" />
                        </x-slot:en>
                    </x-admin.lang-tabs>
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                <x-admin.card title="النشر" icon="broadcast">
                    <x-admin.status-select :value="$page->status" />
                    <x-admin.toggle name="show_in_more" label="تظهر في قائمة المزيد" description="رابط إلى الصفحة في شاشة المزيد بالتطبيق" :checked="old('show_in_more', $page->show_in_more)" />
                    <div class="row">
                        <x-admin.form-field col="col-6" name="sort" label="الترتيب" type="number" min="0" dir="ltr" :value="old('sort', $page->sort)" />
                        <x-admin.form-field col="col-6" name="slug" label="المعرّف في الرابط" dir="ltr" :value="old('slug', $page->slug)" />
                    </div>
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ الصفحة') }}</button>
                            <a href="{{ route('admin.content-pages.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
                <x-admin.card title="المظهر" icon="palette">
                    <x-admin.icon-picker name="icon" :value="old('icon', $page->icon)" fallback="article" label="الأيقونة" />
                    <div data-image-field>
                        <x-admin.file-upload name="cover" label="صورة الغلاف" :current="$cover['thumb_url'] ?? null" accept="image/jpeg,image/png,image/webp,image/gif" help="اختيارية، وتظهر أعلى الصفحة" icon="image" />
                        @if($cover)
                            <div class="form-check mt-n2">
                                <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="remove_cover" data-remove-image>
                                <label class="form-check-label small" for="remove_cover">{{ __('حذف صورة الغلاف') }}</label>
                            </div>
                        @endif
                    </div>
                </x-admin.card>
                @include('admin.discover._copilot', [
                    'kind' => 'page', 'permission' => 'manage content',
                    'fields' => ['title', 'body'],
                    'describe' => ['title' => 'title', 'title_ar' => 'title_ar', 'notes' => 'body_ar'],
                    'out' => ['description_ar' => 'body_ar', 'description' => 'body'],
                ])
            </div>
        </div>
    </form>
@endsection

@include('admin.discover._assets')
