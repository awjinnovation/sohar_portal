@extends('layouts.admin')

@section('title', 'إضافة فئة جديدة')
@section('page-title', 'إضافة فئة جديدة')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">معلومات الفئة الجديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.categories.store') }}" method="POST">
                        @csrf
                        
                        <!-- Language Tabs -->
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#arabic-content">
                                    <i class="bi bi-translate"></i> العربية
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#english-content">
                                    <i class="bi bi-globe"></i> English
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Arabic Content -->
                            <div class="tab-pane fade show active" id="arabic-content">
                                <div class="mb-3">
                                    <label for="name_ar" class="form-label">اسم الفئة <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name_ar') is-invalid @enderror" 
                                           id="name_ar" name="name_ar" value="{{ old('name_ar') }}" required>
                                    @error('name_ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description_ar" class="form-label">الوصف <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                                              id="description_ar" name="description_ar" rows="3" required>{{ old('description_ar') }}</textarea>
                                    @error('description_ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- English Content -->
                            <div class="tab-pane fade" id="english-content">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Common Fields -->
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="icon_name" class="form-label">الأيقونة <span class="text-danger">*</span></label>
                                    <select class="form-select @error('icon_name') is-invalid @enderror" 
                                            id="icon_name" name="icon_name" required>
                                        <option value="">اختر أيقونة</option>
                                        <option value="music-note" {{ old('icon_name') == 'music-note' ? 'selected' : '' }}>🎵 موسيقى</option>
                                        <option value="calendar" {{ old('icon_name') == 'calendar' ? 'selected' : '' }}>📅 فعاليات</option>
                                        <option value="palette" {{ old('icon_name') == 'palette' ? 'selected' : '' }}>🎨 فنون</option>
                                        <option value="book" {{ old('icon_name') == 'book' ? 'selected' : '' }}>📚 ثقافة</option>
                                        <option value="trophy" {{ old('icon_name') == 'trophy' ? 'selected' : '' }}>🏆 رياضة</option>
                                        <option value="heart" {{ old('icon_name') == 'heart' ? 'selected' : '' }}>❤️ عائلي</option>
                                        <option value="shop" {{ old('icon_name') == 'shop' ? 'selected' : '' }}>🛍️ تسوق</option>
                                        <option value="cup-hot" {{ old('icon_name') == 'cup-hot' ? 'selected' : '' }}>☕ طعام</option>
                                    </select>
                                    @error('icon_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="color_value" class="form-label">اللون <span class="text-danger">*</span></label>
                                    <input type="color" class="form-control form-control-color @error('color_value') is-invalid @enderror" 
                                           id="color_picker" value="#1E3A8A">
                                    <input type="hidden" id="color_value" name="color_value" value="{{ old('color_value', '507744394') }}">
                                    @error('color_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_order" class="form-label">الترتيب</label>
                                    <input type="number" class="form-control @error('display_order') is-invalid @enderror" 
                                           id="display_order" name="display_order" value="{{ old('display_order', 0) }}" min="0">
                                    @error('display_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image_url" class="form-label">رابط الصورة</label>
                                    <input type="url" class="form-control @error('image_url') is-invalid @enderror" 
                                           id="image_url" name="image_url" value="{{ old('image_url') }}" 
                                           placeholder="https://example.com/image.jpg">
                                    @error('image_url')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    نشط
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">
                                رجوع
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i>
                                حفظ الفئة
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
// Convert color to integer
document.getElementById('color_picker').addEventListener('change', function(e) {
    const hex = e.target.value.replace('#', '');
    const int = parseInt(hex, 16);
    document.getElementById('color_value').value = int;
});

// Convert integer to color on load
window.addEventListener('DOMContentLoaded', function() {
    const colorValue = document.getElementById('color_value').value;
    if (colorValue) {
        const hex = '#' + parseInt(colorValue).toString(16).padStart(6, '0');
        document.getElementById('color_picker').value = hex;
    }
});
</script>
@endpush
@endsection