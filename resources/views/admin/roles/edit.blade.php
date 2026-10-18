@extends('layouts.admin')

@section('title', 'تعديل الدور')
@section('page-title', 'تعديل الدور')
@section('page-subtitle', $role->name)
@php
    $pageIcon = 'shield-lock';
    $breadcrumbs = ['المنظمة' => null, 'الأدوار والصلاحيات' => route('admin.roles.index'), 'تعديل' => null];
    $permissionTotal = $permissions->flatten()->count();
    $selectedPermissions = old('permissions', $rolePermissions);
@endphp
@section('page-actions')
    <a href="{{ route('admin.roles.show', $role) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.roles.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.roles.update', $role) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="تعديل معلومات الدور" icon="shield-lock">
                    <x-admin.form-field name="name" id="name" label="اسم الدور" :value="old('name', $role->name)" dir="ltr" required
                        help="استخدم اسماً واضحاً باللغة الإنجليزية" />
                </x-admin.card>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="h6 fw-bold m-0"><i class="bi bi-ui-checks-grid text-primary"></i> {{ __('الصلاحيات') }}</h2>
                    <span class="text-muted small">{{ __('فعّل ما يحتاجه أصحاب هذا الدور فقط') }}</span>
                </div>
                @include('admin.roles._permissions', ['permissions' => $permissions, 'selected' => $selectedPermissions])
                @error('permissions')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-xl-4">
                <div class="preview-sticky">
                    <x-admin.card title="الملخص" icon="clipboard-check">
                        <div class="kv mb-3">
                            <div class="k">{{ __('الصلاحيات المحددة') }}</div>
                            <div class="v"><span class="num"><span data-perm-total>{{ count($selectedPermissions) }}</span></span> <span class="text-muted fw-normal fs-6">{{ __('من') }} <span class="num">{{ $permissionTotal }}</span></span></div>
                        </div>
                        <label class="toggle-row mb-3" for="selectAll">
                            <span class="tr-text">
                                <span class="tr-label d-block">{{ __('تحديد جميع الصلاحيات') }}</span>
                                <span class="tr-desc d-block">{{ __('تفعيل أو إلغاء كل الصلاحيات دفعة واحدة') }}</span>
                            </span>
                            <span class="switch">
                                <input type="checkbox" role="switch" id="selectAll">
                                <span class="track"></span>
                            </span>
                        </label>
                        <div class="alert alert-warning d-flex gap-2 align-items-start mb-0 small">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div><strong>{{ __('تنبيه:') }}</strong> {{ __('تعديل الصلاحيات سيؤثر على جميع المستخدمين الذين يمتلكون هذا الدور في كل الجهات.') }}</div>
                        </div>
                        <x-slot:footer>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                            </div>
                        </x-slot:footer>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </form>
@endsection
