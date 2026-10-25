@extends('layouts.admin')

@section('title', 'تعديل الفئة')
@section('page-title', 'تعديل الفئة')
@section('page-subtitle', $category->name_ar)
@php
    $pageIcon = 'grid';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفئات' => route('admin.categories.index'), 'تعديل' => null];
    $model = $category;
    $colorInt = old('color_value', $category->color_value);
    $colorHex = sprintf('#%06X', ((int) $colorInt) & 0xFFFFFF);
@endphp
@section('page-actions')
    <a href="{{ route('admin.categories.show', $category) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.categories.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.categories.update', $category) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="معلومات الفئة" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي بحسب لغة الزائر">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="arabic-tab" data-bs-toggle="tab" data-bs-target="#arabic-content" type="button" role="tab">{{ __('العربية') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="english-tab" data-bs-toggle="tab" data-bs-target="#english-content" type="button" role="tab">English</button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3">
                        <div class="tab-pane fade show active" id="arabic-content" role="tabpanel">
                            <x-admin.form-field name="name_ar" label="اسم الفئة" :value="old('name_ar', $model?->name_ar)" required />
                            <x-admin.form-field name="description_ar" label="الوصف" type="textarea" rows="3" :value="old('description_ar', $model?->description_ar)" required />
                        </div>
                        <div class="tab-pane fade" id="english-content" role="tabpanel" dir="ltr">
                            <x-admin.form-field name="name" label="Category Name" :value="old('name', $model?->name)" required />
                            <x-admin.form-field name="description" label="Description" type="textarea" rows="3" :value="old('description', $model?->description)" required />
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="المظهر" icon="palette">
                    <x-admin.form-field name="icon_name" label="الأيقونة" dir="ltr" required :value="old('icon_name', $category->icon_name)"
                        help="اسم أيقونة التطبيق، مثل music_note أو sports" />

                    <div class="field">
                        <label for="color_picker" class="form-label">{{ __('اللون') }}<span class="req">*</span></label>
                        <div class="color-field">
                            <span class="swatch"><span id="color_swatch" style="background: {{ $colorHex }}"></span>
                                <input type="color" id="color_picker" value="{{ strtolower($colorHex) }}" aria-label="{{ __('اللون') }}"></span>
                            <input type="text" id="color_hex" class="form-control" value="{{ $colorHex }}" dir="ltr" readonly tabindex="-1" aria-label="{{ __('رمز اللون') }}">
                        </div>
                        <input type="hidden" id="color_value" name="color_value" value="{{ $colorInt }}">
                        @error('color_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <x-admin.form-field name="image_url" label="رابط الصورة" type="url" dir="ltr" placeholder="https://example.com/image.jpg" :value="old('image_url', $model?->image_url)" />
                </x-admin.card>

                <x-admin.card title="النشر" icon="broadcast">
                    <x-admin.form-field name="display_order" label="الترتيب" type="number" min="0" dir="ltr" :value="old('display_order', $model ? $model->display_order : 0)"
                        help="يظهر الرقم الأصغر أولًا" />
                    <x-admin.toggle name="is_active" id="is_active" label="نشط" description="تظهر الفئة في التطبيق" :checked="old('is_active', $model ? $model->is_active : true)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // color_value is stored as an integer (app colors may carry an alpha byte, e.g. 0xFF1E3A8A).
    var picker = document.getElementById('color_picker');
    var hidden = document.getElementById('color_value');
    var swatch = document.getElementById('color_swatch');
    var hexText = document.getElementById('color_hex');
    var current = parseInt(hidden.value, 10) || 0;
    var alpha = Math.floor(current / 0x1000000);
    function sync(hex) {
        var rgb = parseInt(hex.replace('#', ''), 16);
        hidden.value = alpha * 0x1000000 + rgb;
        swatch.style.background = hex;
        hexText.value = hex.toUpperCase();
    }
    picker.addEventListener('input', function (e) { sync(e.target.value); });
    picker.addEventListener('change', function (e) { sync(e.target.value); });
});
</script>
@endpush
