@extends('layouts.admin')

@section('title', 'سياسات الخصوصية')
@section('page-title', 'إدارة سياسات الخصوصية')
@section('page-subtitle', 'سياسة الخصوصية المعروضة في التطبيق وصفحته العامة')
@php
    $pageIcon = 'shield-check';
    $breadcrumbs = ['المحتوى' => null, 'سياسة الخصوصية' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.privacy-policies.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة سياسة خصوصية جديدة') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الإصدارات" icon="shield-check" :count="$policies->total()" :paginator="$policies">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('العنوان') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('تاريخ الإنشاء') }}</th>
            <th>{{ __('آخر تحديث') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($policies as $policy)
            <tr>
                <td class="num text-muted">{{ $policy->id }}</td>
                <td>
                    <a href="{{ route('admin.privacy-policies.show', $policy) }}" class="cell-title d-block text-decoration-none">{{ $policy->title_ar }}</a>
                    <div class="cell-sub ltr text-start">{{ $policy->title }}</div>
                </td>
                <td><x-admin.status-pill domain="active" :status="$policy->is_active" /></td>
                <td class="num text-muted">{{ $policy->created_at?->format('Y-m-d') }}</td>
                <td class="num text-muted">{{ $policy->updated_at?->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.privacy-policies.show', $policy) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.privacy-policies.edit', $policy) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.privacy-policies.destroy', $policy) }}" method="POST" data-confirm="{{ __('ستُحذف هذه السياسة نهائيًا.') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="shield-check" title="لا توجد سياسات خصوصية" text="أضف سياسة الخصوصية لتظهر للزوار في التطبيق.">
                <a href="{{ route('admin.privacy-policies.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة سياسة') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
