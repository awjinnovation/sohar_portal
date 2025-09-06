@extends('layouts.admin')

@section('title', 'إدارة الأدوار')
@section('page-title', 'الأدوار والصلاحيات')

@section('content')
<div class="container-fluid">
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">قائمة الأدوار</h5>
                @can('assign roles')
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    إضافة دور جديد
                </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الدور</th>
                            <th>عدد الصلاحيات</th>
                            <th>عدد المستخدمين</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                        <tr>
                            <td>{{ $role->id }}</td>
                            <td>
                                <span class="badge bg-primary fs-6">{{ ucfirst($role->name) }}</span>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $role->permissions->count() }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $role->users->count() }}</span>
                            </td>
                            <td>{{ $role->created_at->format('Y-m-d') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    @if(!in_array($role->name, ['super-admin', 'admin']))
                                        @can('assign roles')
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endcan
                                        
                                        @can('delete users')
                                        @if($role->users->count() == 0)
                                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الدور؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                        @endcan
                                    @else
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="لا يمكن تعديل هذا الدور">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <p class="text-muted mb-0">لا توجد أدوار</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $roles->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">ملاحظات النظام</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-0">
                <h6 class="alert-heading">
                    <i class="bi bi-info-circle"></i>
                    معلومات مهمة حول الأدوار
                </h6>
                <ul class="mb-0 mt-2">
                    <li>الأدوار <strong>super-admin</strong> و <strong>admin</strong> محمية ولا يمكن تعديلها أو حذفها</li>
                    <li>لا يمكن حذف دور مرتبط بمستخدمين نشطين</li>
                    <li>كل دور يمكن أن يحتوي على صلاحيات متعددة</li>
                    <li>المستخدم يمكن أن يمتلك أكثر من دور واحد</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection