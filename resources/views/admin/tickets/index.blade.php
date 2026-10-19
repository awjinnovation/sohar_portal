@extends('layouts.admin')

@section('title', 'التذاكر')
@section('page-title', 'التذاكر المصدرة')
@section('page-subtitle', 'تذاكر الفعاليات الصادرة من عمليات الشراء في التطبيق')
@php
    $pageIcon = 'ticket-perforated';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'التذاكر' => null];
    $typeLabels = ['standard' => __('عادية'), 'vip' => 'VIP', 'child' => __('طفل'), 'adult' => __('بالغ'), 'family' => __('عائلية'), 'student' => __('طالب')];
@endphp

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع التذاكر" icon="ticket-perforated" :count="$tickets->total()" search="q"
        search-placeholder="رقم التذكرة أو الاسم أو رقم المعاملة" :paginator="$tickets">
        <x-slot:filters>
            <select name="event_id" class="form-select" aria-label="{{ __('الفعالية') }}">
                <option value="">{{ __('كل الفعاليات') }}</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->title_ar ?: $event->title }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach(['active' => __('صالحة'), 'used' => __('مستخدمة'), 'cancelled' => __('ملغاة'), 'expired' => __('منتهية')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th>{{ __('رقم التذكرة') }}</th>
            <th>{{ __('الفعالية') }}</th>
            <th>{{ __('حامل التذكرة') }}</th>
            <th>{{ __('تاريخ الحجز') }}</th>
            <th>{{ __('السعر') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($tickets as $ticket)
            <tr>
                <td class="nowrap"><a href="{{ route('admin.tickets.show', $ticket) }}" class="num fw-semibold" dir="ltr">{{ $ticket->ticket_number }}</a></td>
                <td>
                    <div class="cell-title">{{ $ticket->event?->title_ar ?: ($ticket->event?->title ?? '—') }}</div>
                    @if($ticket->ticket_type)<div class="cell-sub">{{ $typeLabels[$ticket->ticket_type] ?? $ticket->ticket_type }}</div>@endif
                </td>
                <td>
                    <div class="cell-title">{{ $ticket->holder_name ?: '—' }}</div>
                    @if($ticket->user?->phone_number)<div class="cell-sub num"><span dir="ltr">{{ $ticket->user->phone_number }}</span></div>@endif
                </td>
                <td class="num">{{ $ticket->booking_date?->format('Y-m-d') ?? '—' }}</td>
                <td class="nowrap">
                    @if((float) $ticket->price == 0)
                        <x-admin.status-pill variant="success" label="مجانية" />
                    @else
                        <x-admin.money :amount="$ticket->price" :currency="$ticket->currency ?: null" />
                    @endif
                </td>
                <td><x-admin.status-pill domain="ticket" :status="$ticket->status" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="icon-btn" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="ticket-perforated" title="لا توجد تذاكر"
                text="تظهر هنا التذاكر بعد شرائها من التطبيق. جرّب تغيير معايير البحث." />
        @endforelse
    </x-admin.data-table>
@endsection
