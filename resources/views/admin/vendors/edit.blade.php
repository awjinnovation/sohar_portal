@extends('layouts.admin')

@section('title', 'تعديل التاجر')
@section('page-title', 'تعديل التاجر')
@section('page-subtitle', $vendor->localizedName('ar'))
@php
    $pageIcon = 'shop-window';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'التجار' => route('admin.vendors.index'), $vendor->localizedName('ar') => route('admin.vendors.show', $vendor), 'تعديل' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.vendors.show', $vendor) }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.vendors.update', $vendor) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.vendors._form')
    </form>
@endsection
