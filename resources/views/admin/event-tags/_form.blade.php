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
