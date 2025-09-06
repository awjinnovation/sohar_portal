@extends('layouts.admin')

@section('title', 'تعديل الدور')
@section('page-title', 'تعديل الدور')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">تعديل معلومات الدور</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.roles.update', $role) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-4">
                            <label for="name" class="form-label">اسم الدور <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $role->name) }}" required>
                            <small class="text-muted">استخدم اسماً واضحاً باللغة الإنجليزية</small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">الصلاحيات</label>
                            <div class="border rounded p-3">
                                @foreach($permissions as $group => $groupPermissions)
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-shield-check"></i>
                                        {{ ucfirst(str_replace('_', ' ', $group)) }}
                                    </h6>
                                    <div class="row">
                                        @foreach($groupPermissions as $permission)
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input permission-check" type="checkbox" 
                                                       name="permissions[]" value="{{ $permission->id }}" 
                                                       id="permission_{{ $permission->id }}"
                                                       data-group="{{ $group }}"
                                                       {{ in_array($permission->id, old('permissions', $rolePermissions)) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="permission_{{ $permission->id }}">
                                                    {{ $permission->name }}
                                                </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if(!$loop->last)
                                <hr>
                                @endif
                                @endforeach
                            </div>
                            @error('permissions')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">
                                    تحديد جميع الصلاحيات
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>تنبيه:</strong> تعديل الصلاحيات سيؤثر على جميع المستخدمين الذين يمتلكون هذا الدور
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                                    رجوع
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Check if all checkboxes are already checked on load
window.addEventListener('DOMContentLoaded', function() {
    const allCheckboxes = document.querySelectorAll('.permission-check');
    const checkedCheckboxes = document.querySelectorAll('.permission-check:checked');
    if(allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0) {
        document.getElementById('selectAll').checked = true;
    }
});

document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.permission-check');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Update select all checkbox when individual checkboxes change
document.querySelectorAll('.permission-check').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.permission-check');
        const checkedCheckboxes = document.querySelectorAll('.permission-check:checked');
        document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
    });
});
</script>
@endpush
@endsection