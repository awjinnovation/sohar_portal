@extends('layouts.admin')

@section('title', 'تعديل سياسة الخصوصية')
@section('page-title', 'تعديل سياسة الخصوصية')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.privacy-policies.update', $privacyPolicy) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="language" class="form-label">اللغة <span class="text-danger">*</span></label>
                        <select name="language" id="language" class="form-select @error('language') is-invalid @enderror" required>
                            <option value="">اختر اللغة</option>
                            <option value="en" {{ old('language', $privacyPolicy->language) == 'en' ? 'selected' : '' }}>English</option>
                            <option value="ar" {{ old('language', $privacyPolicy->language) == 'ar' ? 'selected' : '' }}>العربية</option>
                        </select>
                        @error('language')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="title" class="form-label">العنوان <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $privacyPolicy->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="content" class="form-label">المحتوى <span class="text-danger">*</span></label>
                <textarea name="content" id="content" rows="15" class="form-control @error('content') is-invalid @enderror" required>{{ old('content', $privacyPolicy->content) }}</textarea>
                @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $privacyPolicy->is_active) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">نشط</label>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('admin.privacy-policies.index') }}" class="btn btn-secondary">إلغاء</a>
                <button type="submit" class="btn btn-primary">تحديث</button>
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
