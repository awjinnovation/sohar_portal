@extends('layouts.admin')

@section('title', 'محفظة المستخدم')
@section('page-title', 'محفظة المستخدم')
@section('page-subtitle', $user->name ?? '')
@php
    $pageIcon = 'wallet2';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المحافظ' => route('admin.wallets.index'), ($user->name ?: '#'.$user->id) => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.wallets.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.card title="لا توجد محفظة بعد" icon="wallet2">
                <x-admin.empty-state icon="wallet2" title="لا توجد محفظة لهذا المستخدم بعد"
                    text="تُنشأ المحفظة تلقائيًا عند أول شحن أو عملية دفع من التطبيق. يمكنك إنشاؤها الآن يدويًا لإضافة رصيد أو تعديل.">
                    @can('manage wallets')
                        <form method="POST" action="{{ route('admin.wallets.create', $user) }}" class="mt-3"
                              data-confirm="{{ __('إنشاء محفظة فارغة لهذا المستخدم في هذه المنظمة؟') }}" data-confirm-tone="primary" data-confirm-ok="{{ __('إنشاء المحفظة') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('إنشاء المحفظة') }}</button>
                        </form>
                    @endcan
                </x-admin.empty-state>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="المستخدم" icon="person">
                <x-admin.detail-list :items="[
                    'الاسم' => $user->name,
                    'الهاتف' => $user->phone_number,
                    'البريد الإلكتروني' => $user->email,
                    'تاريخ التسجيل' => $user->created_at?->format('Y-m-d'),
                ]" />
            </x-admin.card>
        </div>
    </div>
@endsection
