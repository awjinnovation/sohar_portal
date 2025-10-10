@extends('layouts.admin')

@section('page-title', 'تعديل الإعداد')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">✏️ تعديل الإعداد</h2>
            <p class="text-muted mb-0">تحديث إعدادات تطبيق Flutter</p>
        </div>
        <a href="{{ route('admin.app-settings.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع للقائمة
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">معلومات الإعداد</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.app-settings.update', $appSetting) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Setting Key -->
                        <div class="mb-3">
                            <label for="setting_key" class="form-label">مفتاح الإعداد <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('setting_key') is-invalid @enderror"
                                   id="setting_key"
                                   name="setting_key"
                                   value="{{ old('setting_key', $appSetting->setting_key) }}"
                                   required>
                            @error('setting_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> المفتاح المستخدم للوصول إلى هذا الإعداد
                            </div>
                        </div>

                        <!-- Setting Type -->
                        <div class="mb-3">
                            <label for="setting_type" class="form-label">نوع البيانات <span class="text-danger">*</span></label>
                            <select class="form-select @error('setting_type') is-invalid @enderror"
                                    id="setting_type"
                                    name="setting_type"
                                    onchange="updateValueField()"
                                    required>
                                <option value="string" {{ old('setting_type', $appSetting->setting_type) == 'string' ? 'selected' : '' }}>
                                    نص (String)
                                </option>
                                <option value="integer" {{ old('setting_type', $appSetting->setting_type) == 'integer' ? 'selected' : '' }}>
                                    رقم صحيح (Integer)
                                </option>
                                <option value="boolean" {{ old('setting_type', $appSetting->setting_type) == 'boolean' ? 'selected' : '' }}>
                                    منطقي (Boolean)
                                </option>
                                <option value="decimal" {{ old('setting_type', $appSetting->setting_type) == 'decimal' ? 'selected' : '' }}>
                                    عشري (Decimal)
                                </option>
                                <option value="json" {{ old('setting_type', $appSetting->setting_type) == 'json' ? 'selected' : '' }}>
                                    JSON
                                </option>
                            </select>
                            @error('setting_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Setting Value -->
                        <div class="mb-3">
                            <label for="setting_value" class="form-label">القيمة <span class="text-danger">*</span></label>

                            <!-- For boolean -->
                            <div id="boolean_value" style="display: none;">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="boolean_checkbox"
                                           {{ old('setting_value', $appSetting->setting_value) == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="boolean_checkbox">
                                        تفعيل
                                    </label>
                                </div>
                            </div>

                            <!-- For text/json -->
                            <textarea class="form-control @error('setting_value') is-invalid @enderror"
                                      id="setting_value"
                                      name="setting_value"
                                      rows="4"
                                      required>{{ old('setting_value', $appSetting->setting_value) }}</textarea>

                            @error('setting_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <i class="bi bi-lightbulb"></i>
                                <span id="value_hint">القيمة المخزنة لهذا الإعداد</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description"
                                      name="description"
                                      rows="2">{{ old('description', $appSetting->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Is Public -->
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_public"
                                       name="is_public"
                                       value="1"
                                       {{ old('is_public', $appSetting->is_public) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_public">
                                    <i class="bi bi-globe"></i> متاح للعموم (سيظهر في API)
                                </label>
                            </div>
                            <div class="form-text">
                                إذا كان مفعلاً، سيكون هذا الإعداد متاحاً عبر API للتطبيق
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.app-settings.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Info Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> معلومات الإعداد
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <strong>تم الإنشاء:</strong><br>
                        {{ $appSetting->created_at->format('Y-m-d H:i') }}
                    </p>
                    <p class="small mb-0">
                        <strong>آخر تحديث:</strong><br>
                        {{ $appSetting->updated_at->format('Y-m-d H:i') }}
                    </p>
                </div>
            </div>

            <!-- Type Examples -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightbulb"></i> أمثلة حسب النوع
                </div>
                <div class="card-body small">
                    <p><strong>String:</strong> "مهرجان صحار"</p>
                    <p><strong>Integer:</strong> 2000</p>
                    <p><strong>Boolean:</strong> 1 أو 0</p>
                    <p><strong>Decimal:</strong> 24.3589</p>
                    <p class="mb-0"><strong>JSON:</strong> {"key": "value"}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function updateValueField() {
        const type = document.getElementById('setting_type').value;
        const valueField = document.getElementById('setting_value');
        const booleanDiv = document.getElementById('boolean_value');
        const valueHint = document.getElementById('value_hint');
        const booleanCheckbox = document.getElementById('boolean_checkbox');

        if (type === 'boolean') {
            booleanDiv.style.display = 'block';
            valueField.style.display = 'none';
            valueHint.textContent = 'استخدم المفتاح للتبديل بين نعم/لا';

            // Sync checkbox with hidden field
            booleanCheckbox.addEventListener('change', function() {
                valueField.value = this.checked ? '1' : '0';
            });
            valueField.value = booleanCheckbox.checked ? '1' : '0';
        } else {
            booleanDiv.style.display = 'none';
            valueField.style.display = 'block';

            if (type === 'json') {
                valueHint.textContent = 'أدخل JSON صحيح، مثال: {"key": "value"}';
                valueField.rows = 6;
            } else if (type === 'integer') {
                valueHint.textContent = 'أدخل رقم صحيح، مثال: 2000';
                valueField.rows = 2;
            } else if (type === 'decimal') {
                valueHint.textContent = 'أدخل رقم عشري، مثال: 24.3589';
                valueField.rows = 2;
            } else {
                valueHint.textContent = 'أدخل نص عادي';
                valueField.rows = 4;
            }
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateValueField();
    });
</script>
@endpush
@endsection
