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
