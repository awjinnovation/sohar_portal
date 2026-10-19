@extends('layouts.admin')

@section('title', 'الفعاليات')
@section('page-title', 'إدارة الفعاليات')
@section('page-subtitle', 'جدول الفعاليات والتذاكر المتاحة وأسعارها')
@php
    $pageIcon = 'calendar-event';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفعاليات' => null];
    $currentView = ($view ?? 'list') === 'calendar' ? 'calendar' : 'list';
@endphp
@section('page-actions')
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة فعالية') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    @if($currentView === 'calendar')
        <x-admin.card title="تقويم الفعاليات" icon="calendar3" subtitle="الفعاليات النشطة فقط · اضغط على فعالية لعرض تفاصيلها">
            <x-slot:actions>
                <div class="segmented" role="tablist" aria-label="{{ __('طريقة العرض') }}">
                    <a href="{{ route('admin.events.index', ['view' => 'list']) }}"><i class="bi bi-list-ul"></i> {{ __('قائمة') }}</a>
                    <a href="{{ route('admin.events.index', ['view' => 'calendar']) }}" class="active" aria-current="page"><i class="bi bi-calendar3"></i> {{ __('تقويم') }}</a>
                </div>
            </x-slot:actions>
            <div id="calendar"></div>
        </x-admin.card>
    @else
        <x-admin.data-table title="جميع الفعاليات" icon="calendar-event" :count="$events->total()" :paginator="$events">
            <x-slot:actions>
                <div class="segmented" role="tablist" aria-label="{{ __('طريقة العرض') }}">
                    <a href="{{ route('admin.events.index', ['view' => 'list']) }}" class="active" aria-current="page"><i class="bi bi-list-ul"></i> {{ __('قائمة') }}</a>
                    <a href="{{ route('admin.events.index', ['view' => 'calendar']) }}"><i class="bi bi-calendar3"></i> {{ __('تقويم') }}</a>
                </div>
            </x-slot:actions>
            <x-slot:head>
                <th>{{ __('الفعالية') }}</th>
                <th>{{ __('الفئة') }}</th>
                <th>{{ __('الموعد') }}</th>
                <th>{{ __('الموقع') }}</th>
                <th>{{ __('التذاكر المتاحة') }}</th>
                <th>{{ __('السعر') }}</th>
                <th>{{ __('الحالة') }}</th>
                <th class="text-end">{{ __('الإجراءات') }}</th>
            </x-slot:head>

            @forelse($events as $event)
                @php
                    $available = (int) ($event->available_tickets ?? 0);
                    $total = (int) ($event->total_tickets ?? 0);
                    $unlimited = $total === -1 || $available === -1;
                    $soldOut = ! $unlimited && $total > 0 && $available <= 0;
                    $fewLeft = ! $unlimited && ! $soldOut && $total > 0 && ($available / $total) <= 0.2;
                @endphp
                <tr>
                    <td>
                        <div class="cell-media">
                            @if($event->image_url)
                                <img src="{{ $event->image_url }}" alt="" loading="lazy">
                            @else
                                <span class="ph"><i class="bi bi-calendar-event"></i></span>
                            @endif
                            <div style="min-width: 0">
                                <a href="{{ route('admin.events.show', $event) }}" class="cell-title d-block">{{ $event->title_ar }}</a>
                                <div class="cell-sub text-truncate" style="max-width: 260px"><span dir="ltr">{{ $event->title }}</span></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($event->category)
                            <span class="pill pill-neutral">{{ $event->category->name_ar }}</span>
                        @else
                            <span class="text-muted">{{ __('غير محدد') }}</span>
                        @endif
                    </td>
                    <td class="nowrap">
                        <div class="num">{{ $event->start_time->format('Y-m-d') }}</div>
                        <div class="cell-sub num"><span dir="ltr">{{ $event->start_time->format('H:i') }} – {{ $event->end_time->format('H:i') }}</span></div>
                    </td>
                    <td style="min-width: 150px">
                        <span class="d-inline-flex align-items-start gap-1"><i class="bi bi-geo-alt text-muted"></i>{{ $event->location_ar ?: $event->location }}</span>
                    </td>
                    <td class="nowrap">
                        @if($unlimited)
                            <x-admin.status-pill variant="info" label="غير محدودة" icon="infinity" />
                        @else
                            <div class="num">{{ number_format($available) }} <span class="text-muted">/ {{ number_format($total) }}</span></div>
                            @if($soldOut)
                                <x-admin.status-pill variant="neutral" label="نفدت التذاكر" size="sm" />
                            @elseif($fewLeft)
                                <x-admin.status-pill variant="warning" label="بقي القليل" size="sm" />
                            @endif
                        @endif
                    </td>
                    <td class="nowrap">
                        @if((float) $event->price == 0)
                            <x-admin.status-pill variant="success" label="مجانية" />
                        @else
                            <x-admin.money :amount="$event->price" :currency="$event->currency" />
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <x-admin.status-pill domain="active" :status="$event->is_active" />
                            @if($event->start_time->isToday())
                                <x-admin.status-pill variant="brand" label="اليوم" />
                            @endif
                            @if($event->is_featured)
                                <x-admin.status-pill variant="accent" label="مميز" icon="star-fill" />
                            @endif
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="row-actions">
                            <a href="{{ route('admin.events.show', $event) }}" class="icon-btn" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.events.edit', $event) }}" class="icon-btn brand" title="{{ __('تعديل') }}" aria-label="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('admin.events.destroy', $event) }}" method="POST" data-confirm="{{ __('ستُحذف الفعالية «:name» نهائيًا.', ['name' => $event->title_ar]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}" aria-label="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row :colspan="8" icon="calendar-x" title="لا توجد فعاليات حتى الآن" text="أضف أول فعالية لتظهر للزوار في التطبيق.">
                    <a href="{{ route('admin.events.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة أول فعالية') }}</a>
                </x-admin.empty-row>
            @endforelse
        </x-admin.data-table>
    @endif

    @if($currentView === 'calendar')
        <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5 fw-bold d-flex align-items-center gap-2">
                            <span class="stat-icon tone-brand" style="width:36px;height:36px;font-size:17px"><i class="bi bi-calendar-event"></i></span>
                            <span id="eventModalTitle"></span>
                        </h2>
                        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="{{ __('إغلاق') }}"></button>
                    </div>
                    <div class="modal-body" id="eventModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('إغلاق') }}</button>
                        <a href="#" id="eventShowLink" class="btn btn-tonal"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
                        <a href="#" id="eventEditLink" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($currentView === 'calendar')
@push('styles')
<style>
    #calendar .fc-toolbar { flex-wrap: wrap; gap: 10px; }
    #calendar .fc-event { cursor: pointer; border-radius: var(--r-sm); padding: 1px 4px; font-weight: 600; }
    #calendar .fc-daygrid-event { white-space: normal; }
    #calendar .fc-event-time { white-space: nowrap; }
    /* FullCalendar hides the list header at left:-10000px, which widens RTL pages; clip it instead */
    #calendar .fc-list-table thead { left: auto; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%); }
    #calendar .fc-list-day-cushion { background: var(--surface-low); }
    html[dir="rtl"] #calendar .fc-list-event-time { direction: ltr; text-align: right; font-family: var(--font-num), var(--font-ui); }
    #calendar .fc-list-event:hover td { background: rgba(var(--brand-rgb), .045); }
    #calendar .fc-col-header-cell { background: var(--surface-low); }
    #calendar .fc-col-header-cell-cushion { color: var(--muted); font-weight: 700; font-size: 12.5px; padding-block: 8px; }
    #calendar .fc-daygrid-day-number { color: var(--text); font-family: var(--font-num), var(--font-ui); font-size: 13px; }
    #calendar .fc-scrollgrid { border-radius: var(--r-md); overflow: hidden; }
    @media (max-width: 575.98px) {
        #calendar .fc-toolbar { flex-direction: column; align-items: stretch; }
        #calendar .fc-toolbar-chunk { display: flex; justify-content: center; }
        #calendar .fc-toolbar-title { font-size: 16px; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
@if(app()->getLocale() === 'ar')
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales/ar.global.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var showUrl = @json(route('admin.events.show', '__ID__'));
    var editUrl = @json(route('admin.events.edit', '__ID__'));
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var narrow = window.matchMedia('(max-width: 767.98px)').matches;
    var isAr = @json(app()->getLocale() === 'ar');
    var fmtDate = new Intl.DateTimeFormat(isAr ? 'ar-u-nu-latn' : 'en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    var fmtTime = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: narrow ? 'listWeek' : 'dayGridMonth',
        locale: isAr ? 'ar' : 'en',
        direction: isAr ? 'rtl' : 'ltr',
        height: 'auto',
        headerToolbar: narrow
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        footerToolbar: narrow ? { center: 'dayGridMonth,listWeek' } : false,
        buttonText: { today: @json(__('اليوم')), month: @json(__('شهر')), week: @json(__('أسبوع')), day: @json(__('يوم')), list: @json(__('قائمة')) },
        slotMinTime: '08:00:00',
        slotMaxTime: '23:00:00',
        dayMaxEvents: 3,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' },
        events: @json($calendarEvents ?? []),
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            var ev = info.event;
            var p = ev.extendedProps;
            var available = Number(p.available_tickets || 0);
            var total = Number(p.total_tickets || 0);
            var tickets;
            if (total === -1 || available === -1) {
                tickets = '<span class="pill pill-info"><i class="bi bi-infinity"></i>' + @json(__('غير محدودة')) + '</span>';
            } else {
                var pct = total > 0 ? Math.round((available / total) * 100) : 0;
                var pill = '';
                if (total > 0 && available <= 0) pill = ' <span class="pill pill-neutral pill-sm"><span class="dot"></span>' + @json(__('نفدت التذاكر')) + '</span>';
                else if (total > 0 && pct <= 20) pill = ' <span class="pill pill-warning pill-sm"><i class="bi bi-exclamation-triangle-fill"></i>' + @json(__('بقي القليل')) + '</span>';
                tickets = '<span class="num">' + available.toLocaleString('en') + ' / ' + total.toLocaleString('en') + '</span> <span class="text-muted num">(' + pct + '%)</span>' + pill;
            }
            var priceParts = String(p.price || '').split(' ');
            var price = Number(priceParts[0]) === 0
                ? '<span class="pill pill-success"><span class="dot"></span>' + @json(__('مجانية')) + '</span>'
                : '<span class="money" dir="ltr"><span class="cur">' + esc(priceParts[1] || '') + '</span>' + esc(priceParts[0]) + '</span>';
            var when = ev.start ? fmtDate.format(ev.start) : '';
            var time = ev.start ? fmtTime.format(ev.start) + (ev.end ? ' – ' + fmtTime.format(ev.end) : '') : '';

            document.getElementById('eventModalTitle').textContent = ev.title;
            document.getElementById('eventModalBody').innerHTML =
                '<dl class="dl">' +
                '<dt>' + @json(__('الفئة')) + '</dt><dd>' + (p.category ? '<span class="pill pill-neutral">' + esc(p.category) + '</span>' : '—') + '</dd>' +
                '<dt>' + @json(__('التاريخ')) + '</dt><dd>' + esc(when) + '<div class="cell-sub num"><span dir="ltr">' + esc(time) + '</span></div></dd>' +
                '<dt>' + @json(__('الموقع')) + '</dt><dd>' + (esc(p.location) || '—') + '</dd>' +
                '<dt>' + @json(__('التذاكر المتاحة')) + '</dt><dd>' + tickets + '</dd>' +
                '<dt>' + @json(__('السعر')) + '</dt><dd>' + price + '</dd>' +
                '</dl>';
            document.getElementById('eventShowLink').href = showUrl.replace('__ID__', ev.id);
            document.getElementById('eventEditLink').href = editUrl.replace('__ID__', ev.id);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal')).show();
        },
        eventDidMount: function (info) {
            info.el.title = info.event.title + (info.event.extendedProps.location ? '\n' + info.event.extendedProps.location : '');
        }
    });

    calendar.render();
});
</script>
@endpush
@endif
