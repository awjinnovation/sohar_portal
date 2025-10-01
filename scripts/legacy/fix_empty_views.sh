#!/bin/bash

echo "Fixing empty views for announcements, heritage-villages, emergency-contacts, and map-locations..."

# ========== ANNOUNCEMENTS VIEWS ==========

# Create view
cat > resources/views/admin/announcements/create.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة إعلان جديد</h1>
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.announcements.store') }}" method="POST">
                @csrf

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3" id="myTabContent">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <div class="mb-3">
                            <label for="title_ar" class="form-label">العنوان (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title_ar') is-invalid @enderror" 
                                   id="title_ar" name="title_ar" value="{{ old('title_ar') }}" required>
                            @error('title_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="content_ar" class="form-label">المحتوى (العربية) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('content_ar') is-invalid @enderror" 
                                      id="content_ar" name="content_ar" rows="5" required>{{ old('content_ar') }}</textarea>
                            @error('content_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      id="content" name="content" rows="5" required>{{ old('content') }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="info" {{ old('type') == 'info' ? 'selected' : '' }}>معلومات</option>
                                <option value="warning" {{ old('type') == 'warning' ? 'selected' : '' }}>تحذير</option>
                                <option value="alert" {{ old('type') == 'alert' ? 'selected' : '' }}>تنبيه</option>
                                <option value="success" {{ old('type') == 'success' ? 'selected' : '' }}>نجاح</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="priority" class="form-label">الأولوية</label>
                            <input type="number" class="form-control @error('priority') is-invalid @enderror" 
                                   id="priority" name="priority" value="{{ old('priority', 0) }}" min="0">
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="target_audience" class="form-label">الجمهور المستهدف</label>
                            <select class="form-control @error('target_audience') is-invalid @enderror" id="target_audience" name="target_audience">
                                <option value="all" {{ old('target_audience') == 'all' ? 'selected' : '' }}>الجميع</option>
                                <option value="visitors" {{ old('target_audience') == 'visitors' ? 'selected' : '' }}>الزوار</option>
                                <option value="vendors" {{ old('target_audience') == 'vendors' ? 'selected' : '' }}>البائعون</option>
                                <option value="staff" {{ old('target_audience') == 'staff' ? 'selected' : '' }}>الموظفون</option>
                            </select>
                            @error('target_audience')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_datetime" class="form-label">تاريخ ووقت البداية <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control @error('start_datetime') is-invalid @enderror" 
                                   id="start_datetime" name="start_datetime" value="{{ old('start_datetime') }}" required>
                            @error('start_datetime')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_datetime" class="form-label">تاريخ ووقت النهاية</label>
                            <input type="datetime-local" class="form-control @error('end_datetime') is-invalid @enderror" 
                                   id="end_datetime" name="end_datetime" value="{{ old('end_datetime') }}">
                            @error('end_datetime')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" value="1" 
                               {{ old('is_pinned') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_pinned">
                            تثبيت الإعلان
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Edit view
cat > resources/views/admin/announcements/edit.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل الإعلان</h1>
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST">
                @csrf
                @method('PUT')

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3" id="myTabContent">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <div class="mb-3">
                            <label for="title_ar" class="form-label">العنوان (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title_ar') is-invalid @enderror" 
                                   id="title_ar" name="title_ar" value="{{ old('title_ar', $announcement->title_ar) }}" required>
                            @error('title_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="content_ar" class="form-label">المحتوى (العربية) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('content_ar') is-invalid @enderror" 
                                      id="content_ar" name="content_ar" rows="5" required>{{ old('content_ar', $announcement->content_ar) }}</textarea>
                            @error('content_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title', $announcement->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      id="content" name="content" rows="5" required>{{ old('content', $announcement->content) }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="info" {{ old('type', $announcement->type) == 'info' ? 'selected' : '' }}>معلومات</option>
                                <option value="warning" {{ old('type', $announcement->type) == 'warning' ? 'selected' : '' }}>تحذير</option>
                                <option value="alert" {{ old('type', $announcement->type) == 'alert' ? 'selected' : '' }}>تنبيه</option>
                                <option value="success" {{ old('type', $announcement->type) == 'success' ? 'selected' : '' }}>نجاح</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="priority" class="form-label">الأولوية</label>
                            <input type="number" class="form-control @error('priority') is-invalid @enderror" 
                                   id="priority" name="priority" value="{{ old('priority', $announcement->priority) }}" min="0">
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="target_audience" class="form-label">الجمهور المستهدف</label>
                            <select class="form-control @error('target_audience') is-invalid @enderror" id="target_audience" name="target_audience">
                                <option value="all" {{ old('target_audience', $announcement->target_audience) == 'all' ? 'selected' : '' }}>الجميع</option>
                                <option value="visitors" {{ old('target_audience', $announcement->target_audience) == 'visitors' ? 'selected' : '' }}>الزوار</option>
                                <option value="vendors" {{ old('target_audience', $announcement->target_audience) == 'vendors' ? 'selected' : '' }}>البائعون</option>
                                <option value="staff" {{ old('target_audience', $announcement->target_audience) == 'staff' ? 'selected' : '' }}>الموظفون</option>
                            </select>
                            @error('target_audience')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_datetime" class="form-label">تاريخ ووقت البداية <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control @error('start_datetime') is-invalid @enderror" 
                                   id="start_datetime" name="start_datetime" 
                                   value="{{ old('start_datetime', $announcement->start_datetime ? $announcement->start_datetime->format('Y-m-d\TH:i') : '') }}" required>
                            @error('start_datetime')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_datetime" class="form-label">تاريخ ووقت النهاية</label>
                            <input type="datetime-local" class="form-control @error('end_datetime') is-invalid @enderror" 
                                   id="end_datetime" name="end_datetime" 
                                   value="{{ old('end_datetime', $announcement->end_datetime ? $announcement->end_datetime->format('Y-m-d\TH:i') : '') }}">
                            @error('end_datetime')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" value="1" 
                               {{ old('is_pinned', $announcement->is_pinned) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_pinned">
                            تثبيت الإعلان
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', $announcement->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Show view
cat > resources/views/admin/announcements/show.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل الإعلان</h1>
        <div>
            <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="200">النوع:</th>
                            <td>
                                @switch($announcement->type)
                                    @case('info')
                                        <span class="badge bg-info">معلومات</span>
                                        @break
                                    @case('warning')
                                        <span class="badge bg-warning">تحذير</span>
                                        @break
                                    @case('alert')
                                        <span class="badge bg-danger">تنبيه</span>
                                        @break
                                    @case('success')
                                        <span class="badge bg-success">نجاح</span>
                                        @break
                                    @default
                                        {{ $announcement->type }}
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>الأولوية:</th>
                            <td>{{ $announcement->priority ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>الجمهور المستهدف:</th>
                            <td>{{ $announcement->target_audience ?? 'الجميع' }}</td>
                        </tr>
                        <tr>
                            <th>مثبت:</th>
                            <td>
                                @if($announcement->is_pinned)
                                    <span class="badge bg-primary">مثبت</span>
                                @else
                                    <span class="badge bg-secondary">غير مثبت</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($announcement->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ البداية:</th>
                            <td>{{ $announcement->start_datetime ? $announcement->start_datetime->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <th>تاريخ النهاية:</th>
                            <td>{{ $announcement->end_datetime ? $announcement->end_datetime->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">العنوان</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $announcement->title_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $announcement->title }}</p>
                    </div>
                    
                    <h5 class="font-weight-bold mt-4">المحتوى</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $announcement->content_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $announcement->content }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOF

# ========== HERITAGE VILLAGES VIEWS ==========

# Create view
cat > resources/views/admin/heritage-villages/create.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة قرية تراثية جديدة</h1>
        <a href="{{ route('admin.heritage-villages.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.heritage-villages.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3" id="myTabContent">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم القرية (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" 
                                   id="name_ar" name="name_ar" value="{{ old('name_ar') }}" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                                      id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar') }}</textarea>
                            @error('description_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_en" class="form-label">Village Name (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" 
                                   id="name_en" name="name_en" value="{{ old('name_en') }}" required>
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description_en') is-invalid @enderror" 
                                      id="description_en" name="description_en" rows="4" required>{{ old('description_en') }}</textarea>
                            @error('description_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="maritime" {{ old('type') == 'maritime' ? 'selected' : '' }}>بحري</option>
                                <option value="agricultural" {{ old('type') == 'agricultural' ? 'selected' : '' }}>زراعي</option>
                                <option value="desert" {{ old('type') == 'desert' ? 'selected' : '' }}>صحراوي</option>
                                <option value="mountain" {{ old('type') == 'mountain' ? 'selected' : '' }}>جبلي</option>
                                <option value="urban" {{ old('type') == 'urban' ? 'selected' : '' }}>حضري</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="opening_hours" class="form-label">ساعات العمل</label>
                            <input type="text" class="form-control @error('opening_hours') is-invalid @enderror" 
                                   id="opening_hours" name="opening_hours" value="{{ old('opening_hours') }}"
                                   placeholder="مثال: 9:00 صباحاً - 10:00 مساءً">
                            @error('opening_hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="cover_image" class="form-label">صورة الغلاف</label>
                    <input type="file" class="form-control @error('cover_image') is-invalid @enderror" 
                           id="cover_image" name="cover_image" accept="image/*">
                    @error('cover_image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Edit view
cat > resources/views/admin/heritage-villages/edit.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل القرية التراثية</h1>
        <a href="{{ route('admin.heritage-villages.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.heritage-villages.update', $heritageVillage) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3" id="myTabContent">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم القرية (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" 
                                   id="name_ar" name="name_ar" value="{{ old('name_ar', $heritageVillage->name_ar) }}" required>
                            @error('name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                                      id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar', $heritageVillage->description_ar) }}</textarea>
                            @error('description_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_en" class="form-label">Village Name (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" 
                                   id="name_en" name="name_en" value="{{ old('name_en', $heritageVillage->name_en) }}" required>
                            @error('name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description_en') is-invalid @enderror" 
                                      id="description_en" name="description_en" rows="4" required>{{ old('description_en', $heritageVillage->description_en) }}</textarea>
                            @error('description_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="maritime" {{ old('type', $heritageVillage->type) == 'maritime' ? 'selected' : '' }}>بحري</option>
                                <option value="agricultural" {{ old('type', $heritageVillage->type) == 'agricultural' ? 'selected' : '' }}>زراعي</option>
                                <option value="desert" {{ old('type', $heritageVillage->type) == 'desert' ? 'selected' : '' }}>صحراوي</option>
                                <option value="mountain" {{ old('type', $heritageVillage->type) == 'mountain' ? 'selected' : '' }}>جبلي</option>
                                <option value="urban" {{ old('type', $heritageVillage->type) == 'urban' ? 'selected' : '' }}>حضري</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="opening_hours" class="form-label">ساعات العمل</label>
                            <input type="text" class="form-control @error('opening_hours') is-invalid @enderror" 
                                   id="opening_hours" name="opening_hours" value="{{ old('opening_hours', $heritageVillage->opening_hours) }}"
                                   placeholder="مثال: 9:00 صباحاً - 10:00 مساءً">
                            @error('opening_hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                @if($heritageVillage->cover_image)
                <div class="mb-3">
                    <label class="form-label">الصورة الحالية</label>
                    <div>
                        <img src="{{ $heritageVillage->cover_image }}" alt="صورة الغلاف" style="max-width: 300px; height: auto;">
                    </div>
                </div>
                @endif

                <div class="mb-3">
                    <label for="cover_image" class="form-label">صورة غلاف جديدة (اختياري)</label>
                    <input type="file" class="form-control @error('cover_image') is-invalid @enderror" 
                           id="cover_image" name="cover_image" accept="image/*">
                    @error('cover_image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', $heritageVillage->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Show view
cat > resources/views/admin/heritage-villages/show.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل القرية التراثية</h1>
        <div>
            <a href="{{ route('admin.heritage-villages.edit', $heritageVillage) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.heritage-villages.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @if($heritageVillage->cover_image)
                    <img src="{{ $heritageVillage->cover_image }}" alt="صورة القرية" class="img-fluid mb-3">
                    @endif
                    
                    <table class="table">
                        <tr>
                            <th width="200">النوع:</th>
                            <td>
                                @switch($heritageVillage->type)
                                    @case('maritime')
                                        <span class="badge bg-primary">بحري</span>
                                        @break
                                    @case('agricultural')
                                        <span class="badge bg-success">زراعي</span>
                                        @break
                                    @case('desert')
                                        <span class="badge bg-warning">صحراوي</span>
                                        @break
                                    @case('mountain')
                                        <span class="badge bg-info">جبلي</span>
                                        @break
                                    @case('urban')
                                        <span class="badge bg-secondary">حضري</span>
                                        @break
                                    @default
                                        {{ $heritageVillage->type }}
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>ساعات العمل:</th>
                            <td>{{ $heritageVillage->opening_hours ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($heritageVillage->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ الإنشاء:</th>
                            <td>{{ $heritageVillage->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">اسم القرية</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $heritageVillage->name_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $heritageVillage->name_en }}</p>
                    </div>
                    
                    <h5 class="font-weight-bold mt-4">الوصف</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $heritageVillage->description_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $heritageVillage->description_en }}</p>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="font-weight-bold mb-3">المحتوى المرتبط</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->images()->count() }}</h3>
                            <p>صور</p>
                            <a href="{{ route('admin.village-images.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->attractions()->count() }}</h3>
                            <p>معالم</p>
                            <a href="{{ route('admin.village-attractions.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->craftDemonstrations()->count() }}</h3>
                            <p>عروض حرفية</p>
                            <a href="{{ route('admin.craft-demonstrations.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->workshops()->count() }}</h3>
                            <p>ورش ثقافية</p>
                            <a href="{{ route('admin.cultural-workshops.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOF

# ========== EMERGENCY CONTACTS VIEWS - Continue in next part...
echo "Views for announcements and heritage-villages created!"

# Continue with emergency-contacts and map-locations...