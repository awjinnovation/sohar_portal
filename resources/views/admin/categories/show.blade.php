@extends('layouts.admin')

@section('title', 'عرض الفئة')
@section('page-title', 'تفاصيل الفئة')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">معلومات الفئة</h5>
                        <div>
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil"></i>
                                تعديل
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">الاسم بالعربية</label>
                            <h5>{{ $category->name_ar }}</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">الاسم بالإنجليزية</label>
                            <h5>{{ $category->name }}</h5>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">الوصف بالعربية</label>
                            <p>{{ $category->description_ar }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">الوصف بالإنجليزية</label>
                            <p>{{ $category->description }}</p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <label class="text-muted small">الأيقونة</label>
                            <p><i class="bi bi-{{ $category->icon_name }}"></i> {{ $category->icon_name }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">اللون</label>
                            <div style="width: 30px; height: 30px; background: #{!! sprintf('%06X', $category->color_value) !!}; border-radius: 4px;"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">الترتيب</label>
                            <p>{{ $category->display_order }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">الحالة</label>
                            <p>
                                @if($category->is_active)
                                <span class="badge bg-success">نشط</span>
                                @else
                                <span class="badge bg-secondary">غير نشط</span>
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
                    <h5 class="mb-0 fw-bold">الفعاليات المرتبطة</h5>
                </div>
                <div class="card-body">
                    @if($category->events && $category->events->count() > 0)
                    <div class="list-group">
                        @foreach($category->events as $event)
                        <a href="{{ route('admin.events.show', $event) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1">{{ $event->title_ar }}</h6>
                                <small>{{ $event->start_time->format('Y-m-d') }}</small>
                            </div>
                            <small class="text-muted">{{ $event->location_ar }}</small>
                        </a>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted text-center">لا توجد فعاليات مرتبطة</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection