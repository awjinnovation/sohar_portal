@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل الصورة</h1>
        <a href="{{ route('admin.village-images.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.village-images.update', $villageImage) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}" {{ old('heritage_village_id', $villageImage->heritage_village_id) == $village->id ? 'selected' : '' }}>
                                {{ $village->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">الصورة الحالية</label>
                    <div>
                        <img src="{{ $villageImage->image_url }}" alt="الصورة الحالية" style="max-width: 300px; height: auto;">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label">صورة جديدة (اختياري)</label>
                    <input type="file" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url" accept="image/*">
                    @error('image_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

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
                            <label for="caption_ar" class="form-label">الوصف (العربية)</label>
                            <input type="text" class="form-control @error('caption_ar') is-invalid @enderror" id="caption_ar" name="caption_ar" value="{{ old('caption_ar', $villageImage->caption_ar) }}">
                            @error('caption_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="caption_en" class="form-label">Caption (English)</label>
                            <input type="text" class="form-control @error('caption_en') is-invalid @enderror" id="caption_en" name="caption_en" value="{{ old('caption_en', $villageImage->caption_en) }}">
                            @error('caption_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="display_order" class="form-label">الترتيب</label>
                    <input type="number" class="form-control @error('display_order') is-invalid @enderror" id="display_order" name="display_order" value="{{ old('display_order', $villageImage->display_order) }}">
                    @error('display_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $villageImage->is_featured) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_featured">
                            صورة مميزة
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>
@endsection
