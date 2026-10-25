@extends('layouts.admin')

@section('title', 'إضافة بوابة')
@section('page-title', 'إضافة بوابة')
@section('page-subtitle', 'بعد الحفظ أقرِن جهاز مسح بالبوابة من صفحة الأجهزة')
@php
    $pageIcon = 'door-open';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'إضافة بوابة' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.gates.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    <form action="{{ route('admin.gates.store') }}" method="POST">
        @csrf
        @include('admin.gates._form')
    </form>
@endsection
