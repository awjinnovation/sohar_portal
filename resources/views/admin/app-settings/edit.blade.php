@extends('layouts.admin')

@section('title', 'تعديل الإعداد')
@section('page-title', 'تعديل الإعداد')
@section('page-subtitle', 'تحديث إعدادات تطبيق Flutter')
@php
    $pageIcon = 'sliders';
    $breadcrumbs = ['المنظمة' => null, 'إعدادات التطبيق' => route('admin.app-settings.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.app-settings.show', $appSetting) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.app-settings.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع للقائمة') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.app-settings.update', $appSetting) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.app-settings._form', ['appSetting' => $appSetting, 'submitLabel' => 'حفظ التغييرات'])
    </form>
@endsection
