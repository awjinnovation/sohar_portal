@extends('layouts.admin')

@section('title', 'الشروط والأحكام')
@section('page-title', 'إدارة الشروط والأحكام')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.terms-and-conditions.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>إضافة شروط وأحكام جديدة
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>رقم</th>
                        <th>النوع</th>
                        <th>اللغة</th>
                        <th>العنوان</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($terms as $term)
                    <tr>
                        <td>{{ $term->id }}</td>
                        <td>
                            <span class="badge bg-primary">{{ ucfirst($term->type) }}</span>
                        </td>
                        <td>
                            <span class="badge bg-info">{{ strtoupper($term->language) }}</span>
                        </td>
                        <td>{{ $term->title }}</td>
                        <td>
                            @if($term->is_active)
                                <span class="badge bg-success">نشط</span>
                            @else
                                <span class="badge bg-secondary">غير نشط</span>
                            @endif
                        </td>
                        <td>{{ $term->created_at->format('Y-m-d') }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.terms-and-conditions.show', $term) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.terms-and-conditions.edit', $term) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.terms-and-conditions.destroy', $term) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الشروط؟')">
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
                        <td colspan="7" class="text-center">لا توجد شروط وأحكام</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $terms->links() }}
        </div>
    </div>
</div>
@endsection
