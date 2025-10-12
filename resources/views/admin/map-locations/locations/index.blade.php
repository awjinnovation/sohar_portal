@extends('layouts.admin')

@section('title', 'المواقع')
@section('page-title', 'جميع المواقع')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">إدارة المواقع</h4>
                    <p class="text-muted mb-0">عرض وإدارة جميع المواقع في النظام</p>
                </div>
                <a href="{{ route('admin.locations.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> إضافة موقع جديد
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filter & Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.locations.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">البحث</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="ابحث عن موقع..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع الموقع</label>
                    <select name="type" class="form-select">
                        <option value="">الكل</option>
                        <option value="restaurant" {{ request('type') == 'restaurant' ? 'selected' : '' }}>مطعم</option>
                        <option value="activity" {{ request('type') == 'activity' ? 'selected' : '' }}>نشاط</option>
                        <option value="service" {{ request('type') == 'service' ? 'selected' : '' }}>خدمة</option>
                        <option value="emergency" {{ request('type') == 'emergency' ? 'selected' : '' }}>طوارئ</option>
                        <option value="parking" {{ request('type') == 'parking' ? 'selected' : '' }}>موقف سيارات</option>
                        <option value="restroom" {{ request('type') == 'restroom' ? 'selected' : '' }}>دورة مياه</option>
                        <option value="first_aid" {{ request('type') == 'first_aid' ? 'selected' : '' }}>إسعاف أولي</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> بحث
                    </button>
                    <a href="{{ route('admin.locations.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> مسح
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Locations Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>العنوان</th>
                            <th>رقم الاتصال</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $location->name }}</div>
                                    <small class="text-muted">{{ $location->name_ar }}</small>
                                </td>
                                <td>
                                    @php
                                        $typeLabels = [
                                            'restaurant' => 'مطعم',
                                            'activity' => 'نشاط',
                                            'service' => 'خدمة',
                                            'emergency' => 'طوارئ',
                                            'parking' => 'موقف',
                                            'restroom' => 'دورة مياه',
                                            'first_aid' => 'إسعاف'
                                        ];
                                        $typeColors = [
                                            'restaurant' => 'success',
                                            'activity' => 'info',
                                            'service' => 'primary',
                                            'emergency' => 'danger',
                                            'parking' => 'secondary',
                                            'restroom' => 'warning',
                                            'first_aid' => 'danger'
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $typeColors[$location->type] ?? 'secondary' }}">
                                        {{ $typeLabels[$location->type] ?? $location->type }}
                                    </span>
                                </td>
                                <td>
                                    <small>{{ $location->address ?? $location->address_ar ?? '-' }}</small>
                                </td>
                                <td>{{ $location->contact_number ?? '-' }}</td>
                                <td>
                                    @if($location->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.locations.show', $location) }}"
                                           class="btn btn-sm btn-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.locations.edit', $location) }}"
                                           class="btn btn-sm btn-warning" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.locations.destroy', $location) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="text-muted mt-2">لا توجد مواقع</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $locations->links() }}
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
</style>
@endpush
@endsection
