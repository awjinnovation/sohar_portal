@extends('layouts.admin')

@section('title', 'تعديل الشروط والأحكام')
@section('page-title', 'تعديل الشروط والأحكام')
@section('page-subtitle', $termsAndCondition->title_ar)
@php
    $pageIcon = 'file-earmark-text';
    $breadcrumbs = ['المحتوى' => null, 'الشروط والأحكام' => route('admin.terms-and-conditions.index'), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.terms-and-conditions.show', $termsAndCondition) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.terms-and-conditions.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.terms-and-conditions.update', $termsAndCondition) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.partials.legal-form', ['item' => $termsAndCondition, 'submitLabel' => 'تحديث', 'cancelUrl' => route('admin.terms-and-conditions.index')])
    </form>
@endsection
