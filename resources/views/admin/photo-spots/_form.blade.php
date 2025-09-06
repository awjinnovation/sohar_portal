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
