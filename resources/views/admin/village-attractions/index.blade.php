@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">معالم القرى التراثية</h1>
        <a href="{{ route('admin.village-attractions.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة معلم جديد
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
                            <th>الاسم</th>
                            <th>القرية</th>
                            <th>ساعات الزيارة</th>
                            <th>المدة الموصى بها</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attractions as $attraction)
                            <tr>
                                <td>{{ $attraction->name_ar }}</td>
                                <td>{{ $attraction->village->name_ar ?? '-' }}</td>
                                <td>{{ $attraction->visiting_hours ?? '-' }}</td>
                                <td>{{ $attraction->recommended_duration ?? '-' }}</td>
                                <td>
                                    @if($attraction->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.village-attractions.show', $attraction) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.village-attractions.edit', $attraction) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.village-attractions.destroy', $attraction) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="6" class="text-center">لا توجد معالم</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $attractions->links() }}
        </div>
    </div>
</div>
@endsection
