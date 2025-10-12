@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-info-circle text-info"></i> تفاصيل الموقع
            </h1>
            <p class="text-muted mb-0">معلومات كاملة عن الموقع: {{ $mapLocation->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.map-locations.edit', $mapLocation) }}" class="btn btn-warning shadow-sm">
                <i class="bi bi-pencil"></i> تعديل
            </a>
            <a href="{{ route('admin.map-locations.index') }}" class="btn btn-secondary shadow-sm">
                <i class="bi bi-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Main Info -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle-fill"></i> المعلومات الأساسية</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">المعرف</label>
                            <p class="fw-bold mb-0">#{{ $mapLocation->id }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">النوع</label>
                            <p class="mb-0">
                                <span class="badge bg-{{ $mapLocation->type == 'emergency' ? 'danger' : ($mapLocation->type == 'food' ? 'warning' : 'primary') }} fs-6">
                                    <i class="bi bi-{{ $mapLocation->icon }}"></i> {{ $mapLocation->type }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">الحالة</label>
                            <p class="mb-0">
                                @if($mapLocation->is_active)
                                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle-fill"></i> نشط</span>
                                @else
                                    <span class="badge bg-secondary fs-6"><i class="bi bi-x-circle-fill"></i> غير نشط</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">الأيقونة</label>
                            <p class="mb-0">
                                <i class="bi bi-{{ $mapLocation->icon }}" style="font-size: 2rem;"></i>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="text-muted small">الاسم (عربي)</label>
                        <p class="fw-bold mb-0">{{ $mapLocation->name_ar }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">الاسم (English)</label>
                        <p class="fw-bold mb-0">{{ $mapLocation->name }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">الوصف (عربي)</label>
                        <p class="text-muted mb-0">{{ $mapLocation->description_ar }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">الوصف (English)</label>
                        <p class="text-muted mb-0">{{ $mapLocation->description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <!-- Location Coordinates -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-geo-alt-fill"></i> الموقع الجغرافي</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">خط العرض (Latitude)</label>
                        <p class="fw-bold mb-0">{{ $mapLocation->latitude }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">خط الطول (Longitude)</label>
                        <p class="fw-bold mb-0">{{ $mapLocation->longitude }}</p>
                    </div>
                    <a href="https://www.google.com/maps?q={{ $mapLocation->latitude }},{{ $mapLocation->longitude }}"
                       target="_blank" class="btn btn-outline-success btn-sm w-100">
                        <i class="bi bi-map"></i> عرض على Google Maps
                    </a>
                </div>
            </div>

            <!-- Color & Icon -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-palette-fill"></i> المظهر</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <label class="text-muted small d-block">اللون</label>
                        @if($mapLocation->color)
                            <div class="d-inline-block rounded-circle mt-2"
                                 style="width: 60px; height: 60px; background-color: #{{ dechex($mapLocation->color) }}; border: 3px solid #dee2e6;">
                            </div>
                            <p class="mt-2 mb-0 small text-muted">#{{ dechex($mapLocation->color) }}</p>
                        @else
                            <p class="text-muted">لا يوجد</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Timestamps -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> التواريخ</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">تاريخ الإنشاء</label>
                        <p class="mb-0">{{ $mapLocation->created_at->format('Y-m-d H:i') }}</p>
                        <small class="text-muted">{{ $mapLocation->created_at->diffForHumans() }}</small>
                    </div>
                    <div>
                        <label class="text-muted small">آخر تحديث</label>
                        <p class="mb-0">{{ $mapLocation->updated_at->format('Y-m-d H:i') }}</p>
                        <small class="text-muted">{{ $mapLocation->updated_at->diffForHumans() }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">إجراءات</h5>
                    <p class="text-muted small mb-0">تحكم في الموقع</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.map-locations.edit', $mapLocation) }}" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <form action="{{ route('admin.map-locations.destroy', $mapLocation) }}" method="POST"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> حذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
