@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة عرض حرفي جديد</h1>
        <a href="{{ route('admin.craft-demonstrations.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.craft-demonstrations.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}" {{ old('heritage_village_id') == $village->id ? 'selected' : '' }}>
                                {{ $village->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
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
                            <label for="craft_name_ar" class="form-label">اسم الحرفة (العربية)</label>
                            <input type="text" class="form-control @error('craft_name_ar') is-invalid @enderror" id="craft_name_ar" name="craft_name_ar" value="{{ old('craft_name_ar') }}" required>
                            @error('craft_name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية)</label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar') }}</textarea>
                            @error('description_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="materials_used_ar" class="form-label">المواد المستخدمة (العربية)</label>
                            <textarea class="form-control @error('materials_used_ar') is-invalid @enderror" id="materials_used_ar" name="materials_used_ar" rows="3">{{ old('materials_used_ar') }}</textarea>
                            @error('materials_used_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="historical_significance_ar" class="form-label">الأهمية التاريخية (العربية)</label>
                            <textarea class="form-control @error('historical_significance_ar') is-invalid @enderror" id="historical_significance_ar" name="historical_significance_ar" rows="3">{{ old('historical_significance_ar') }}</textarea>
                            @error('historical_significance_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="craft_name_en" class="form-label">Craft Name (English)</label>
                            <input type="text" class="form-control @error('craft_name_en') is-invalid @enderror" id="craft_name_en" name="craft_name_en" value="{{ old('craft_name_en') }}" required>
                            @error('craft_name_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="4" required>{{ old('description_en') }}</textarea>
                            @error('description_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="materials_used_en" class="form-label">Materials Used (English)</label>
                            <textarea class="form-control @error('materials_used_en') is-invalid @enderror" id="materials_used_en" name="materials_used_en" rows="3">{{ old('materials_used_en') }}</textarea>
                            @error('materials_used_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="historical_significance_en" class="form-label">Historical Significance (English)</label>
                            <textarea class="form-control @error('historical_significance_en') is-invalid @enderror" id="historical_significance_en" name="historical_significance_en" rows="3">{{ old('historical_significance_en') }}</textarea>
                            @error('historical_significance_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="artisan_name" class="form-label">اسم الحرفي</label>
                            <input type="text" class="form-control @error('artisan_name') is-invalid @enderror" id="artisan_name" name="artisan_name" value="{{ old('artisan_name') }}">
                            @error('artisan_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="demonstration_times" class="form-label">أوقات العرض</label>
                            <input type="text" class="form-control @error('demonstration_times') is-invalid @enderror" id="demonstration_times" name="demonstration_times" value="{{ old('demonstration_times') }}">
                            @error('demonstration_times')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="duration_minutes" class="form-label">المدة (بالدقائق)</label>
                            <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}">
                            @error('duration_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="can_try_hands_on" name="can_try_hands_on" value="1" {{ old('can_try_hands_on') ? 'checked' : '' }}>
                                <label class="form-check-label" for="can_try_hands_on">
                                    يمكن للزوار التجربة العملية
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
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
