@extends('layouts.admin')

@section('title', 'إضافة مطعم جديد')
@section('page-title', 'إضافة مطعم جديد')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">معلومات المطعم الجديد</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.restaurants.store') }}" method="POST">
                        @csrf
                        
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

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="arabic-content">
                                <div class="mb-3">
    <label for="name_ar" class="form-label">اسم المطعم <span class="text-danger">*</span></label>
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
                                <div class="mb-3">
    <label for="cuisine_ar" class="form-label">نوع المطبخ <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('cuisine_ar') is-invalid @enderror" 
           id="cuisine_ar" name="cuisine_ar" value="{{ old('cuisine_ar') }}" required>
    @error('cuisine_ar')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
                                <div class="mb-3">
    <label for="location_ar" class="form-label">الموقع <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('location_ar') is-invalid @enderror" 
           id="location_ar" name="location_ar" value="{{ old('location_ar') }}" required>
    @error('location_ar')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
                            </div>

                            <div class="tab-pane fade" id="english-content">
                                <div class="mb-3">
    <label for="name" class="form-label">Restaurant Name <span class="text-danger">*</span></label>
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
                                <div class="mb-3">
    <label for="cuisine" class="form-label">Cuisine Type <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('cuisine') is-invalid @enderror" 
           id="cuisine" name="cuisine" value="{{ old('cuisine') }}" required>
    @error('cuisine')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
                                <div class="mb-3">
    <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('location') is-invalid @enderror" 
           id="location" name="location" value="{{ old('location') }}" required>
    @error('location')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
                            </div>
                        </div>

                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price_range" class="form-label">مستوى السعر <span class="text-danger">*</span></label>
                                    <select class="form-select @error('price_range') is-invalid @enderror" 
                                            id="price_range" name="price_range" required>
                                        <option value="">اختر المستوى</option>
                                        <option value="$">$ - اقتصادي</option>
                                        <option value="$$">$$ - متوسط</option>
                                        <option value="$$$">$$$ - مرتفع</option>
                                        <option value="$$$$">$$$$ - فاخر</option>
                                    </select>
                                    @error('price_range')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">الهاتف</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                           id="phone" name="phone" value="{{ old('phone') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="website" class="form-label">الموقع الإلكتروني</label>
                                    <input type="url" class="form-control @error('website') is-invalid @enderror" 
                                           id="website" name="website" value="{{ old('website') }}">
                                    @error('website')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="latitude" class="form-label">خط العرض</label>
                                    <input type="number" step="0.00000001" class="form-control @error('latitude') is-invalid @enderror" 
                                           id="latitude" name="latitude" value="{{ old('latitude') }}" min="-90" max="90">
                                    @error('latitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="longitude" class="form-label">خط الطول</label>
                                    <input type="number" step="0.00000001" class="form-control @error('longitude') is-invalid @enderror" 
                                           id="longitude" name="longitude" value="{{ old('longitude') }}" min="-180" max="180">
                                    @error('longitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="image_url" class="form-label">رابط الصورة</label>
                            <input type="url" class="form-control @error('image_url') is-invalid @enderror" 
                                   id="image_url" name="image_url" value="{{ old('image_url') }}">
                            @error('image_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        نشط
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                           value="1" {{ old('is_featured') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_featured">
                                        مميز
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_open" name="is_open" 
                                           value="1" {{ old('is_open', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_open">
                                        مفتوح الآن
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary">
                                رجوع
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i>
                                حفظ المطعم
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection