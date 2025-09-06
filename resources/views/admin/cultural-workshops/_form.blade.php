<div class="mb-3">
    <label for="heritage_village_id" class="form-label">القرية التراثية <span class="text-danger">*</span></label>
    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
        <option value="">اختر القرية</option>
        @foreach($villages as $village)
            <option value="{{ $village->id }}" {{ old('heritage_village_id', $culturalWorkshop->heritage_village_id ?? '') == $village->id ? 'selected' : '' }}>
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
            <label for="workshop_title_ar" class="form-label">عنوان الورشة (العربية) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('workshop_title_ar') is-invalid @enderror" 
                   id="workshop_title_ar" name="workshop_title_ar" 
                   value="{{ old('workshop_title_ar', $culturalWorkshop->workshop_title_ar ?? '') }}" required>
            @error('workshop_title_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_ar" class="form-label">الوصف (العربية) <span class="text-danger">*</span></label>
            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                      id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar', $culturalWorkshop->description_ar ?? '') }}</textarea>
            @error('description_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="instructor_bio_ar" class="form-label">نبذة عن المدرب (العربية)</label>
            <textarea class="form-control @error('instructor_bio_ar') is-invalid @enderror" 
                      id="instructor_bio_ar" name="instructor_bio_ar" rows="3">{{ old('instructor_bio_ar', $culturalWorkshop->instructor_bio_ar ?? '') }}</textarea>
            @error('instructor_bio_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel">
        <div class="mb-3">
            <label for="workshop_title_en" class="form-label">Workshop Title (English) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('workshop_title_en') is-invalid @enderror" 
                   id="workshop_title_en" name="workshop_title_en" 
                   value="{{ old('workshop_title_en', $culturalWorkshop->workshop_title_en ?? '') }}" required>
            @error('workshop_title_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_en" class="form-label">Description (English) <span class="text-danger">*</span></label>
            <textarea class="form-control @error('description_en') is-invalid @enderror" 
                      id="description_en" name="description_en" rows="4" required>{{ old('description_en', $culturalWorkshop->description_en ?? '') }}</textarea>
            @error('description_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="instructor_bio_en" class="form-label">Instructor Bio (English)</label>
            <textarea class="form-control @error('instructor_bio_en') is-invalid @enderror" 
                      id="instructor_bio_en" name="instructor_bio_en" rows="3">{{ old('instructor_bio_en', $culturalWorkshop->instructor_bio_en ?? '') }}</textarea>
            @error('instructor_bio_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="instructor_name" class="form-label">اسم المدرب</label>
            <input type="text" class="form-control @error('instructor_name') is-invalid @enderror" 
                   id="instructor_name" name="instructor_name" 
                   value="{{ old('instructor_name', $culturalWorkshop->instructor_name ?? '') }}">
            @error('instructor_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="schedule" class="form-label">الجدول الزمني</label>
            <input type="text" class="form-control @error('schedule') is-invalid @enderror" 
                   id="schedule" name="schedule" 
                   value="{{ old('schedule', $culturalWorkshop->schedule ?? '') }}"
                   placeholder="مثال: كل يوم سبت 4:00 مساءً">
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
                   value="{{ old('duration_minutes', $culturalWorkshop->duration_minutes ?? '') }}" min="1">
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
                   value="{{ old('max_participants', $culturalWorkshop->max_participants ?? '') }}" min="1">
            @error('max_participants')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="min_age" class="form-label">الحد الأدنى للعمر</label>
            <input type="number" class="form-control @error('min_age') is-invalid @enderror" 
                   id="min_age" name="min_age" 
                   value="{{ old('min_age', $culturalWorkshop->min_age ?? '') }}" min="1">
            @error('min_age')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label for="skill_level" class="form-label">مستوى المهارة</label>
            <select class="form-control @error('skill_level') is-invalid @enderror" id="skill_level" name="skill_level">
                <option value="">اختر المستوى</option>
                <option value="beginner" {{ old('skill_level', $culturalWorkshop->skill_level ?? '') == 'beginner' ? 'selected' : '' }}>مبتدئ</option>
                <option value="intermediate" {{ old('skill_level', $culturalWorkshop->skill_level ?? '') == 'intermediate' ? 'selected' : '' }}>متوسط</option>
                <option value="advanced" {{ old('skill_level', $culturalWorkshop->skill_level ?? '') == 'advanced' ? 'selected' : '' }}>متقدم</option>
                <option value="all" {{ old('skill_level', $culturalWorkshop->skill_level ?? '') == 'all' ? 'selected' : '' }}>جميع المستويات</option>
            </select>
            @error('skill_level')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="price" class="form-label">السعر (ر.ع)</label>
            <input type="number" class="form-control @error('price') is-invalid @enderror" 
                   id="price" name="price" 
                   value="{{ old('price', $culturalWorkshop->price ?? '') }}" min="0" step="0.001">
            @error('price')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="booking_link" class="form-label">رابط الحجز</label>
            <input type="url" class="form-control @error('booking_link') is-invalid @enderror" 
                   id="booking_link" name="booking_link" 
                   value="{{ old('booking_link', $culturalWorkshop->booking_link ?? '') }}">
            @error('booking_link')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="materials_included" name="materials_included" value="1" 
               {{ old('materials_included', $culturalWorkshop->materials_included ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="materials_included">
            المواد مشمولة
        </label>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
               {{ old('is_active', $culturalWorkshop->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            نشط
        </label>
    </div>
</div>
