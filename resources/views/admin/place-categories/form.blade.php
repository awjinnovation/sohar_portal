@extends('layouts.admin')
@php
    $isEdit = $category->exists;
    $pageIcon = 'tags';
    $breadcrumbs = ['المحتوى' => null, 'فئات الأماكن' => route('admin.place-categories.index'), ($isEdit ? 'تعديل فئة' : 'إضافة فئة') => null];
@endphp
@section('title', $isEdit ? 'تعديل فئة' : 'إضافة فئة')
@section('page-title', $isEdit ? 'تعديل فئة' : 'إضافة فئة')
@section('page-actions')
    <a href="{{ route('admin.place-categories.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    <form action="{{ $isEdit ? route('admin.place-categories.update', $category) : route('admin.place-categories.store') }}" method="POST">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="الفئة" icon="tags">
                    <div class="row">
                        <x-admin.form-field col="col-md-6" name="name[ar]" id="name_ar" label="الاسم بالعربية" :value="old('name.ar', $category->name['ar'] ?? '')" placeholder="مثل قلاع وحصون" />
                        <x-admin.form-field col="col-md-6" name="name[en]" id="name" label="الاسم بالإنجليزية" dir="ltr" :value="old('name.en', $category->name['en'] ?? '')" placeholder="Forts & castles" />
                        <x-admin.form-field col="col-md-6" name="key" label="المفتاح" dir="ltr" :value="old('key', $category->key)" placeholder="forts" help="حروف إنجليزية صغيرة وأرقام، ويستخدمه التطبيق في التصفية" />
                        <x-admin.form-field col="col-md-6" name="sort" label="الترتيب" type="number" min="0" dir="ltr" :value="old('sort', $category->sort)" />
                    </div>
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                <x-admin.card title="المظهر" icon="palette">
                    <x-admin.icon-picker name="icon" :value="old('icon', $category->icon)" fallback="place" :color="$category->color" label="الأيقونة" :required="true" />
                    <x-admin.color-picker name="color" id="color" label="اللون" :value="old('color', $category->color)" against="#FFFFFF" :required="true" />
                    <x-admin.toggle name="is_active" label="نشطة" description="تظهر الفئة وأماكنها في التطبيق" :checked="old('is_active', $category->is_active)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ الفئة') }}</button>
                            <a href="{{ route('admin.place-categories.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
    @push('scripts')<script>(function(){var c=document.getElementById('color'),p=document.querySelector('[data-ip-preview]'),w=c&&c.closest('[data-color-field]');function s(){setTimeout(function(){if(p&&/^#[0-9a-f]{6}$/i.test(c.value))p.style.setProperty('--ip',c.value);},0);}if(w){w.addEventListener('input',s);w.addEventListener('change',s);}})();</script>@endpush
@endsection

@include('admin.discover._assets')
