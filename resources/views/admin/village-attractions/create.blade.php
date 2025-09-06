@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة معلم جديد</h1>
        <a href="{{ route('admin.village-attractions.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.village-attractions.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}">{{ $village->name_ar }}</option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3">
                    <div class="tab-pane fade show active" id="ar">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم المعلم (العربية)</label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" required>
                            @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية)</label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="4" required></textarea>
                            @error('description_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en">
                        <div class="mb-3">
                            <label for="name_en" class="form-label">Name (English)</label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" required>
                            @error('name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="4" required></textarea>
                            @error('description_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="visiting_hours" class="form-label">ساعات الزيارة</label>
                    <input type="text" class="form-control" id="visiting_hours" name="visiting_hours">
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">نشط</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
