@extends('layouts.admin')

@section('title', 'المطاعم')
@section('page-title', 'إدارة المطاعم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع المطاعم</h5>
                        <a href="{{ route('admin.restaurants.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة مطعم جديد
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>الاسم</th>
                                    <th>المطبخ</th>
                                    <th>الموقع</th>
                                    <th>التقييم</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($restaurants as $restaurant)
                                <tr>
                                    <td>{{ $restaurant->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $restaurant->name_ar }}</strong>
                                            <small class="text-muted d-block">{{ $restaurant->name }}</small>
                                        </div>
                                    </td>
                                    <td>{{ $restaurant->cuisine_ar }}</td>
                                    <td>{{ $restaurant->location_ar }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-1">{{ $restaurant->rating }}</span>
                                            <i class="bi bi-star-fill text-warning"></i>
                                        </div>
                                    </td>
                                    <td>{{ $restaurant->price_range }}</td>
                                    <td>
                                        @if($restaurant->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                        @if($restaurant->is_featured)
                                        <span class="badge bg-warning">مميز</span>
                                        @endif
                                        @if($restaurant->is_open)
                                        <span class="badge bg-info">مفتوح</span>
                                        @else
                                        <span class="badge bg-danger">مغلق</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox display-4"></i>
                                            <p class="mt-2">لا توجد مطاعم حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($restaurants->hasPages())
                    <div class="mt-3">
                        {{ $restaurants->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection