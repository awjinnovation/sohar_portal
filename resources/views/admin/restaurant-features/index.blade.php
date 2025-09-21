@extends('layouts.admin')

@section('title', 'ميزات المطاعم')
@section('page-title', 'ميزات المطاعم')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">ميزات المطاعم</h1>
        <a href="{{ route('admin.restaurant-features.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> إضافة ميزة جديدة
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
                            <th width="30%">الميزة (عربي)</th>
                            <th width="25%">الميزة (انجليزي)</th>
                            <th width="15%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($features as $feature)
                            <tr>
                                <td>{{ $feature->id }}</td>
                                <td>{{ $feature->restaurant->name ?? 'غير محدد' }}</td>
                                <td>{{ $feature->feature_ar }}</td>
                                <td>{{ $feature->feature }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.restaurant-features.show', $feature) }}"
                                           class="btn btn-sm btn-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.restaurant-features.edit', $feature) }}"
                                           class="btn btn-sm btn-warning" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.restaurant-features.destroy', $feature) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذه الميزة؟')">
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
                                <td colspan="5" class="text-center">لا توجد ميزات مسجلة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $features->links() }}
        </div>
    </div>
</div>
@endsection