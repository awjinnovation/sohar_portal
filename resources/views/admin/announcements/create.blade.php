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
