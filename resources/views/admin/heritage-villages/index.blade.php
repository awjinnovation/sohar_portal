@extends('layouts.admin')

@section('title', 'القرى التراثية')
@section('page-title', 'إدارة القرى التراثية')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع القرى التراثية</h5>
                        <a href="{{ route('admin.heritage-villages.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة قرية جديدة
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
                                    <th>النوع</th>
                                    <th>ساعات العمل</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($villages as $village)
                                <tr>
                                    <td>{{ $village->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $village->name_ar }}</strong>
                                            <small class="text-muted d-block">{{ $village->name_en }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($village->type == 'maritime')
                                        <span class="badge bg-info">بحري</span>
                                        @elseif($village->type == 'agricultural')
                                        <span class="badge bg-success">زراعي</span>
                                        @else
                                        <span class="badge bg-warning">بدوي</span>
                                        @endif
                                    </td>
                                    <td>{{ $village->opening_hours }}</td>
                                    <td>
                                        @if($village->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.heritage-villages.show', $village) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.heritage-villages.edit', $village) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.heritage-villages.destroy', $village) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox display-4"></i>
                                            <p class="mt-2">لا توجد قرى تراثية حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($villages->hasPages())
                    <div class="mt-3">
                        {{ $villages->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
