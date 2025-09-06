@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">الأنشطة التقليدية</h1>
        <a href="{{ route('admin.traditional-activities.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة نشاط تقليدي جديد
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
                            <th>اسم النشاط</th>
                            <th>القرية</th>
                            <th>النوع</th>
                            <th>المدة</th>
                            <th>الحد الأقصى للمشاركين</th>
                            <th>يتطلب حجز</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            <tr>
                                <td>{{ $activity->activity_name_ar }}</td>
                                <td>{{ $activity->village->name_ar ?? '-' }}</td>
                                <td>{{ $activity->activity_type }}</td>
                                <td>{{ $activity->duration_minutes ? $activity->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>{{ $activity->max_participants ?? '-' }}</td>
                                <td>
                                    @if($activity->booking_required)
                                        <span class="badge bg-warning">مطلوب</span>
                                    @else
                                        <span class="badge bg-info">غير مطلوب</span>
                                    @endif
                                </td>
                                <td>
                                    @if($activity->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.traditional-activities.show', $activity) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.traditional-activities.edit', $activity) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.traditional-activities.destroy', $activity) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="8" class="text-center">لا توجد أنشطة تقليدية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        </div>
    </div>
</div>
@endsection
