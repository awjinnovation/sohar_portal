@extends('layouts.admin')

@section('title', 'إضافة دور جديد')
@section('page-title', 'إضافة دور جديد')
@section('page-subtitle', 'تعريفات الأدوار مشتركة بين جميع الجهات')
@php
    $pageIcon = 'shield-plus';
    $breadcrumbs = ['المنظمة' => null, 'الأدوار والصلاحيات' => route('admin.roles.index'), 'إضافة دور' => null];
    $permissionTotal = $permissions->flatten()->count();
@endphp
@section('page-actions')
    <a href="{{ route('admin.roles.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.roles.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="معلومات الدور الجديد" icon="shield-lock">
                    <x-admin.form-field name="name" id="name" label="اسم الدور" :value="old('name')" dir="ltr" required
                        help="استخدم اسماً واضحاً باللغة الإنجليزية (مثل: content-editor, event-coordinator)" />
                </x-admin.card>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="h6 fw-bold m-0"><i class="bi bi-ui-checks-grid text-primary"></i> {{ __('الصلاحيات') }}</h2>
                    <span class="text-muted small">{{ __('فعّل ما يحتاجه أصحاب هذا الدور فقط') }}</span>
                </div>
                @include('admin.roles._permissions', ['permissions' => $permissions, 'selected' => old('permissions', [])])
                @error('permissions')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-xl-4">
                <div class="preview-sticky">
                    <x-admin.card title="الملخص" icon="clipboard-check">
                        <div class="kv mb-3">
                            <div class="k">{{ __('الصلاحيات المحددة') }}</div>
                            <div class="v"><span class="num"><span data-perm-total>{{ count(old('permissions', [])) }}</span></span> <span class="text-muted fw-normal fs-6">{{ __('من') }} <span class="num">{{ $permissionTotal }}</span></span></div>
                        </div>
                        <label class="toggle-row" for="selectAll">
                            <span class="tr-text">
                                <span class="tr-label d-block">{{ __('تحديد جميع الصلاحيات') }}</span>
                                <span class="tr-desc d-block">{{ __('تفعيل أو إلغاء كل الصلاحيات دفعة واحدة') }}</span>
                            </span>
                            <span class="switch">
                                <input type="checkbox" role="switch" id="selectAll">
                                <span class="track"></span>
                            </span>
                        </label>
                        <x-slot:footer>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ الدور') }}</button>
                                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                            </div>
                        </x-slot:footer>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </form>
@endsection
