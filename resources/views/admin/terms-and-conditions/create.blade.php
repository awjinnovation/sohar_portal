@extends('layouts.admin')

@section('title', 'إضافة شروط وأحكام')
@section('page-title', 'إضافة شروط وأحكام جديدة')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.terms-and-conditions.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">اختر النوع</option>
                            <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>عامة (General)</option>
                            <option value="ticket" {{ old('type') == 'ticket' ? 'selected' : '' }}>التذاكر (Ticket)</option>
                            <option value="event" {{ old('type') == 'event' ? 'selected' : '' }}>الفعاليات (Event)</option>
                            <option value="restaurant" {{ old('type') == 'restaurant' ? 'selected' : '' }}>المطاعم (Restaurant)</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="language" class="form-label">اللغة <span class="text-danger">*</span></label>
                        <select name="language" id="language" class="form-select @error('language') is-invalid @enderror" required>
                            <option value="">اختر اللغة</option>
                            <option value="en" {{ old('language') == 'en' ? 'selected' : '' }}>English</option>
                            <option value="ar" {{ old('language') == 'ar' ? 'selected' : '' }}>العربية</option>
                        </select>
                        @error('language')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="title" class="form-label">العنوان <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="content" class="form-label">المحتوى <span class="text-danger">*</span></label>
                <textarea name="content" id="content" rows="15" class="form-control @error('content') is-invalid @enderror" required>{{ old('content') }}</textarea>
                @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">نشط</label>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('admin.terms-and-conditions.index') }}" class="btn btn-secondary">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://cdn.ckeditor.com/4.20.1/standard/ckeditor.js"></script>
<script>
    CKEDITOR.replace('content', {
        language: 'ar',
        height: 400
    });
</script>
@endpush
@endsection
