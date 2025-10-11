@extends('layouts.admin')

@section('title', 'سياسات الخصوصية')
@section('page-title', 'إدارة سياسات الخصوصية')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.privacy-policies.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>إضافة سياسة خصوصية جديدة
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
                        <th>اللغة</th>
                        <th>العنوان</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $policy)
                    <tr>
                        <td>{{ $policy->id }}</td>
                        <td>
                            <span class="badge bg-info">{{ strtoupper($policy->language) }}</span>
                        </td>
                        <td>{{ $policy->title }}</td>
                        <td>
                            @if($policy->is_active)
                                <span class="badge bg-success">نشط</span>
                            @else
                                <span class="badge bg-secondary">غير نشط</span>
                            @endif
                        </td>
                        <td>{{ $policy->created_at->format('Y-m-d') }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('admin.privacy-policies.show', $policy) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.privacy-policies.edit', $policy) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.privacy-policies.destroy', $policy) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه السياسة؟')">
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
                        <td colspan="6" class="text-center">لا توجد سياسات خصوصية</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $policies->links() }}
        </div>
    </div>
</div>
@endsection
