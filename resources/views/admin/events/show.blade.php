@extends('layouts.admin')

@section('title', 'عرض الفعالية')
@section('page-title', 'تفاصيل الفعالية')
@section('page-subtitle', $event->title_ar)
@php
    $pageIcon = 'calendar-event';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفعاليات' => route('admin.events.index'), 'التفاصيل' => null];
    $available = (int) ($event->available_tickets ?? 0);
    $total = (int) ($event->total_tickets ?? 0);
    $unlimited = $total === -1 || $available === -1;
    $soldOut = ! $unlimited && $total > 0 && $available <= 0;
    $fewLeft = ! $unlimited && ! $soldOut && $total > 0 && ($available / $total) <= 0.2;
    $isFree = (float) $event->price == 0;
    $gallery = array_values(array_filter((array) ($event->images ?? []), 'is_string'));
@endphp
@section('page-actions')
    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    @if(Route::has('admin.tickets.index'))
        <a href="{{ route('admin.tickets.index', ['event_id' => $event->id]) }}" class="btn"><i class="bi bi-ticket-perforated"></i> {{ __('التذاكر') }}</a>
    @endif
    <a href="{{ route('admin.events.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <div class="stat">
            <div class="stat-top">
                <span class="stat-icon tone-brand"><i class="bi bi-cash-coin"></i></span>
                <span class="stat-label">{{ __('السعر') }}</span>
            </div>
            <div class="stat-value">
                @if($isFree)
                    {{ __('مجانية') }}
                @else
                    <x-admin.money :amount="$event->price" :currency="$event->currency" />
                @endif
            </div>
            @if($isFree)<div class="stat-hint"><x-admin.status-pill variant="success" label="دخول مجاني" size="sm" /></div>@endif
        </div>
        <x-admin.stat-tile label="التذاكر المتاحة" :value="$unlimited ? __('غير محدودة') : $available" icon="ticket-perforated" :tone="$soldOut ? 'neutral' : ($fewLeft ? 'warning' : 'success')">
            @if($soldOut)
                <x-admin.status-pill variant="neutral" label="نفدت التذاكر" size="sm" />
            @elseif($fewLeft)
                <x-admin.status-pill variant="warning" label="بقي القليل" size="sm" />
            @endif
        </x-admin.stat-tile>
        <x-admin.stat-tile label="إجمالي التذاكر" :value="$unlimited ? '∞' : $total" icon="stack" tone="neutral" />
        <x-admin.stat-tile label="الموعد" :value="$event->start_time->format('Y-m-d')" icon="calendar3" :tone="$event->start_time->isToday() ? 'brand' : 'info'">
            <span class="num" dir="ltr">{{ $event->start_time->format('H:i') }} – {{ $event->end_time->format('H:i') }}</span>
        </x-admin.stat-tile>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="معلومات الفعالية" icon="info-circle">
                @if($event->image_url)
                    <div class="ratio ratio-21x9 rounded overflow-hidden border bg-light mb-4">
                        <img src="{{ $event->image_url }}" alt="{{ $event->title_ar }}" class="object-fit-cover">
                    </div>
                @endif
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="cell-sub mb-1">{{ __('العنوان بالعربية') }}</div>
                        <h3 class="h5 mb-2">{{ $event->title_ar }}</h3>
                        <div class="cell-sub mb-1">{{ __('الوصف بالعربية') }}</div>
                        <p class="mb-0" style="white-space: pre-line">{{ $event->description_ar }}</p>
                    </div>
                    <div class="col-md-6" dir="ltr">
                        <div class="cell-sub mb-1">Title (English)</div>
                        <h3 class="h5 mb-2">{{ $event->title }}</h3>
                        <div class="cell-sub mb-1">Description (English)</div>
                        <p class="mb-0" style="white-space: pre-line">{{ $event->description }}</p>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="التفاصيل" icon="list-ul">
                <dl class="dl">
                    <dt>{{ __('الفئة') }}</dt>
                    <dd>@if($event->category)<span class="pill pill-neutral">{{ $event->category->name_ar }}</span>@else {{ __('غير محدد') }} @endif</dd>
                    <dt>{{ __('البداية') }}</dt>
                    <dd><span class="num" dir="ltr">{{ $event->start_time->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('النهاية') }}</dt>
                    <dd><span class="num" dir="ltr">{{ $event->end_time->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('الموقع') }}</dt>
                    <dd>{{ $event->location_ar ?: '—' }}@if($event->location)<div class="cell-sub"><span dir="ltr">{{ $event->location }}</span></div>@endif</dd>
                    @if($event->mapLocation)
                        <dt>{{ __('موقع الخريطة') }}</dt>
                        <dd>{{ $event->mapLocation->name_ar }} <span class="cell-sub num"><span dir="ltr">({{ $event->mapLocation->latitude }}, {{ $event->mapLocation->longitude }})</span></span></dd>
                    @endif
                    <dt>{{ __('المنظِّم') }}</dt>
                    <dd>{{ $event->organizer_name_ar ?? __('غير محدد') }}@if($event->organizer_name)<div class="cell-sub"><span dir="ltr">{{ $event->organizer_name }}</span></div>@endif</dd>
                    <dt>{{ __('التذاكر المتاحة') }}</dt>
                    <dd>@if($unlimited) {{ __('غير محدودة') }} @else<span class="num" dir="ltr">{{ number_format($available) }} / {{ number_format($total) }}</span>@endif</dd>
                </dl>
            </x-admin.card>

            @if(count($gallery))
                <x-admin.card title="معرض الصور" icon="images" :subtitle="__('الصور: :count', ['count' => count($gallery)])">
                    <div class="row g-3">
                        @foreach($gallery as $img)
                            <div class="col-6 col-md-4">
                                <a href="{{ $img }}" target="_blank" rel="noopener" class="d-block ratio ratio-4x3 rounded overflow-hidden border bg-light">
                                    <img src="{{ $img }}" alt="" class="object-fit-cover" loading="lazy">
                                </a>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            @endif
        </div>

        <div class="col-xl-4">
            <x-admin.card title="الحالة" icon="toggles">
                <div class="d-flex flex-wrap gap-2">
                    <x-admin.status-pill domain="active" :status="$event->is_active" />
                    @if($event->start_time->isToday())
                        <x-admin.status-pill variant="brand" label="اليوم" />
                    @endif
                    @if($event->is_featured)
                        <x-admin.status-pill variant="accent" label="مميز" icon="star-fill" />
                    @endif
                    @if($isFree)
                        <x-admin.status-pill variant="success" label="مجانية" />
                    @endif
                    @if($soldOut)
                        <x-admin.status-pill variant="neutral" label="نفدت التذاكر" />
                    @elseif($fewLeft)
                        <x-admin.status-pill variant="warning" label="بقي القليل" />
                    @endif
                </div>
            </x-admin.card>

            <x-admin.card title="التذاكر المباعة" icon="ticket-perforated" subtitle="آخر 10 تذاكر" :flush="true">
                @if($event->tickets && $event->tickets->count() > 0)
                    <ul class="tx-list">
                        @foreach($event->tickets as $ticket)
                            <li class="tx-item">
                                <span class="tx-ico tone-neutral"><i class="bi bi-person"></i></span>
                                <div class="tx-main">
                                    @if(Route::has('admin.tickets.show'))
                                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="tx-title d-block text-reset">{{ $ticket->holder_name ?: $ticket->ticket_number }}</a>
                                    @else
                                        <div class="tx-title">{{ $ticket->holder_name ?: $ticket->ticket_number }}</div>
                                    @endif
                                    <div class="tx-sub num">{{ $ticket->purchase_date?->format('Y-m-d') ?? $ticket->created_at?->format('Y-m-d') }}</div>
                                </div>
                                <x-admin.status-pill domain="ticket" :status="$ticket->status" size="sm" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-admin.empty-state icon="ticket-perforated" title="لا توجد تذاكر مباعة حتى الآن" />
                @endif
            </x-admin.card>

            <x-admin.card title="الوسوم" icon="tags">
                <x-slot:actions>
                    @if(Route::has('admin.event-tags.create'))
                        <a href="{{ route('admin.event-tags.create', ['event_id' => $event->id]) }}" class="btn btn-sm btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('وسم') }}</a>
                    @endif
                </x-slot:actions>
                @if($event->tags && $event->tags->count() > 0)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($event->tags as $tag)
                            <span class="pill pill-neutral"><i class="bi bi-tag"></i>{{ $tag->tag_ar }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">{{ __('لا توجد وسوم') }}</p>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
