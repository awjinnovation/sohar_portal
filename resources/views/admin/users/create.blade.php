@extends('layouts.admin')

@section('title', 'إضافة مستخدم جديد')
@section('page-title', 'إضافة مستخدم جديد')
@section('page-subtitle', 'يحصل المستخدم على الأدوار المحددة في هذه المنظمة فقط')
@php
    $pageIcon = 'person-plus';
    $breadcrumbs = ['المنظمة' => null, 'المستخدمون' => route('admin.users.index'), 'إضافة مستخدم' => null];
    $checkedRoles = old('roles', []);
@endphp
@section('page-actions')
    <a href="{{ route('admin.users.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.users.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="معلومات المستخدم الجديد" icon="person-vcard" subtitle="رقم الهاتف لدخول التطبيق، والبريد الإلكتروني وكلمة المرور لدخول لوحة التحكم، أو كلاهما">
                    <div class="row">
                        <x-admin.form-field col="col-md-6" name="name" id="name" label="الاسم الكامل" :value="old('name')" required autocomplete="name" />
                        <x-admin.form-field col="col-md-6" name="phone_number" id="phone_number" type="tel" label="رقم الهاتف" :value="old('phone_number')" dir="ltr" inputmode="tel" placeholder="9XXXXXXX" autocomplete="tel"
                            help="يدخل به الموظف إلى التطبيق برمز تحقق يصله في رسالة. إن كان له حساب في التطبيق أُضيفت الأدوار إليه." />
                    </div>
                    <div class="row">
                        <x-admin.form-field col="col-md-6" name="email" id="email" type="email" label="البريد الإلكتروني" :value="old('email')" dir="ltr" autocomplete="email"
                            help="لدخول لوحة التحكم، ويلزم معه كلمة مرور" />
                    </div>
                    <div class="row">
                        <x-admin.form-field col="col-md-6" name="password" id="password" type="password" label="كلمة المرور" dir="ltr" help="8 أحرف على الأقل" autocomplete="new-password" />
                        <x-admin.form-field col="col-md-6" name="password_confirmation" id="password_confirmation" type="password" label="تأكيد كلمة المرور" dir="ltr" autocomplete="new-password" />
                    </div>
                </x-admin.card>

                <x-admin.card title="الأدوار" icon="shield-lock" subtitle="اختر دورًا واحدًا على الأقل؛ تحدد الأدوار ما يمكن للمستخدم الوصول إليه">
                    @include('admin.users._roles')
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="الحفظ" icon="check2-circle">
                    @if(auth()->user()->isSuperAdmin())
                        <x-admin.toggle name="is_super_admin" id="is_super_admin" :hidden="true" label="مدير المنصة"
                            description="صلاحية كاملة على جميع المنظمات" :checked="old('is_super_admin', false)" />
                    @endif
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle"></i>
                        {{ __('يدخل المستخدم إلى التطبيق برقم هاتفه، وإلى لوحة التحكم ببريده الإلكتروني وكلمة المرور، مباشرة بعد الحفظ.') }}
                    </p>
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ المستخدم') }}</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
