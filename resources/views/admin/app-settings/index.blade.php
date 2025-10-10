@extends('layouts.admin')

@section('page-title', 'إعدادات التطبيق')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">⚙️ إعدادات تطبيق Flutter</h2>
            <p class="text-muted mb-0">إدارة إعدادات تطبيق الهاتف المحمول</p>
        </div>
        <div>
            <a href="{{ route('admin.app-settings.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> إضافة إعداد جديد
            </a>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Search & Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.app-settings.index') }}" class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="البحث بالاسم..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select">
                        <option value="">جميع الأنواع</option>
                        <option value="string" {{ request('type') == 'string' ? 'selected' : '' }}>نص (String)</option>
                        <option value="integer" {{ request('type') == 'integer' ? 'selected' : '' }}>رقم صحيح (Integer)</option>
                        <option value="boolean" {{ request('type') == 'boolean' ? 'selected' : '' }}>منطقي (Boolean)</option>
                        <option value="decimal" {{ request('type') == 'decimal' ? 'selected' : '' }}>عشري (Decimal)</option>
                        <option value="json" {{ request('type') == 'json' ? 'selected' : '' }}>JSON</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> تصفية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">الإعدادات ({{ $settings->total() }})</h5>
                <span class="badge bg-primary rounded-pill">{{ $settings->total() }} إعداد</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 25%">مفتاح الإعداد</th>
                            <th style="width: 20%">القيمة</th>
                            <th style="width: 10%">النوع</th>
                            <th style="width: 10%">عام</th>
                            <th style="width: 20%">الوصف</th>
                            <th style="width: 10%" class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settings as $setting)
                            <tr>
                                <td class="text-muted">{{ $setting->id }}</td>
                                <td>
                                    <code class="text-primary">{{ $setting->setting_key }}</code>
                                </td>
                                <td>
                                    @if($setting->setting_type == 'boolean')
                                        @if($setting->value)
                                            <span class="badge bg-success">✓ نعم</span>
                                        @else
                                            <span class="badge bg-secondary">✗ لا</span>
                                        @endif
                                    @elseif($setting->setting_type == 'json')
                                        <span class="badge bg-info"><i class="bi bi-braces"></i> JSON</span>
                                    @elseif(strlen($setting->setting_value) > 50)
                                        <small class="text-muted">{{ Str::limit($setting->setting_value, 50) }}</small>
                                    @else
                                        <span class="fw-medium">{{ $setting->setting_value }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badges = [
                                            'string' => 'bg-primary',
                                            'integer' => 'bg-success',
                                            'boolean' => 'bg-warning',
                                            'decimal' => 'bg-info',
                                            'json' => 'bg-dark'
                                        ];
                                        $badgeClass = $badges[$setting->setting_type] ?? 'bg-secondary';
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $setting->setting_type }}</span>
                                </td>
                                <td>
                                    @if($setting->is_public)
                                        <i class="bi bi-globe text-success" title="متاح للعموم"></i>
                                    @else
                                        <i class="bi bi-lock text-muted" title="خاص"></i>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ Str::limit($setting->description ?? '-', 40) }}</small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('admin.app-settings.edit', $setting) }}"
                                           class="btn btn-outline-primary"
                                           title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.app-settings.destroy', $setting) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذا الإعداد؟\n{{ $setting->setting_key }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                        <p class="mt-3">لا توجد إعدادات</p>
                                        <a href="{{ route('admin.app-settings.create') }}" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-circle"></i> إضافة إعداد جديد
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($settings->hasPages())
        <div class="card-footer bg-white">
            {{ $settings->links() }}
        </div>
        @endif
    </div>

    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-globe text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2">{{ \App\Models\AppSetting::where('is_public', true)->count() }}</h4>
                    <p class="text-muted mb-0">إعدادات عامة</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="bi bi-toggle-on text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2">{{ \App\Models\AppSetting::where('setting_type', 'boolean')->count() }}</h4>
                    <p class="text-muted mb-0">إعدادات منطقية</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="bi bi-braces text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2">{{ \App\Models\AppSetting::where('setting_type', 'json')->count() }}</h4>
                    <p class="text-muted mb-0">إعدادات JSON</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-palette text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2">{{ \App\Models\AppSetting::where('setting_key', 'like', '%color%')->count() }}</h4>
                    <p class="text-muted mb-0">إعدادات الألوان</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    code {
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        background: #f8f9fa;
    }
    .table td, .table th {
        vertical-align: middle;
    }
</style>
@endpush
@endsection
