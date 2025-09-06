@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">الورش الثقافية</h1>
        <a href="{{ route('admin.cultural-workshops.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة ورشة ثقافية جديدة
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
                            <th>عنوان الورشة</th>
                            <th>القرية</th>
                            <th>المدرب</th>
                            <th>المدة</th>
                            <th>السعر</th>
                            <th>الحد الأقصى</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workshops as $workshop)
                            <tr>
                                <td>{{ $workshop->workshop_title_ar }}</td>
                                <td>{{ $workshop->village->name_ar ?? '-' }}</td>
                                <td>{{ $workshop->instructor_name ?? '-' }}</td>
                                <td>{{ $workshop->duration_minutes ? $workshop->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>{{ $workshop->price ? $workshop->price . ' ر.ع' : 'مجاني' }}</td>
                                <td>{{ $workshop->max_participants ?? '-' }}</td>
                                <td>
                                    @if($workshop->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.cultural-workshops.show', $workshop) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.cultural-workshops.edit', $workshop) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.cultural-workshops.destroy', $workshop) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="8" class="text-center">لا توجد ورش ثقافية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $workshops->links() }}
        </div>
    </div>
</div>
@endsection
