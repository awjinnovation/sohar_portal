@extends('layouts.admin')

@section('title', 'إضافة تاجر جديد')
@section('page-title', 'إضافة تاجر جديد')
@section('page-subtitle', 'بعد الحفظ يمكنك إضافة الموظفين الذين يستقبلون الدفع')
@php
    $pageIcon = 'shop-window';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'التجار' => route('admin.vendors.index'), 'إضافة تاجر' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.vendors.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.vendors.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.vendors._form')
    </form>
@endsection
