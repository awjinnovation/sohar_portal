<div class="mb-3">
    <label for="heritage_village_id" class="form-label">القرية التراثية <span class="text-danger">*</span></label>
    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
        <option value="">اختر القرية</option>
        @foreach($villages as $village)
            <option value="{{ $village->id }}" {{ old('heritage_village_id', $traditionalActivity->heritage_village_id ?? '') == $village->id ? 'selected' : '' }}>
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
            <label for="activity_name_ar" class="form-label">اسم النشاط (العربية) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('activity_name_ar') is-invalid @enderror" 
                   id="activity_name_ar" name="activity_name_ar" 
                   value="{{ old('activity_name_ar', $traditionalActivity->activity_name_ar ?? '') }}" required>
            @error('activity_name_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_ar" class="form-label">الوصف (العربية) <span class="text-danger">*</span></label>
            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                      id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar', $traditionalActivity->description_ar ?? '') }}</textarea>
            @error('description_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="cultural_significance_ar" class="form-label">الأهمية الثقافية (العربية)</label>
            <textarea class="form-control @error('cultural_significance_ar') is-invalid @enderror" 
                      id="cultural_significance_ar" name="cultural_significance_ar" rows="3">{{ old('cultural_significance_ar', $traditionalActivity->cultural_significance_ar ?? '') }}</textarea>
            @error('cultural_significance_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel">
        <div class="mb-3">
            <label for="activity_name_en" class="form-label">Activity Name (English) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('activity_name_en') is-invalid @enderror" 
                   id="activity_name_en" name="activity_name_en" 
                   value="{{ old('activity_name_en', $traditionalActivity->activity_name_en ?? '') }}" required>
            @error('activity_name_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_en" class="form-label">Description (English) <span class="text-danger">*</span></label>
            <textarea class="form-control @error('description_en') is-invalid @enderror" 
                      id="description_en" name="description_en" rows="4" required>{{ old('description_en', $traditionalActivity->description_en ?? '') }}</textarea>
            @error('description_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="cultural_significance_en" class="form-label">Cultural Significance (English)</label>
            <textarea class="form-control @error('cultural_significance_en') is-invalid @enderror" 
                      id="cultural_significance_en" name="cultural_significance_en" rows="3">{{ old('cultural_significance_en', $traditionalActivity->cultural_significance_en ?? '') }}</textarea>
            @error('cultural_significance_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="activity_type" class="form-label">نوع النشاط <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('activity_type') is-invalid @enderror" 
                   id="activity_type" name="activity_type" 
                   value="{{ old('activity_type', $traditionalActivity->activity_type ?? '') }}" required
                   placeholder="مثال: رقص تقليدي، ألعاب شعبية، حرف يدوية">
            @error('activity_type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="schedule" class="form-label">الجدول الزمني</label>
            <input type="text" class="form-control @error('schedule') is-invalid @enderror" 
                   id="schedule" name="schedule" 
                   value="{{ old('schedule', $traditionalActivity->schedule ?? '') }}"
                   placeholder="مثال: يومياً 5:00 - 7:00 مساءً">
            @error('schedule')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label for="duration_minutes" class="form-label">المدة (بالدقائق)</label>
            <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" 
                   id="duration_minutes" name="duration_minutes" 
                   value="{{ old('duration_minutes', $traditionalActivity->duration_minutes ?? '') }}" min="1">
            @error('duration_minutes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="max_participants" class="form-label">الحد الأقصى للمشاركين</label>
            <input type="number" class="form-control @error('max_participants') is-invalid @enderror" 
                   id="max_participants" name="max_participants" 
                   value="{{ old('max_participants', $traditionalActivity->max_participants ?? '') }}" min="1">
            @error('max_participants')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="age_restrictions" class="form-label">قيود العمر</label>
            <input type="text" class="form-control @error('age_restrictions') is-invalid @enderror" 
                   id="age_restrictions" name="age_restrictions" 
                   value="{{ old('age_restrictions', $traditionalActivity->age_restrictions ?? '') }}"
                   placeholder="مثال: 8 سنوات فما فوق">
            @error('age_restrictions')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="equipment_provided" name="equipment_provided" value="1" 
                       {{ old('equipment_provided', $traditionalActivity->equipment_provided ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="equipment_provided">
                    المعدات متوفرة
                </label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="booking_required" name="booking_required" value="1" 
                       {{ old('booking_required', $traditionalActivity->booking_required ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="booking_required">
                    يتطلب حجز مسبق
                </label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                       {{ old('is_active', $traditionalActivity->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    نشط
                </label>
            </div>
        </div>
    </div>
</div>
