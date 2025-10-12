@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-map text-primary"></i> مواقع الخريطة
            </h1>
            <p class="text-muted mb-0">إدارة المواقع والأماكن على الخريطة التفاعلية</p>
        </div>
        <a href="{{ route('admin.map-locations.create') }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle"></i> إضافة موقع جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.map-locations.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">النوع</label>
                    <select name="type" class="form-select">
                        <option value="">الكل</option>
                        <option value="entertainment" {{ request('type') == 'entertainment' ? 'selected' : '' }}>ترفيه</option>
                        <option value="food" {{ request('type') == 'food' ? 'selected' : '' }}>طعام</option>
                        <option value="facilities" {{ request('type') == 'facilities' ? 'selected' : '' }}>مرافق</option>
                        <option value="parking" {{ request('type') == 'parking' ? 'selected' : '' }}>مواقف</option>
                        <option value="emergency" {{ request('type') == 'emergency' ? 'selected' : '' }}>طوارئ</option>
                        <option value="first_aid" {{ request('type') == 'first_aid' ? 'selected' : '' }}>إسعافات</option>
                        <option value="restroom" {{ request('type') == 'restroom' ? 'selected' : '' }}>دورات مياه</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> بحث
                    </button>
                    <a href="{{ route('admin.map-locations.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">إجمالي المواقع</h6>
                            <h3 class="mb-0 fw-bold">{{ $locations->total() }}</h3>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">المواقع النشطة</h6>
                            <h3 class="mb-0 fw-bold text-success">{{ $locations->where('is_active', true)->count() }}</h3>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Locations Grid -->
    <div class="row">
        @forelse($locations as $location)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card shadow-sm h-100 border-0 location-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <span class="badge bg-{{ $location->type == 'emergency' ? 'danger' : ($location->type == 'food' ? 'warning' : 'primary') }} mb-2">
                                    <i class="bi bi-{{ $location->icon }}"></i>
                                    {{ $location->type }}
                                </span>
                                <h5 class="card-title mb-1 fw-bold">{{ $location->name }}</h5>
                                <p class="text-muted small mb-0">{{ Str::limit($location->description, 50) }}</p>
                            </div>
                            <div>
                                @if($location->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-secondary">غير نشط</span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center text-muted small mb-1">
                                <i class="bi bi-geo-fill me-2"></i>
                                <span>{{ $location->latitude }}, {{ $location->longitude }}</span>
                            </div>
                            <div class="d-flex align-items-center text-muted small">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span>{{ $location->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.map-locations.show', $location) }}" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="bi bi-eye"></i> عرض
                            </a>
                            <a href="{{ route('admin.map-locations.edit', $location) }}" class="btn btn-sm btn-outline-warning flex-fill">
                                <i class="bi bi-pencil"></i> تعديل
                            </a>
                            <form action="{{ route('admin.map-locations.destroy', $location) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد مواقع</h5>
                        <p class="text-muted">قم بإضافة موقع جديد للبدء</p>
                        <a href="{{ route('admin.map-locations.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> إضافة موقع جديد
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($locations->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $locations->links() }}
        </div>
    @endif
</div>

<style>
.location-card {
    transition: all 0.3s ease;
}
.location-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
@endsection
