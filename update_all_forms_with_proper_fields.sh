#!/bin/bash

echo "Updating all forms with proper input fields..."

# ========== EVENT TAGS FORM ==========
cat > resources/views/admin/event-tags/_form.blade.php << 'EOF'
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
            <label for="tag_name_ar" class="form-label">اسم الوسم (العربية) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('tag_name_ar') is-invalid @enderror" 
                   id="tag_name_ar" name="tag_name_ar" 
                   value="{{ old('tag_name_ar', $eventTag->tag_name_ar ?? '') }}" required>
            @error('tag_name_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel">
        <div class="mb-3">
            <label for="tag_name" class="form-label">Tag Name (English) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('tag_name') is-invalid @enderror" 
                   id="tag_name" name="tag_name" 
                   value="{{ old('tag_name', $eventTag->tag_name ?? '') }}" required>
            @error('tag_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="color_hex" class="form-label">لون الوسم</label>
    <input type="color" class="form-control @error('color_hex') is-invalid @enderror" 
           id="color_hex" name="color_hex" 
           value="{{ old('color_hex', $eventTag->color_hex ?? '#007bff') }}" style="max-width: 200px;">
    @error('color_hex')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
               {{ old('is_active', $eventTag->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            نشط
        </label>
    </div>
</div>
EOF

# ========== TICKETS FORM ==========
cat > resources/views/admin/tickets/_form.blade.php << 'EOF'
<div class="mb-3">
    <label for="event_id" class="form-label">الفعالية <span class="text-danger">*</span></label>
    <select class="form-control @error('event_id') is-invalid @enderror" id="event_id" name="event_id" required>
        <option value="">اختر الفعالية</option>
        @foreach($events as $event)
            <option value="{{ $event->id }}" {{ old('event_id', $ticket->event_id ?? '') == $event->id ? 'selected' : '' }}>
                {{ $event->title_ar }}
            </option>
        @endforeach
    </select>
    @error('event_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="ticket_type" class="form-label">نوع التذكرة <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('ticket_type') is-invalid @enderror" 
           id="ticket_type" name="ticket_type" 
           value="{{ old('ticket_type', $ticket->ticket_type ?? '') }}" required
           placeholder="مثال: VIP، عادي، طلاب">
    @error('ticket_type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">وصف التذكرة</label>
    <textarea class="form-control @error('description') is-invalid @enderror" 
              id="description" name="description" rows="3">{{ old('description', $ticket->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="total_quantity" class="form-label">الكمية الإجمالية <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('total_quantity') is-invalid @enderror" 
                   id="total_quantity" name="total_quantity" 
                   value="{{ old('total_quantity', $ticket->total_quantity ?? '') }}" required min="1">
            @error('total_quantity')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="available_quantity" class="form-label">الكمية المتاحة <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('available_quantity') is-invalid @enderror" 
                   id="available_quantity" name="available_quantity" 
                   value="{{ old('available_quantity', $ticket->available_quantity ?? '') }}" required min="0">
            @error('available_quantity')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="min_purchase" class="form-label">الحد الأدنى للشراء</label>
            <input type="number" class="form-control @error('min_purchase') is-invalid @enderror" 
                   id="min_purchase" name="min_purchase" 
                   value="{{ old('min_purchase', $ticket->min_purchase ?? 1) }}" min="1">
            @error('min_purchase')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="max_purchase" class="form-label">الحد الأقصى للشراء</label>
            <input type="number" class="form-control @error('max_purchase') is-invalid @enderror" 
                   id="max_purchase" name="max_purchase" 
                   value="{{ old('max_purchase', $ticket->max_purchase ?? 10) }}" min="1">
            @error('max_purchase')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="sale_start_date" class="form-label">تاريخ بداية البيع</label>
            <input type="datetime-local" class="form-control @error('sale_start_date') is-invalid @enderror" 
                   id="sale_start_date" name="sale_start_date" 
                   value="{{ old('sale_start_date', isset($ticket) && $ticket->sale_start_date ? $ticket->sale_start_date->format('Y-m-d\TH:i') : '') }}">
            @error('sale_start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="sale_end_date" class="form-label">تاريخ نهاية البيع</label>
            <input type="datetime-local" class="form-control @error('sale_end_date') is-invalid @enderror" 
                   id="sale_end_date" name="sale_end_date" 
                   value="{{ old('sale_end_date', isset($ticket) && $ticket->sale_end_date ? $ticket->sale_end_date->format('Y-m-d\TH:i') : '') }}">
            @error('sale_end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
               {{ old('is_active', $ticket->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            نشط
        </label>
    </div>
</div>
EOF

# ========== CULTURAL WORKSHOPS FORM ==========
cat > resources/views/admin/cultural-workshops/_form.blade.php << 'EOF'
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
EOF

# ========== PHOTO SPOTS FORM ==========
cat > resources/views/admin/photo-spots/_form.blade.php << 'EOF'
<div class="mb-3">
    <label for="heritage_village_id" class="form-label">القرية التراثية <span class="text-danger">*</span></label>
    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
        <option value="">اختر القرية</option>
        @foreach($villages as $village)
            <option value="{{ $village->id }}" {{ old('heritage_village_id', $photoSpot->heritage_village_id ?? '') == $village->id ? 'selected' : '' }}>
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
            <label for="spot_name_ar" class="form-label">اسم الموقع (العربية) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('spot_name_ar') is-invalid @enderror" 
                   id="spot_name_ar" name="spot_name_ar" 
                   value="{{ old('spot_name_ar', $photoSpot->spot_name_ar ?? '') }}" required>
            @error('spot_name_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_ar" class="form-label">الوصف (العربية)</label>
            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                      id="description_ar" name="description_ar" rows="3">{{ old('description_ar', $photoSpot->description_ar ?? '') }}</textarea>
            @error('description_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="location_details_ar" class="form-label">تفاصيل الموقع (العربية)</label>
            <textarea class="form-control @error('location_details_ar') is-invalid @enderror" 
                      id="location_details_ar" name="location_details_ar" rows="2">{{ old('location_details_ar', $photoSpot->location_details_ar ?? '') }}</textarea>
            @error('location_details_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="photography_tips_ar" class="form-label">نصائح التصوير (العربية)</label>
            <textarea class="form-control @error('photography_tips_ar') is-invalid @enderror" 
                      id="photography_tips_ar" name="photography_tips_ar" rows="3">{{ old('photography_tips_ar', $photoSpot->photography_tips_ar ?? '') }}</textarea>
            @error('photography_tips_ar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel">
        <div class="mb-3">
            <label for="spot_name_en" class="form-label">Spot Name (English) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('spot_name_en') is-invalid @enderror" 
                   id="spot_name_en" name="spot_name_en" 
                   value="{{ old('spot_name_en', $photoSpot->spot_name_en ?? '') }}" required>
            @error('spot_name_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="description_en" class="form-label">Description (English)</label>
            <textarea class="form-control @error('description_en') is-invalid @enderror" 
                      id="description_en" name="description_en" rows="3">{{ old('description_en', $photoSpot->description_en ?? '') }}</textarea>
            @error('description_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="location_details_en" class="form-label">Location Details (English)</label>
            <textarea class="form-control @error('location_details_en') is-invalid @enderror" 
                      id="location_details_en" name="location_details_en" rows="2">{{ old('location_details_en', $photoSpot->location_details_en ?? '') }}</textarea>
            @error('location_details_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="photography_tips_en" class="form-label">Photography Tips (English)</label>
            <textarea class="form-control @error('photography_tips_en') is-invalid @enderror" 
                      id="photography_tips_en" name="photography_tips_en" rows="3">{{ old('photography_tips_en', $photoSpot->photography_tips_en ?? '') }}</textarea>
            @error('photography_tips_en')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="best_time_for_photos" class="form-label">أفضل وقت للتصوير</label>
            <input type="text" class="form-control @error('best_time_for_photos') is-invalid @enderror" 
                   id="best_time_for_photos" name="best_time_for_photos" 
                   value="{{ old('best_time_for_photos', $photoSpot->best_time_for_photos ?? '') }}"
                   placeholder="مثال: وقت الغروب، الصباح الباكر">
            @error('best_time_for_photos')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="hashtags" class="form-label">الهاشتاقات</label>
            <input type="text" class="form-control @error('hashtags') is-invalid @enderror" 
                   id="hashtags" name="hashtags" 
                   value="{{ old('hashtags', $photoSpot->hashtags ?? '') }}"
                   placeholder="#مهرجان_صحار #قرية_تراثية">
            @error('hashtags')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_accessible" name="is_accessible" value="1" 
                       {{ old('is_accessible', $photoSpot->is_accessible ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_accessible">
                    سهل الوصول
                </label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="has_backdrop" name="has_backdrop" value="1" 
                       {{ old('has_backdrop', $photoSpot->has_backdrop ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="has_backdrop">
                    يحتوي على خلفية
                </label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular" value="1" 
                       {{ old('is_popular', $photoSpot->is_popular ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_popular">
                    موقع شائع
                </label>
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
               {{ old('is_active', $photoSpot->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            نشط
        </label>
    </div>
</div>
EOF

# ========== TRADITIONAL ACTIVITIES FORM ==========
cat > resources/views/admin/traditional-activities/_form.blade.php << 'EOF'
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
EOF

echo "All form templates updated with proper input fields!"