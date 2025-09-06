@extends('layouts.admin')

@section('title', 'الإعلانات')
@section('page-title', 'إدارة الإعلانات')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع الإعلانات</h5>
                        <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة إعلان جديد
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
                                    <th>العنوان</th>
                                    <th>النوع</th>
                                    <th>الأولوية</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($announcements as $announcement)
                                <tr>
                                    <td>{{ $announcement->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $announcement->title_ar }}</strong>
                                            @if($announcement->is_pinned)
                                            <i class="bi bi-pin-angle-fill text-danger"></i>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($announcement->type == 'info')
                                        <span class="badge bg-info">معلومات</span>
                                        @elseif($announcement->type == 'warning')
                                        <span class="badge bg-warning">تحذير</span>
                                        @elseif($announcement->type == 'emergency')
                                        <span class="badge bg-danger">طوارئ</span>
                                        @else
                                        <span class="badge bg-success">احتفال</span>
                                        @endif
                                    </td>
                                    <td>{{ $announcement->priority ?? 0 }}</td>
                                    <td>{{ $announcement->start_datetime->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($announcement->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.announcements.show', $announcement) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                    <td colspan="7" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox display-4"></i>
                                            <p class="mt-2">لا توجد إعلانات حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($announcements->hasPages())
                    <div class="mt-3">
                        {{ $announcements->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
