@extends('layouts.admin')

@section('title', 'تعديل الإشعار')
@section('page-title', 'تعديل الإشعار')
@section('page-subtitle', $notification->title_ar ?: $notification->title)
@php
    $pageIcon = 'bell';
    $breadcrumbs = ['المحتوى' => null, 'الإشعارات' => route('admin.notifications.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.notifications.show', $notification) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.notifications.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.notifications.update', $notification) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.notifications._form', ['submitLabel' => 'حفظ التغييرات'])
    </form>
@endsection
