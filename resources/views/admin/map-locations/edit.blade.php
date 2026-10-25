@extends('layouts.admin')

@section('title', 'تعديل موقع')
@section('page-title', 'تعديل موقع على الخريطة')
@section('page-subtitle', __('تحديث معلومات الموقع: :name', ['name' => $mapLocation->name_ar ?: $mapLocation->name]))
@php
    $pageIcon = 'geo-alt';
    $breadcrumbs = ['المحتوى' => null, 'مواقع الخريطة' => route('admin.map-locations.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.map-locations.show', $mapLocation) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.map-locations.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.map-locations.update', $mapLocation) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.map-locations._form', ['submitLabel' => 'حفظ التغييرات'])
    </form>
@endsection
