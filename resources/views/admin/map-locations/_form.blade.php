<!-- Type Selection -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-tags-fill"></i> معلومات الموقع الأساسية</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">النوع <span class="text-danger">*</span></label>
                <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="">-- اختر النوع --</option>
                    <option value="entertainment" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'entertainment' ? 'selected' : '' }}>🎪 ترفيه</option>
                    <option value="food" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'food' ? 'selected' : '' }}>🍽️ طعام</option>
                    <option value="facilities" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'facilities' ? 'selected' : '' }}>🏢 مرافق</option>
                    <option value="parking" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'parking' ? 'selected' : '' }}>🅿️ مواقف</option>
                    <option value="emergency" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'emergency' ? 'selected' : '' }}>🚨 طوارئ</option>
                    <option value="first_aid" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'first_aid' ? 'selected' : '' }}>⚕️ إسعافات أولية</option>
                    <option value="restroom" {{ old('type', isset($mapLocation) ? $mapLocation->type : '') == 'restroom' ? 'selected' : '' }}>🚻 دورات مياه</option>
                </select>
                @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">الحالة</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', isset($mapLocation) ? $mapLocation->is_active : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">نشط</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Multilingual Content -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-translate"></i> المحتوى متعدد اللغات</h5>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="languageTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">
                    <i class="bi bi-flag-fill"></i> العربية
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">
                    <i class="bi bi-flag"></i> English
                </button>
            </li>
        </ul>

        <div class="tab-content" id="languageTabContent">
            <!-- Arabic Tab -->
            <div class="tab-pane fade show active" id="ar" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label fw-semibold">الاسم (عربي) <span class="text-danger">*</span></label>
                    <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror"
                           value="{{ old('name_ar', isset($mapLocation) ? $mapLocation->name_ar : '') }}"
                           placeholder="مثال: ساحة الفعاليات" required>
                    @error('name_ar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">الوصف (عربي) <span class="text-danger">*</span></label>
                    <textarea name="description_ar" rows="4" class="form-control @error('description_ar') is-invalid @enderror"
                              placeholder="اكتب وصفاً تفصيلياً للموقع..." required>{{ old('description_ar', isset($mapLocation) ? $mapLocation->description_ar : '') }}</textarea>
                    @error('description_ar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- English Tab -->
            <div class="tab-pane fade" id="en" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', isset($mapLocation) ? $mapLocation->name : '') }}"
                           placeholder="Example: Events Plaza" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description (English) <span class="text-danger">*</span></label>
                    <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror"
                              placeholder="Write a detailed description of the location..." required>{{ old('description', isset($mapLocation) ? $mapLocation->description : '') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Location Coordinates -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-geo-alt-fill"></i> الإحداثيات الجغرافية</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">خط العرض (Latitude) <span class="text-danger">*</span></label>
                <input type="number" step="0.00000001" name="latitude"
                       class="form-control @error('latitude') is-invalid @enderror"
                       value="{{ old('latitude', isset($mapLocation) ? $mapLocation->latitude : '') }}"
                       placeholder="24.3456789" required>
                <small class="text-muted">مثال: 24.3456789</small>
                @error('latitude')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">خط الطول (Longitude) <span class="text-danger">*</span></label>
                <input type="number" step="0.00000001" name="longitude"
                       class="form-control @error('longitude') is-invalid @enderror"
                       value="{{ old('longitude', isset($mapLocation) ? $mapLocation->longitude : '') }}"
                       placeholder="56.1234567" required>
                <small class="text-muted">مثال: 56.1234567</small>
                @error('longitude')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i>
            <strong>نصيحة:</strong> يمكنك الحصول على الإحداثيات من Google Maps بالنقر بزر الماوس الأيمن على الموقع واختيار الإحداثيات
        </div>
    </div>
</div>

<!-- Icon & Color -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-palette-fill"></i> المظهر (أيقونة ولون)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">الأيقونة <span class="text-danger">*</span></label>
                <select name="icon" class="form-select @error('icon') is-invalid @enderror" id="iconSelect" required>
                    <option value="">-- اختر الأيقونة --</option>
                    <option value="pin-map-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'pin-map-fill' ? 'selected' : '' }}>📍 دبوس موقع</option>
                    <option value="geo-alt-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'geo-alt-fill' ? 'selected' : '' }}>📌 موقع</option>
                    <option value="star-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'star-fill' ? 'selected' : '' }}>⭐ نجمة</option>
                    <option value="flag-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'flag-fill' ? 'selected' : '' }}>🚩 علم</option>
                    <option value="cup-hot-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'cup-hot-fill' ? 'selected' : '' }}>☕ مقهى</option>
                    <option value="shop" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'shop' ? 'selected' : '' }}>🏪 متجر</option>
                    <option value="building" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'building' ? 'selected' : '' }}>🏢 مبنى</option>
                    <option value="hospital" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'hospital' ? 'selected' : '' }}>🏥 مستشفى</option>
                    <option value="life-preserver" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'life-preserver' ? 'selected' : '' }}>🛟 إسعاف</option>
                    <option value="p-square-fill" {{ old('icon', isset($mapLocation) ? $mapLocation->icon : '') == 'p-square-fill' ? 'selected' : '' }}>🅿️ موقف</option>
                </select>
                @error('icon')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div id="iconPreview" class="mt-2 p-3 bg-light rounded text-center">
                    <i class="bi bi-{{ old('icon', isset($mapLocation) ? $mapLocation->icon : 'pin-map-fill') }}" style="font-size: 3rem;"></i>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">اللون (اختياري)</label>
                <input type="color" name="color_picker" id="colorPicker" class="form-control form-control-color"
                       value="{{ old('color_picker', (isset($mapLocation) && $mapLocation->color) ? '#' . dechex($mapLocation->color) : '#4A90E2') }}">
                <input type="hidden" name="color" id="colorHidden" value="{{ old('color', isset($mapLocation) ? $mapLocation->color : '') }}">
                <small class="text-muted d-block mt-1">اختر لون للعلامة على الخريطة</small>
                <div class="mt-2 p-3 bg-light rounded">
                    <strong>المعاينة:</strong>
                    <div id="colorPreview" class="d-inline-block ms-2 rounded"
                         style="width: 40px; height: 40px; background-color: {{ old('color_picker', (isset($mapLocation) && $mapLocation->color) ? '#' . dechex($mapLocation->color) : '#4A90E2') }};"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Icon preview
    const iconSelect = document.getElementById('iconSelect');
    const iconPreview = document.getElementById('iconPreview');

    iconSelect.addEventListener('change', function() {
        const icon = this.value;
        iconPreview.innerHTML = `<i class="bi bi-${icon}" style="font-size: 3rem;"></i>`;
    });

    // Color picker
    const colorPicker = document.getElementById('colorPicker');
    const colorHidden = document.getElementById('colorHidden');
    const colorPreview = document.getElementById('colorPreview');

    colorPicker.addEventListener('input', function() {
        const hex = this.value;
        const decimal = parseInt(hex.substring(1), 16);
        colorHidden.value = decimal;
        colorPreview.style.backgroundColor = hex;
    });
});
</script>
@endpush
