@extends('layouts.admin')

@section('title', 'إضافة شروط وأحكام')
@section('page-title', 'إضافة شروط وأحكام جديدة')
@php
    $pageIcon = 'file-earmark-text';
    $breadcrumbs = ['المحتوى' => null, 'الشروط والأحكام' => route('admin.terms-and-conditions.index'), 'إضافة' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.terms-and-conditions.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.terms-and-conditions.store') }}" method="POST">
        @csrf
        @include('admin.partials.legal-form', ['item' => null, 'submitLabel' => 'حفظ', 'cancelUrl' => route('admin.terms-and-conditions.index')])
    </form>
@endsection
