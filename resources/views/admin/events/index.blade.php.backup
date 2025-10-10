@extends('layouts.admin')

@section('title', 'الفعاليات')
@section('page-title', 'إدارة الفعاليات')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع الفعاليات</h5>
                        <a href="{{ route('admin.events.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة فعالية جديدة
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
                                    <th>العنوان</th>
                                    <th>الفئة</th>
                                    <th>التاريخ</th>
                                    <th>الموقع</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($events as $event)
                                <tr>
                                    <td>{{ $event->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $event->title_ar }}</strong>
                                            <small class="text-muted d-block">{{ $event->title }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $event->category->name_ar ?? 'غير محدد' }}</span>
                                    </td>
                                    <td>{{ $event->start_time->format('Y-m-d H:i') }}</td>
                                    <td>{{ $event->location_ar }}</td>
                                    <td>{{ $event->price }} {{ $event->currency }}</td>
                                    <td>
                                        @if($event->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                        @if($event->is_featured)
                                        <span class="badge bg-warning">مميز</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.events.show', $event) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.events.destroy', $event) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                            <p class="mt-2">لا توجد فعاليات حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($events->hasPages())
                    <div class="mt-3">
                        {{ $events->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection