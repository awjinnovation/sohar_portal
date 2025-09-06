@extends('layouts.admin')

@section('title', 'الفئات')
@section('page-title', 'إدارة الفئات')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع الفئات</h5>
                        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة فئة جديدة
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

                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>الاسم</th>
                                    <th>الأيقونة</th>
                                    <th>الترتيب</th>
                                    <th>عدد الفعاليات</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $category->name_ar }}</strong>
                                            <small class="text-muted d-block">{{ $category->name }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="bi bi-{{ $category->icon_name }}"></i>
                                        {{ $category->icon_name }}
                                    </td>
                                    <td>{{ $category->display_order }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $category->events_count ?? 0 }}</span>
                                    </td>
                                    <td>
                                        @if($category->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.categories.show', $category) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                            <p class="mt-2">لا توجد فئات حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($categories->hasPages())
                    <div class="mt-3">
                        {{ $categories->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection