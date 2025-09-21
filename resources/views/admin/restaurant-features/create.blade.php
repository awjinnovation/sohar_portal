@extends('layouts.admin')

@section('title', 'إضافة ميزة مطعم')
@section('page-title', 'إضافة ميزة مطعم جديدة')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">معلومات الميزة</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.restaurant-features.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="restaurant_id" class="form-label">المطعم <span class="text-danger">*</span></label>
                            <select name="restaurant_id" id="restaurant_id" class="form-control @error('restaurant_id') is-invalid @enderror" required>
                                <option value="">اختر المطعم</option>
                                @foreach($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}" {{ old('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
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
                                   value="{{ old('feature_ar') }}" required>
                            @error('feature_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="feature" class="form-label">الميزة (انجليزي) <span class="text-danger">*</span></label>
                            <input type="text" name="feature" id="feature"
                                   class="form-control @error('feature') is-invalid @enderror"
                                   value="{{ old('feature') }}" required>
                            @error('feature')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> حفظ
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
                    <h6 class="m-0 font-weight-bold text-primary">ملاحظات</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>ميزات المطعم</strong><br>
                        أضف الميزات المختلفة للمطاعم مثل:
                        <ul class="mt-2">
                            <li>واي فاي مجاني</li>
                            <li>مواقف سيارات</li>
                            <li>جلسات خارجية</li>
                            <li>توصيل منازل</li>
                            <li>قائمة نباتية</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection