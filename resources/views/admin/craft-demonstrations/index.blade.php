@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">العروض الحرفية</h1>
        <a href="{{ route('admin.craft-demonstrations.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة عرض حرفي جديد
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
                            <th>اسم الحرفة</th>
                            <th>القرية</th>
                            <th>الحرفي</th>
                            <th>المدة</th>
                            <th>تجربة عملية</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($demonstrations as $demo)
                            <tr>
                                <td>{{ $demo->craft_name_ar }}</td>
                                <td>{{ $demo->village->name_ar ?? '-' }}</td>
                                <td>{{ $demo->artisan_name ?? '-' }}</td>
                                <td>{{ $demo->duration_minutes ? $demo->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>
                                    @if($demo->can_try_hands_on)
                                        <span class="badge bg-success">متاح</span>
                                    @else
                                        <span class="badge bg-secondary">غير متاح</span>
                                    @endif
                                </td>
                                <td>
                                    @if($demo->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.craft-demonstrations.show', $demo) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.craft-demonstrations.edit', $demo) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.craft-demonstrations.destroy', $demo) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="7" class="text-center">لا توجد عروض حرفية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $demonstrations->links() }}
        </div>
    </div>
</div>
@endsection
