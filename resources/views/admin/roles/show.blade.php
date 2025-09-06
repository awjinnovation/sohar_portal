@extends('layouts.admin')

@section('title', 'عرض الدور')
@section('page-title', 'تفاصيل الدور')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">معلومات الدور</h5>
                        <div>
                            @if(!in_array($role->name, ['super-admin', 'admin']))
                                @can('assign roles')
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil ms-2"></i>
                                    تعديل
                                </a>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="text-muted small">اسم الدور</label>
                                <h5>
                                    <span class="badge bg-primary">{{ ucfirst($role->name) }}</span>
                                </h5>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small">عدد المستخدمين</label>
                                <h5>{{ $role->users->count() }}</h5>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small">عدد الصلاحيات</label>
                                <h5>{{ $role->permissions->count() }}</h5>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-4">
                        <h6 class="mb-3">الصلاحيات المخصصة لهذا الدور</h6>
                        <div class="border rounded p-3">
                            @php
                                $groupedPermissions = $role->permissions->groupBy(function($permission) {
                                    $parts = explode(' ', $permission->name);
                                    return end($parts);
                                });
                            @endphp
                            
                            @forelse($groupedPermissions as $group => $permissions)
                            <div class="mb-3">
                                <h6 class="text-primary">
                                    <i class="bi bi-shield-check ms-2"></i>
                                    {{ ucfirst(str_replace('_', ' ', $group)) }}
                                </h6>
                                <div class="row mt-2">
                                    @foreach($permissions as $permission)
                                    <div class="col-md-4 mb-2">
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle ms-1"></i>
                                            {{ $permission->name }}
                                        </span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @if(!$loop->last)
                            <hr>
                            @endif
                            @empty
                            <p class="text-muted mb-0">لا توجد صلاحيات محددة لهذا الدور</p>
                            @endforelse
                        </div>
                    </div>
                    
                    @if($role->users->count() > 0)
                    <hr>
                    <div class="mb-4">
                        <h6 class="mb-3">المستخدمون بهذا الدور</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>تاريخ التعيين</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($role->users->take(10) as $user)
                                    <tr>
                                        <td>{{ $user->name }}</td>
                                        <td dir="ltr" class="text-end">{{ $user->email }}</td>
                                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($role->users->count() > 10)
                            <p class="text-muted small">وآخرون... (إجمالي {{ $role->users->count() }} مستخدم)</p>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-right ms-2"></i>
                            رجوع
                        </a>
                        
                        @if(!in_array($role->name, ['super-admin', 'admin']) && $role->users->count() == 0)
                            @can('delete users')
                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الدور؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash ms-2"></i>
                                    حذف الدور
                                </button>
                            </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection