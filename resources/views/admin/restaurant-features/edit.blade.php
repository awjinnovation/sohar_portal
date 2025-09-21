@extends('layouts.admin')

@section('title', 'تعديل ميزة مطعم')
@section('page-title', 'تعديل ميزة مطعم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">معلومات الميزة</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.restaurant-features.update', $restaurantFeature) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="restaurant_id" class="form-label">المطعم <span class="text-danger">*</span></label>
                            <select name="restaurant_id" id="restaurant_id" class="form-control @error('restaurant_id') is-invalid @enderror" required>
                                <option value="">اختر المطعم</option>
                                @foreach($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}"
                                            {{ (old('restaurant_id', $restaurantFeature->restaurant_id) == $restaurant->id) ? 'selected' : '' }}>
                                        {{ $restaurant->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('restaurant_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="feature_ar" class="form-label">الميزة (عربي) <span class="text-danger">*</span></label>
                            <input type="text" name="feature_ar" id="feature_ar"
                                   class="form-control @error('feature_ar') is-invalid @enderror"
                                   value="{{ old('feature_ar', $restaurantFeature->feature_ar) }}" required>
                            @error('feature_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="feature" class="form-label">الميزة (انجليزي) <span class="text-danger">*</span></label>
                            <input type="text" name="feature" id="feature"
                                   class="form-control @error('feature') is-invalid @enderror"
                                   value="{{ old('feature', $restaurantFeature->feature) }}" required>
                            @error('feature')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> تحديث
                            </button>
                            <a href="{{ route('admin.restaurant-features.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">معلومات إضافية</h6>
                </div>
                <div class="card-body">
                    <p><strong>تاريخ الإنشاء:</strong><br>
                    {{ $restaurantFeature->created_at->format('Y-m-d H:i') }}</p>

                    <p><strong>آخر تحديث:</strong><br>
                    {{ $restaurantFeature->updated_at->format('Y-m-d H:i') }}</p>

                    <hr>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>ميزات المطعم</strong><br>
                        يمكنك تعديل معلومات الميزة الحالية للمطعم
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
