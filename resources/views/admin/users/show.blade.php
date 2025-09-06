@extends('layouts.admin')

@section('title', 'عرض المستخدم')
@section('page-title', 'تفاصيل المستخدم')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">معلومات المستخدم</h5>
                        <div>
                            @can('edit users')
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil"></i>
                                تعديل
                            </a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <span class="text-primary fw-bold fs-2">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                        <h4>{{ $user->name }}</h4>
                        <p class="text-muted" dir="ltr">{{ $user->email }}</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">الأدوار</label>
                                <div>
                                    @foreach($user->roles as $role)
                                        <span class="badge bg-info me-1">{{ ucfirst($role->name) }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">حالة الحساب</label>
                                <div>
                                    <span class="badge bg-success">نشط</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">تاريخ التسجيل</label>
                                <div>{{ $user->created_at->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">آخر تحديث</label>
                                <div>{{ $user->updated_at->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="text-muted small mb-2">الصلاحيات</label>
                        <div class="border rounded p-3">
                            <div class="row">
                                @php
                                    $userPermissions = $user->getAllPermissions();
                                @endphp
                                @forelse($userPermissions as $permission)
                                <div class="col-md-4 mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    <span class="small">{{ $permission->name }}</span>
                                </div>
                                @empty
                                <p class="text-muted mb-0">لا توجد صلاحيات محددة</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                            رجوع
                        </a>
                        
                        @can('delete users')
                        @if($user->id !== auth()->id())
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i>
                                حذف المستخدم
                            </button>
                        </form>
                        @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 100px;
    height: 100px;
}
</style>
@endsection