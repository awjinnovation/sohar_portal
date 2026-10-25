@extends('layouts.admin')

@section('title', 'إضافة إعداد')
@section('page-title', 'إضافة إعداد جديد للتطبيق')
@section('page-subtitle', 'إعداد جديد يقرأه تطبيق الهاتف')
@php
    $pageIcon = 'sliders';
    $breadcrumbs = ['المنظمة' => null, 'إعدادات التطبيق' => route('admin.app-settings.index'), 'إضافة إعداد' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.app-settings.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.app-settings.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.app-settings._form', ['appSetting' => new \App\Models\AppSetting(['setting_type' => 'string', 'is_public' => false]), 'submitLabel' => 'حفظ'])
    </form>
@endsection
