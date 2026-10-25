@extends('layouts.admin')

@section('title', 'تعديل سياسة الخصوصية')
@section('page-title', 'تعديل سياسة الخصوصية')
@section('page-subtitle', $privacyPolicy->title_ar)
@php
    $pageIcon = 'shield-check';
    $breadcrumbs = ['المحتوى' => null, 'سياسة الخصوصية' => route('admin.privacy-policies.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.privacy-policies.show', $privacyPolicy) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.privacy-policies.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.privacy-policies.update', $privacyPolicy) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.partials.legal-form', ['item' => $privacyPolicy, 'submitLabel' => 'تحديث', 'cancelUrl' => route('admin.privacy-policies.index')])
    </form>
@endsection
