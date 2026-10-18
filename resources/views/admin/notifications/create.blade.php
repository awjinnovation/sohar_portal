@extends('layouts.admin')

@section('title', 'إضافة إشعار جديد')
@section('page-title', 'إضافة إشعار جديد')
@php
    $pageIcon = 'bell';
    $breadcrumbs = ['المحتوى' => null, 'الإشعارات' => route('admin.notifications.index'), 'إضافة إشعار' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.notifications.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.notifications.store') }}" method="POST">
        @csrf
        @include('admin.notifications._form', ['submitLabel' => 'حفظ'])
    </form>
@endsection
