@extends('layouts.admin')

@section('title', 'تفاصيل التذكرة')
@section('page-title', __('تذكرة :number', ['number' => $ticket->ticket_number]))
@section('page-subtitle', $ticket->event?->title_ar ?: ($ticket->event?->title ?? ''))
@php
    $pageIcon = 'ticket-perforated';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'التذاكر' => route('admin.tickets.index'), 'تفاصيل التذكرة' => null];
    $typeLabels = ['standard' => __('عادية'), 'vip' => 'VIP', 'child' => __('طفل'), 'adult' => __('بالغ'), 'family' => __('عائلية'), 'student' => __('طالب')];
@endphp
@section('page-actions')
    <a href="{{ route('admin.tickets.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="بيانات التذكرة" icon="ticket-detailed">
                <x-slot:actions>
                    <x-admin.status-pill domain="ticket" :status="$ticket->status" />
                </x-slot:actions>

                <div class="kv-grid mb-4">
                    <div class="kv">
                        <div class="k">{{ __('السعر') }}</div>
                        <div class="v">
                            @if((float) $ticket->price == 0)
                                {{ __('مجانية') }}
                            @else
                                <x-admin.money :amount="$ticket->price" :currency="$ticket->currency ?: null" />
                            @endif
                        </div>
                    </div>
                    <div class="kv">
                        <div class="k">{{ __('تاريخ الحجز') }}</div>
                        <div class="v num">{{ $ticket->booking_date?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                    <div class="kv">
                        <div class="k">{{ __('مرات الدخول') }}</div>
                        <div class="v num"><span dir="ltr">{{ (int) $ticket->check_in_count }} / {{ (int) $ticket->max_check_ins }}</span></div>
                    </div>
                </div>

                <dl class="dl">
                    <dt>{{ __('رقم التذكرة') }}</dt>
                    <dd><span class="num fw-semibold" dir="ltr">{{ $ticket->ticket_number }}</span></dd>
                    <dt>{{ __('رمز QR') }}</dt>
                    <dd><code dir="ltr" class="text-break">{{ $ticket->qr_code ?: '—' }}</code></dd>
                    <dt>{{ __('الفعالية') }}</dt>
                    <dd>
                        @if($ticket->event)
                            <a href="{{ route('admin.events.show', $ticket->event) }}">{{ $ticket->event->title_ar ?: $ticket->event->title }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>{{ __('النوع') }}</dt>
                    <dd>{{ $typeLabels[$ticket->ticket_type] ?? ($ticket->ticket_type ?: '—') }}</dd>
                    <dt>{{ __('حامل التذكرة') }}</dt>
                    <dd>
                        {{ $ticket->holder_name ?: '—' }}
                        @if($ticket->user?->phone_number || $ticket->user?->email)
                            <div class="cell-sub num"><span dir="ltr">{{ $ticket->user?->phone_number ?? $ticket->user?->email }}</span></div>
                        @endif
                    </dd>
                    @if($ticket->seat_number)
                        <dt>{{ __('رقم المقعد') }}</dt>
                        <dd class="num">{{ $ticket->seat_number }}</dd>
                    @endif
                    <dt>{{ __('رقم المعاملة') }}</dt>
                    <dd>
                        @if($ticket->payment && Route::has('admin.payments.show'))
                            <a href="{{ route('admin.payments.show', $ticket->payment) }}" class="num" dir="ltr">{{ $ticket->transaction_id }}</a>
                        @else
                            <span class="num" dir="ltr">{{ $ticket->transaction_id ?? '—' }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('الحالة') }}</dt>
                    <dd><x-admin.status-pill domain="ticket" :status="$ticket->status" /></dd>
                    <dt>{{ __('آخر تحقق') }}</dt>
                    <dd>
                        <span class="num" dir="ltr">{{ $ticket->used_at?->format('Y-m-d H:i') ?? '—' }}</span>
                        @if($ticket->validated_by)<div class="cell-sub">{{ $ticket->validated_by }}</div>@endif
                    </dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="تغيير الحالة" icon="arrow-repeat">
                @if($ticket->status !== 'used')
                    <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}"
                        @if($ticket->status === 'active') data-confirm="{{ __('ستُلغى التذكرة :number ولن تصلح للدخول.', ['number' => $ticket->ticket_number]) }}" data-confirm-ok="{{ __('إلغاء التذكرة') }}" @endif>
                        @csrf
                        @method('PUT')
                        @if($ticket->status === 'active')
                            <input type="hidden" name="status" value="cancelled">
                            <p class="text-muted small">{{ __('التذكرة صالحة حاليًا، وإلغاؤها يمنع استعمالها عند البوابة.') }}</p>
                            <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-x-circle"></i> {{ __('إلغاء التذكرة') }}</button>
                        @else
                            <input type="hidden" name="status" value="active">
                            <p class="text-muted small">{{ __('بعد إعادة التفعيل تصلح التذكرة للدخول مرة أخرى.') }}</p>
                            <button type="submit" class="btn btn-primary w-100" data-busy><i class="bi bi-arrow-counterclockwise"></i> {{ __('إعادة تفعيل التذكرة') }}</button>
                        @endif
                    </form>
                    <x-slot:footer>
                        <div class="small text-muted d-flex gap-2"><i class="bi bi-info-circle"></i><span>{{ __('لاسترداد المبلغ استخدم صفحة المدفوعات.') }}</span></div>
                    </x-slot:footer>
                @else
                    <div class="d-flex gap-2 align-items-start">
                        <x-admin.status-pill domain="ticket" status="used" />
                        <p class="text-muted mb-0 small">{{ __('تذكرة مستخدمة.') }}</p>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
