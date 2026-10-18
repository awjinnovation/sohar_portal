@extends('layouts.admin')

@section('title', 'الشروط والأحكام')
@section('page-title', 'إدارة الشروط والأحكام')
@section('page-subtitle', 'الشروط والأحكام المعروضة في التطبيق وصفحته العامة')
@php
    $pageIcon = 'file-earmark-text';
    $breadcrumbs = ['المحتوى' => null, 'الشروط والأحكام' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.terms-and-conditions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة شروط وأحكام جديدة') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الإصدارات" icon="file-earmark-text" :count="$terms->total()" :paginator="$terms">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('العنوان') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('تاريخ الإنشاء') }}</th>
            <th>{{ __('آخر تحديث') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($terms as $term)
            <tr>
                <td class="num text-muted">{{ $term->id }}</td>
                <td>
                    <a href="{{ route('admin.terms-and-conditions.show', $term) }}" class="cell-title d-block text-decoration-none">{{ $term->title_ar }}</a>
                    <div class="cell-sub ltr text-start">{{ $term->title }}</div>
                </td>
                <td><x-admin.status-pill domain="active" :status="$term->is_active" /></td>
                <td class="num text-muted">{{ $term->created_at?->format('Y-m-d') }}</td>
                <td class="num text-muted">{{ $term->updated_at?->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.terms-and-conditions.show', $term) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.terms-and-conditions.edit', $term) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.terms-and-conditions.destroy', $term) }}" method="POST" data-confirm="{{ __('سيتم حذف هذه الشروط نهائيًا.') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="file-earmark-text" title="لا توجد شروط وأحكام" text="أضف الشروط والأحكام لتظهر للمستخدمين في التطبيق.">
                <a href="{{ route('admin.terms-and-conditions.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة شروط') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
