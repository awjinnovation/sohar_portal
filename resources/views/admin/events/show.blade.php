@extends('layouts.admin')

@section('title', 'عرض الفعالية')
@section('page-title', 'تفاصيل الفعالية')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">معلومات الفعالية</h5>
                        <div>
                            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil"></i>
                                تعديل
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">العنوان بالعربية</label>
                            <h5>{{ $event->title_ar }}</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">العنوان بالإنجليزية</label>
                            <h5>{{ $event->title }}</h5>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted small">الوصف بالعربية</label>
                            <p>{{ $event->description_ar }}</p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">الوصف بالإنجليزية</label>
                            <p>{{ $event->description }}</p>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="text-muted small">الفئة</label>
                            <p><span class="badge bg-info">{{ $event->category->name_ar ?? 'غير محدد' }}</span></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">التاريخ</label>
                            <p>{{ $event->start_time->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">الموقع</label>
                            <p>{{ $event->location_ar }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">السعر</label>
                            <p>{{ $event->price }} {{ $event->currency }}</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <label class="text-muted small">التذاكر المتاحة</label>
                            <p>{{ $event->available_tickets ?? 'غير محدد' }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">إجمالي التذاكر</label>
                            <p>{{ $event->total_tickets ?? 'غير محدد' }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">المنظم</label>
                            <p>{{ $event->organizer_name_ar ?? 'غير محدد' }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">الحالة</label>
                            <p>
                                @if($event->is_active)
                                <span class="badge bg-success">نشط</span>
                                @else
                                <span class="badge bg-secondary">غير نشط</span>
                                @endif
                                @if($event->is_featured)
                                <span class="badge bg-warning">مميز</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">التذاكر المباعة</h5>
                </div>
                <div class="card-body">
                    @if($event->tickets && $event->tickets->count() > 0)
                    <div class="list-group">
                        @foreach($event->tickets as $ticket)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1">{{ $ticket->holder_name }}</h6>
                                <span class="badge bg-{{ $ticket->status == 'active' ? 'success' : 'secondary' }}">
                                    {{ $ticket->status }}
                                </span>
                            </div>
                            <small class="text-muted">{{ $ticket->purchase_date->format('Y-m-d') }}</small>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted text-center">لا توجد تذاكر مباعة حتى الآن</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">الوسوم</h5>
                </div>
                <div class="card-body">
                    @if($event->tags && $event->tags->count() > 0)
                        @foreach($event->tags as $tag)
                        <span class="badge bg-primary me-1 mb-1">{{ $tag->tag_ar }}</span>
                        @endforeach
                    @else
                    <p class="text-muted text-center">لا توجد وسوم</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection