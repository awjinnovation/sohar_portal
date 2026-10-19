@extends('layouts.admin')

@section('title', 'تعديل المستخدم')
@section('page-title', 'تعديل المستخدم')
@section('page-subtitle', $user->name)
@php
    $pageIcon = 'person-gear';
    $breadcrumbs = ['المنظمة' => null, 'المستخدمون' => route('admin.users.index'), 'تعديل' => null];
    $checkedRoles = old('roles', $userRoles);
    $isSelf = $user->is(auth()->user());
@endphp
@section('page-actions')
    @can('manage users')
        <a href="{{ route('admin.users.show', $user) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    @endcan
    <a href="{{ route('admin.users.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                @if($canEditIdentity)
                    <x-admin.card title="تعديل معلومات المستخدم" icon="person-vcard" subtitle="الاسم والبريد وكلمة المرور مشتركة بين جميع المنظمات">
                        <div class="row">
                            <x-admin.form-field col="col-md-6" name="name" id="name" label="الاسم الكامل" :value="old('name', $user->name)" required autocomplete="name" />
                            <x-admin.form-field col="col-md-6" name="email" id="email" type="email" label="البريد الإلكتروني" :value="old('email', $user->email)" dir="ltr" autocomplete="email" />
                        </div>
                        <div class="row">
                            <x-admin.form-field col="col-md-6" name="password" id="password" type="password" label="كلمة المرور الجديدة" dir="ltr"
                                help="اتركه فارغًا لتبقى كلمة المرور كما هي" autocomplete="new-password" />
                            <x-admin.form-field col="col-md-6" name="password_confirmation" id="password_confirmation" type="password" label="تأكيد كلمة المرور الجديدة" dir="ltr" autocomplete="new-password" />
                        </div>
                    </x-admin.card>
                @else
                    <div class="alert alert-warning d-flex gap-3 align-items-start">
                        <i class="bi bi-shield-lock fs-5"></i>
                        <div>{{ __('هذا المستخدم مرتبط بمنظمات أخرى، ويمكنك تعديل أدواره في هذه المنظمة فقط.') }}</div>
                    </div>
                    <x-admin.card>
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                            <div style="min-width: 0">
                                <div class="cell-title">{{ $user->name }}</div>
                                <div class="cell-sub"><span class="ltr">{{ $user->email ?? $user->phone_number ?? '-' }}</span></div>
                            </div>
                        </div>
                    </x-admin.card>
                @endif

                <x-admin.card title="الأدوار" icon="shield-lock" subtitle="تسري على هذه المنظمة فقط، ولا تتغير أدوار المستخدم في المنظمات الأخرى">
                    <div class="row g-2">
                        @foreach($roles as $role)
                            <div class="col-md-6 col-xxl-4">
                                <label class="toggle-row" for="role_{{ $role->id }}">
                                    <span class="tr-text">
                                        <span class="tr-label d-block"><span class="ltr">{{ $role->name }}</span></span>
                                        <span class="tr-desc d-block">{{ __('الصلاحيات: :count', ['count' => $role->permissions->count()]) }}</span>
                                    </span>
                                    <span class="switch">
                                        <input type="checkbox" role="switch" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}"
                                               @checked(in_array($role->name, $checkedRoles))>
                                        <span class="track"></span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('roles')
                        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="معلومات المستخدم" icon="info-circle">
                    <dl class="dl">
                        <dt>{{ __('تاريخ التسجيل') }}</dt><dd><span class="num ltr">{{ $user->created_at->format('Y-m-d H:i') }}</span></dd>
                        <dt>{{ __('آخر تحديث') }}</dt><dd><span class="num ltr">{{ $user->updated_at->format('Y-m-d H:i') }}</span></dd>
                        <dt>{{ __('الأدوار الحالية') }}</dt>
                        <dd>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse($userRoles as $roleName)
                                    <span class="pill pill-brand pill-sm">{{ $roleName }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </div>
                        </dd>
                    </dl>
                    @if(auth()->user()->isSuperAdmin())
                        <div class="mt-3">
                            <x-admin.toggle name="is_super_admin" id="is_super_admin" :hidden="true" label="مدير المنصة"
                                :description="$isSelf ? 'لا يمكنك تغيير هذه الصلاحية لحسابك' : 'صلاحية كاملة على جميع المنظمات'"
                                :checked="old('is_super_admin', $user->is_super_admin)" :disabled="$isSelf" />
                        </div>
                    @endif
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
