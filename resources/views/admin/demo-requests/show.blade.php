@extends('layouts.admin')

@section('title', __('طلب عرض #:id', ['id' => $demo->id]))
@section('page-title', $demo->organization)
@section('page-subtitle', __('طلب عرض #:id', ['id' => $demo->id]).' · '.$demo->name)
@php
    $pageIcon = 'envelope-paper';
    $breadcrumbs = ['المنصة' => null, 'طلبات العرض التوضيحي' => route('admin.demo-requests.index'), __('طلب #:id', ['id' => $demo->id]) => null];
    $lc = app()->getLocale();
    $statusLabels = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'closed' => 'مغلق'];
@endphp
@section('page-actions')
    <a href="{{ route('admin.demo-requests.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.card title="تفاصيل الطلب" icon="file-earmark-text">
                <x-slot:actions><x-admin.status-pill domain="demo" :status="$demo->status" /></x-slot:actions>

                <dl class="dl">
                    <dt>{{ __('الاسم') }}</dt><dd dir="auto" class="text-start">{{ $demo->name }}</dd>
                    <dt>{{ __('الجهة') }}</dt><dd dir="auto" class="text-start">{{ $demo->organization }}</dd>
                    <dt>{{ __('نوع الفعالية') }}</dt><dd>{{ $demo->eventTypeLabel($lc) }}</dd>
                    <dt>{{ __('الزوار المتوقعون') }}</dt><dd><bdi>{{ $demo->visitorsLabel($lc) }}</bdi></dd>
                    <dt>{{ __('تواريخ الموسم') }}</dt><dd><bdi>{{ $demo->season ?: '—' }}</bdi></dd>
                    <dt>{{ __('التواصل') }}</dt>
                    <dd>
                        @if($demo->contact_email)
                            <a href="mailto:{{ $demo->contact_email }}" class="ltr d-inline-block">{{ $demo->contact }}</a>
                        @else
                            <a href="tel:{{ $demo->contactPhoneDigits() }}" class="ltr d-inline-block">{{ $demo->contact }}</a>
                        @endif
                    </dd>
                    <dt>{{ __('الملاحظات') }}</dt><dd dir="auto" class="text-start" style="white-space: pre-line">{{ $demo->notes ?: '—' }}</dd>
                    <dt>{{ __('لغة الطلب') }}</dt><dd>{{ $demo->locale === 'ar' ? __('العربية') : 'English' }}</dd>
                    <dt>{{ __('تاريخ الطلب') }}</dt><dd class="num"><span class="ltr">{{ $demo->submittedAtLocal()?->format('Y-m-d H:i') }}</span> <span class="text-muted small">(Asia/Muscat)</span></dd>
                    <dt>{{ __('إشعار البريد') }}</dt>
                    <dd>
                        @if($demo->mailed_at)
                            <span class="ltr">{{ $demo->mailed_at->copy()->setTimezone(config('landing.timezone'))->format('Y-m-d H:i') }}</span>
                            <span class="text-muted small">→ {{ config('landing.demo_request_to') }}</span>
                        @else
                            <span class="text-muted">{{ __('لم يُرسل') }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('المتصفح') }}</dt><dd class="small text-muted ltr text-start">{{ $demo->user_agent ?: '—' }}</dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الإجراءات" icon="lightning-charge">
                @if($demo->contact_email)
                    <a href="mailto:{{ $demo->contact_email }}?subject={{ rawurlencode('Your Mawsim demo request') }}" class="btn btn-primary w-100 mb-3"><i class="bi bi-reply"></i> {{ __('الرد بالبريد') }}</a>
                @else
                    <a href="tel:{{ $demo->contactPhoneDigits() }}" class="btn btn-primary w-100 mb-2"><i class="bi bi-telephone"></i> {{ __('اتصال') }}</a>
                    <a href="https://wa.me/{{ ltrim((string) $demo->contactPhoneDigits(), '+') }}" class="btn btn-tonal w-100 mb-3" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                @endif

                <form action="{{ route('admin.demo-requests.status', $demo) }}" method="POST">
                    @csrf
                    <label class="form-label fw-semibold small" for="demo_status">{{ __('الحالة') }}</label>
                    <div class="d-flex gap-2">
                        <select name="status" id="demo_status" class="form-select">
                            @foreach($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($demo->status === $key)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-tonal" data-busy>{{ __('حفظ') }}</button>
                    </div>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
