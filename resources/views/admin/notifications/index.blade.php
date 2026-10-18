@extends('layouts.admin')

@section('title', 'الإشعارات')
@section('page-title', 'الإشعارات')
@section('page-subtitle', 'إشعارات داخل التطبيق لمستخدم محدد أو لجميع المستخدمين')
@php
    $pageIcon = 'bell';
    $breadcrumbs = ['المحتوى' => null, 'الإشعارات' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع الإشعارات" icon="bell" :count="$notifications->total()" :paginator="$notifications">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('العنوان') }}</th>
            <th>{{ __('المستلم') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('تاريخ الإنشاء') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($notifications as $notification)
            @php $recipient = $recipients[$notification->user_id] ?? null; @endphp
            <tr>
                <td class="num text-muted">{{ $notification->id }}</td>
                <td>
                    <div class="cell-title">{{ $notification->title_ar ?: $notification->title }}</div>
                    <div class="cell-sub">{{ \Illuminate\Support\Str::limit($notification->body_ar ?: $notification->body, 70) }}</div>
                </td>
                <td>
                    @if($recipient)
                        <div class="cell-title">{{ $recipient->name }}</div>
                        <div class="cell-sub ltr text-start">{{ $recipient->email ?: $recipient->phone_number }}</div>
                    @else
                        <x-admin.status-pill variant="neutral" icon="broadcast" label="عام" />
                    @endif
                </td>
                <td><code>{{ $notification->type ?: 'general' }}</code></td>
                <td>
                    @if($notification->is_read)
                        <x-admin.status-pill variant="success" label="مقروء" />
                    @else
                        <x-admin.status-pill variant="info" label="غير مقروء" />
                    @endif
                </td>
                <td class="num text-muted">{{ $notification->created_at?->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.notifications.show', $notification) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.notifications.edit', $notification) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.notifications.destroy', $notification) }}" method="POST" data-confirm="{{ __('سيتم حذف الإشعار «:name» نهائيًا.', ['name' => $notification->title_ar ?: $notification->title]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="bell" title="لا توجد إشعارات" text="أنشئ إشعارًا ليظهر لمستخدم محدد أو لجميع المستخدمين.">
                <a href="{{ route('admin.notifications.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة إشعار') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
