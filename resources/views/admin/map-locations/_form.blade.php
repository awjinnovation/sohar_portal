@php
    $loc = $mapLocation ?? null;
    $locTypes = \App\Models\MapLocation::getTypes();
    $typeOptions = collect($locTypes)->mapWithKeys(fn ($t, $k) => [$k => app()->getLocale() === 'en' ? $t['en'] : $t['ar'].' ('.$t['en'].')'])->all();
    $iconOptions = [
        'pin-map-fill' => 'دبوس موقع', 'geo-alt-fill' => 'موقع', 'star-fill' => 'نجمة', 'flag-fill' => 'علم',
        'cup-hot-fill' => 'مقهى', 'shop' => 'متجر', 'building' => 'مبنى', 'hospital' => 'مستشفى',
        'life-preserver' => 'إسعاف', 'p-square-fill' => 'موقف',
    ];
    $iconValue = old('icon', $loc?->icon ?? '');
    $previewIcon = isset($iconOptions[$iconValue]) ? $iconValue : 'pin-map-fill';
    $storedHex = $loc?->color ? sprintf('#%06X', ((int) $loc->color) & 0xFFFFFF) : '#4A90E2';
    $pickerHex = old('color_picker', $storedHex);
    $unknownType = $loc && $loc->type && ! isset($locTypes[$loc->type]);
@endphp
<div class="row g-4">
    <div class="col-xl-8">
        <x-admin.card title="المحتوى" icon="translate" subtitle="يظهر الاسم والوصف بحسب لغة الزائر">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                </li>
            </ul>
            <div class="tab-content border border-top-0 p-3">
                <div class="tab-pane fade show active" id="ar" role="tabpanel">
                    <x-admin.form-field name="name_ar" label="الاسم بالعربية" placeholder="مثل ساحة الفعاليات" :value="old('name_ar', $loc?->name_ar ?? '')" required />
                    <x-admin.form-field name="description_ar" label="الوصف بالعربية" type="textarea" rows="4" placeholder="اكتب وصفًا تفصيليًا للموقع" :value="old('description_ar', $loc?->description_ar ?? '')" required />
                </div>
                <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                    <x-admin.form-field name="name" label="Name (English)" placeholder="Example: Events Plaza" :value="old('name', $loc?->name ?? '')" required />
                    <x-admin.form-field name="description" label="Description (English)" type="textarea" rows="4" placeholder="Write a detailed description of the location..." :value="old('description', $loc?->description ?? '')" required />
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="الإحداثيات الجغرافية" icon="geo-alt" subtitle="يمكنك نسخ الإحداثيات من خرائط Google بالنقر بزر الماوس الأيمن على الموقع">
            <div class="row">
                <x-admin.form-field col="col-md-6" name="latitude" label="خط العرض" type="number" step="0.00000001" dir="ltr"
                    placeholder="24.3456789" help="مثال: 24.3456789" :value="old('latitude', $loc?->latitude !== null ? (float) $loc->latitude : '')" required />
                <x-admin.form-field col="col-md-6" name="longitude" label="خط الطول" type="number" step="0.00000001" dir="ltr"
                    placeholder="56.1234567" help="مثال: 56.1234567" :value="old('longitude', $loc?->longitude !== null ? (float) $loc->longitude : '')" required />
            </div>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="الإعدادات" icon="sliders">
            <x-admin.form-field name="type" label="النوع" type="select" placeholder="-- اختر النوع --" :options="$typeOptions"
                :value="old('type', $loc?->type ?? '')" required
                :help="$unknownType ? __('النوع الحالي (:type) غير مدعوم، اختر نوعًا من القائمة.', ['type' => $loc->type]) : null" />
            <x-admin.toggle name="is_active" id="is_active" label="نشط" description="يظهر على الخريطة في التطبيق" :checked="old('is_active', $loc?->is_active ?? true)" />
        </x-admin.card>

        <x-admin.card title="المظهر" icon="palette" subtitle="أيقونة ولون العلامة على الخريطة">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-grow-1" style="min-width: 0">
                    <x-admin.form-field name="icon" id="iconSelect" label="الأيقونة" type="select" placeholder="-- اختر الأيقونة --" :options="$iconOptions" :value="$iconValue" required
                        :help="$loc && $loc->icon && ! isset($iconOptions[$loc->icon]) ? __('الأيقونة الحالية (:icon) غير مدعومة، اختر أيقونة.', ['icon' => $loc->icon]) : null" />
                </div>
                <div id="colorPreview" class="pin-preview" style="--pin: {{ $pickerHex }}" title="{{ __('معاينة العلامة') }}" aria-hidden="true">
                    <span id="iconPreview"><i class="bi bi-{{ $previewIcon }}"></i></span>
                </div>
            </div>

            <div class="field mb-0">
                <label for="colorPicker" class="form-label">{{ __('اللون (اختياري)') }}</label>
                <div class="d-flex align-items-center gap-3">
                    <input type="color" name="color_picker" id="colorPicker" class="form-control form-control-color" value="{{ $pickerHex }}" aria-describedby="colorHelp">
                    <input type="hidden" name="color" id="colorHidden" value="{{ old('color', $loc?->color ?? '') }}">
                    <code id="colorHex" class="ltr">{{ strtoupper($pickerHex) }}</code>
                </div>
                <div class="form-text" id="colorHelp">{{ __('اختر لون العلامة على الخريطة') }}</div>
            </div>
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __($submitLabel ?? 'حفظ الموقع') }}</button>
                    <a href="{{ route('admin.map-locations.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</div>

@push('styles')
<style>
    .pin-preview { width: 48px; height: 48px; flex: none; margin-top: 30px; border-radius: 50%; display: grid; place-items: center; font-size: 20px;
        background: var(--pin); color: white; box-shadow: 0 0 0 3px var(--surface), 0 0 0 4px var(--hairline); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var iconSelect = document.getElementById('iconSelect');
    var iconPreview = document.getElementById('iconPreview');
    iconSelect.addEventListener('change', function () {
        var i = document.createElement('i');
        i.className = 'bi bi-' + (this.value || 'pin-map-fill');
        iconPreview.replaceChildren(i);
    });

    var colorPicker = document.getElementById('colorPicker');
    var colorHidden = document.getElementById('colorHidden');
    var colorPreview = document.getElementById('colorPreview');
    var colorHex = document.getElementById('colorHex');
    colorPicker.addEventListener('input', function () {
        var hex = this.value;
        colorHidden.value = parseInt(hex.substring(1), 16);
        colorPreview.style.setProperty('--pin', hex);
        colorHex.textContent = hex.toUpperCase();
    });
});
</script>
@endpush
