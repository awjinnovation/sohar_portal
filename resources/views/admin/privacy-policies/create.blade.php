@extends('layouts.admin')

@section('title', 'إضافة سياسة خصوصية')
@section('page-title', 'إضافة سياسة خصوصية جديدة')
@php
    $pageIcon = 'shield-check';
    $breadcrumbs = ['المحتوى' => null, 'سياسة الخصوصية' => route('admin.privacy-policies.index'), 'إضافة' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.privacy-policies.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.privacy-policies.store') }}" method="POST">
        @csrf
        @include('admin.partials.legal-form', ['item' => null, 'submitLabel' => 'حفظ', 'cancelUrl' => route('admin.privacy-policies.index')])
    </form>
@endsection
