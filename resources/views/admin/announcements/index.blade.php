@extends('layouts.admin')

@section('title', 'الإعلانات')
@section('page-title', 'إدارة الإعلانات')
@section('page-subtitle', 'رسائل تظهر لزوار التطبيق في الصفحة الرئيسية')
@php
    $pageIcon = 'megaphone';
    $breadcrumbs = ['المحتوى' => null, 'الإعلانات' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة إعلان جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الإعلانات" icon="megaphone" :count="$announcements->total()" :paginator="$announcements">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('العنوان') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الأولوية') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($announcements as $announcement)
            <tr>
                <td class="num text-muted">{{ $announcement->id }}</td>
                <td>
                    <div class="cell-title">
                        {{ $announcement->title_ar }}
                        @if($announcement->is_pinned)
                            <i class="bi bi-pin-angle-fill text-danger" title="{{ __('مثبت') }}"></i>
                        @endif
                    </div>
                    <div class="cell-sub">{{ \Illuminate\Support\Str::limit($announcement->title, 60) }}</div>
                </td>
                <td><x-admin.status-pill domain="announcement" :status="$announcement->type" /></td>
                <td class="num">{{ $announcement->priority ?? 0 }}</td>
                <td class="num text-muted">{{ $announcement->start_datetime->format('Y-m-d H:i') }}</td>
                <td><x-admin.status-pill domain="active" :status="$announcement->is_active" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.announcements.show', $announcement) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" data-confirm="{{ __('سيُحذف الإعلان «:name» نهائيًا.', ['name' => $announcement->title_ar]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="megaphone" title="لا توجد إعلانات حتى الآن" text="أضف إعلانًا ليظهر للزوار في التطبيق.">
                <a href="{{ route('admin.announcements.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة إعلان') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
