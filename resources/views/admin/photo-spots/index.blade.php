@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">مواقع التصوير</h1>
        <a href="{{ route('admin.photo-spots.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة موقع تصوير جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>اسم الموقع</th>
                            <th>القرية</th>
                            <th>أفضل وقت للتصوير</th>
                            <th>سهل الوصول</th>
                            <th>شائع</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($photoSpots as $spot)
                            <tr>
                                <td>{{ $spot->spot_name_ar }}</td>
                                <td>{{ $spot->village->name_ar ?? '-' }}</td>
                                <td>{{ $spot->best_time_for_photos ?? '-' }}</td>
                                <td>
                                    @if($spot->is_accessible)
                                        <span class="badge bg-success">نعم</span>
                                    @else
                                        <span class="badge bg-warning">لا</span>
                                    @endif
                                </td>
                                <td>
                                    @if($spot->is_popular)
                                        <span class="badge bg-info">شائع</span>
                                    @else
                                        <span class="badge bg-secondary">عادي</span>
                                    @endif
                                </td>
                                <td>
                                    @if($spot->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.photo-spots.show', $spot) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.photo-spots.edit', $spot) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.photo-spots.destroy', $spot) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">لا توجد مواقع تصوير</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $photoSpots->links() }}
        </div>
    </div>
</div>
@endsection
