@extends('layouts.admin')

@section('title', 'الفعاليات')
@section('page-title', 'إدارة الفعاليات')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css' rel='stylesheet' />
<style>
    .view-toggle {
        background: #F8F9FA;
        border-radius: 10px;
        padding: 4px;
        display: inline-flex;
        gap: 4px;
    }
    .view-toggle .btn {
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .view-toggle .btn:not(.active) {
        background: transparent;
        color: #7F8C8D;
    }
    .view-toggle .btn.active {
        background: white;
        color: #4A90E2;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    #calendar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .fc {
        direction: rtl;
    }
    .fc-toolbar {
        flex-wrap: wrap;
        gap: 10px;
    }
    .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #2C3E50;
    }
    .fc-button {
        background: #4A90E2 !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
    }
    .fc-button:hover {
        background: #3A7BC8 !important;
    }
    .fc-button-active {
        background: #3A7BC8 !important;
    }
    .fc-event {
        border-radius: 6px;
        padding: 4px 8px;
        margin-bottom: 2px;
        cursor: pointer;
        font-weight: 600;
    }
    .fc-daygrid-event {
        white-space: normal;
    }
    .event-details-modal .modal-header {
        background: linear-gradient(135deg, #4A90E2 0%, #6BA5E8 100%);
        color: white;
    }
    .availability-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    .availability-high {
        background: #D4EDDA;
        color: #155724;
    }
    .availability-medium {
        background: #FFF3CD;
        color: #856404;
    }
    .availability-low {
        background: #F8D7DA;
        color: #721C24;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-0 fw-bold">جميع الفعاليات</h4>
                    <p class="text-muted mb-0">عرض وإدارة فعاليات المهرجان</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="view-toggle">
                        <a href="{{ route('admin.events.index', ['view' => 'list']) }}"
                           class="btn {{ ($view ?? 'list') === 'list' ? 'active' : '' }}">
                            <i class="bi bi-list-ul"></i> قائمة
                        </a>
                        <a href="{{ route('admin.events.index', ['view' => 'calendar']) }}"
                           class="btn {{ ($view ?? 'list') === 'calendar' ? 'active' : '' }}">
                            <i class="bi bi-calendar3"></i> تقويم
                        </a>
                    </div>
                    <a href="{{ route('admin.events.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> إضافة فعالية
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(($view ?? 'list') === 'calendar')
        <!-- Calendar View -->
        <div class="row">
            <div class="col-12">
                <div id="calendar"></div>
            </div>
        </div>
    @else
        <!-- List View -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>العنوان</th>
                                <th>الفئة</th>
                                <th>التاريخ والوقت</th>
                                <th>الموقع</th>
                                <th>التذاكر المتاحة</th>
                                <th>السعر</th>
                                <th>الحالة</th>
                                <th width="150">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($events as $event)
                            <tr>
                                <td class="fw-bold">{{ $event->id }}</td>
                                <td>
                                    <div>
                                        <strong class="d-block">{{ $event->title_ar }}</strong>
                                        <small class="text-muted">{{ $event->title }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $event->category->name_ar ?? 'غير محدد' }}
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <i class="bi bi-calendar3"></i> {{ $event->start_time->format('Y-m-d') }}
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> {{ $event->start_time->format('H:i') }} - {{ $event->end_time->format('H:i') }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <i class="bi bi-geo-alt"></i> {{ $event->location_ar }}
                                </td>
                                <td>
                                    @php
                                        $available = $event->available_tickets ?? 0;
                                        $total = $event->total_tickets ?? 0;
                                        $percentage = $total > 0 ? ($available / $total) * 100 : 0;
                                        if ($percentage > 50) {
                                            $class = 'availability-high';
                                        } elseif ($percentage > 20) {
                                            $class = 'availability-medium';
                                        } else {
                                            $class = 'availability-low';
                                        }
                                    @endphp
                                    <span class="availability-badge {{ $class }}">
                                        {{ $available }} / {{ $total }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $event->price }}</strong> {{ $event->currency }}
                                </td>
                                <td>
                                    @if($event->is_active)
                                    <span class="badge bg-success">نشط</span>
                                    @else
                                    <span class="badge bg-secondary">غير نشط</span>
                                    @endif
                                    @if($event->is_featured)
                                    <span class="badge bg-warning">مميز</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.events.show', $event) }}"
                                           class="btn btn-outline-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.events.edit', $event) }}"
                                           class="btn btn-outline-primary" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.events.destroy', $event) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="bi bi-calendar-x display-1 text-muted opacity-25"></i>
                                    <p class="text-muted mt-3 mb-0">لا توجد فعاليات حتى الآن</p>
                                    <a href="{{ route('admin.events.create') }}" class="btn btn-primary btn-sm mt-3">
                                        <i class="bi bi-plus-circle"></i> إضافة أول فعالية
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($events->hasPages())
                <div class="mt-3">
                    {{ $events->links() }}
                </div>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header event-details-modal">
                <h5 class="modal-title" id="eventModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <a href="#" id="eventEditLink" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> تعديل
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endsection

@if(($view ?? 'list') === 'calendar')
@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ar',
        direction: 'rtl',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'اليوم',
            month: 'شهر',
            week: 'أسبوع',
            day: 'يوم',
            list: 'قائمة'
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '23:00:00',
        events: @json($calendarEvents ?? []),
        eventClick: function(info) {
            info.jsEvent.preventDefault();

            const event = info.event;
            const props = event.extendedProps;

            document.getElementById('eventModalTitle').textContent = event.title;

            const startDate = new Date(event.start);
            const endDate = new Date(event.end);
            const dateFormatter = new Intl.DateTimeFormat('ar-SA', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const timeFormatter = new Intl.DateTimeFormat('ar-SA', {
                hour: '2-digit',
                minute: '2-digit'
            });

            const available = props.available_tickets || 0;
            const total = props.total_tickets || 0;
            const percentage = total > 0 ? ((available / total) * 100).toFixed(0) : 0;
            let availabilityClass = 'availability-high';
            if (percentage <= 20) availabilityClass = 'availability-low';
            else if (percentage <= 50) availabilityClass = 'availability-medium';

            const modalBody = `
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="bi bi-folder"></i> الفئة</h6>
                    <span class="badge bg-info">${props.category}</span>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="bi bi-calendar3"></i> التاريخ والوقت</h6>
                    <p class="mb-1">${dateFormatter.format(startDate)}</p>
                    <p class="mb-0 text-muted">
                        <i class="bi bi-clock"></i>
                        ${timeFormatter.format(startDate)} - ${timeFormatter.format(endDate)}
                    </p>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="bi bi-geo-alt"></i> الموقع</h6>
                    <p class="mb-0">${props.location}</p>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="bi bi-ticket-perforated"></i> التذاكر المتاحة</h6>
                    <span class="availability-badge ${availabilityClass}">
                        ${available} / ${total} (${percentage}%)
                    </span>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2"><i class="bi bi-tag"></i> السعر</h6>
                    <h4 class="mb-0 text-primary">${props.price}</h4>
                </div>
            `;

            document.getElementById('eventModalBody').innerHTML = modalBody;
            document.getElementById('eventEditLink').href = `/admin/events/${event.id}/edit`;

            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        },
        eventDidMount: function(info) {
            info.el.title = `${info.event.title}\n${info.event.extendedProps.location}`;
        }
    });

    calendar.render();
});
</script>
@endpush
@endif
