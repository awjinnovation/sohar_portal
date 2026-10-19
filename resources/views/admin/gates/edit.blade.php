@extends('layouts.admin')

@section('title', 'تعديل البوابة')
@section('page-title', 'تعديل البوابة')
@section('page-subtitle', $gate->localizedName())
@php
    $pageIcon = 'door-open';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), $gate->localizedName() => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.gates.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    <form action="{{ route('admin.gates.update', $gate) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.gates._form')
    </form>
@endsection
