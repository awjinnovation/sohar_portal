@extends('layouts.admin')

@section('title', 'صور المطاعم')
@section('page-title', 'صور المطاعم')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">صور المطاعم</h1>
        <a href="{{ route('admin.restaurant-images.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> إضافة صورة جديدة
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
                            <th width="5%">#</th>
                            <th width="25%">المطعم</th>
                            <th width="35%">رابط الصورة</th>
                            <th width="15%">الترتيب</th>
                            <th width="20%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($images as $image)
                            <tr>
                                <td>{{ $image->id }}</td>
                                <td>{{ $image->restaurant->name ?? 'غير محدد' }}</td>
                                <td>
                                    <a href="{{ $image->image_url }}" target="_blank" class="text-truncate d-inline-block" style="max-width: 300px;">
                                        {{ $image->image_url }}
                                    </a>
                                </td>
                                <td>{{ $image->display_order ?? '-' }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.restaurant-images.show', $image) }}"
                                           class="btn btn-sm btn-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.restaurant-images.edit', $image) }}"
                                           class="btn btn-sm btn-warning" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.restaurant-images.destroy', $image) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذه الصورة؟')">
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
                                <td colspan="5" class="text-center">لا توجد صور مسجلة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $images->links() }}
        </div>
    </div>
</div>
@endsection