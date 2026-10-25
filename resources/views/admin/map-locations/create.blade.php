@extends('layouts.admin')

@section('title', 'إضافة موقع جديد')
@section('page-title', 'إضافة موقع جديد على الخريطة')
@section('page-subtitle', 'أضف موقعًا جديدًا ليظهر على الخريطة التفاعلية')
@php
    $pageIcon = 'geo-alt';
    $breadcrumbs = ['المحتوى' => null, 'مواقع الخريطة' => route('admin.map-locations.index'), 'إضافة موقع' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.map-locations.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.map-locations.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.map-locations._form', ['submitLabel' => 'حفظ الموقع'])
    </form>
@endsection
