@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">صور القرى التراثية</h1>
        <a href="{{ route('admin.village-images.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة صورة جديدة
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
                            <th>الصورة</th>
                            <th>القرية</th>
                            <th>الوصف</th>
                            <th>مميزة</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($images as $image)
                            <tr>
                                <td>
                                    <img src="{{ $image->image_url }}" alt="صورة" style="width: 100px; height: 60px; object-fit: cover;">
                                </td>
                                <td>{{ $image->village->name_ar ?? '-' }}</td>
                                <td>{{ $image->caption_ar ?? $image->caption_en ?? '-' }}</td>
                                <td>
                                    @if($image->is_featured)
                                        <span class="badge bg-success">مميزة</span>
                                    @else
                                        <span class="badge bg-secondary">عادية</span>
                                    @endif
                                </td>
                                <td>{{ $image->display_order ?? '-' }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.village-images.show', $image) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.village-images.edit', $image) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.village-images.destroy', $image) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="6" class="text-center">لا توجد صور</td>
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
