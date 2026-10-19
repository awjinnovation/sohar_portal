{{-- Shared create/edit body. Expects $appSetting (new or existing) and $submitLabel; the caller wraps it in the <form>. --}}
@php
    $settingTypes = [
        'string' => 'نص',
        'integer' => 'عدد صحيح',
        'boolean' => 'قيمة منطقية',
        'decimal' => 'عدد عشري',
        'json' => 'JSON',
    ];
    $currentType = old('setting_type', $appSetting->setting_type ?? 'string');
    $currentValue = old('setting_value', $appSetting->setting_value);
@endphp
<div class="row g-4">
    <div class="col-xl-8">
        <x-admin.card title="معلومات الإعداد" icon="sliders" subtitle="قيمة يقرأها تطبيق الهاتف عبر واجهة الإعدادات">
            <div class="row">
                <x-admin.form-field col="col-md-7" name="setting_key" id="setting_key" label="مفتاح الإعداد" :value="old('setting_key', $appSetting->setting_key)"
                    dir="ltr" required maxlength="100" autocomplete="off" help="المفتاح المستخدم للوصول إلى هذا الإعداد" />
                <x-admin.form-field col="col-md-5" name="setting_type" id="setting_type" type="select" label="نوع البيانات" :value="$currentType"
                    :options="$settingTypes" required />
            </div>

            <div class="field">
                <label for="setting_value" class="form-label">{{ __('القيمة') }}<span class="req">*</span></label>
                <div id="boolean_value" @if($currentType !== 'boolean') hidden @endif>
                    <label class="toggle-row" for="boolean_checkbox">
                        <span class="tr-text">
                            <span class="tr-label d-block">{{ __('تفعيل') }}</span>
                            <span class="tr-desc d-block">{{ __('يُحفظ بالقيمة') }} <code>1</code> {{ __('عند التفعيل، وبالقيمة') }} <code>0</code> {{ __('عند الإيقاف') }}</span>
                        </span>
                        <span class="switch">
                            <input type="checkbox" role="switch" id="boolean_checkbox" @checked($currentValue == '1')>
                            <span class="track"></span>
                        </span>
                    </label>
                </div>
                <textarea class="form-control @error('setting_value') is-invalid @enderror" id="setting_value" name="setting_value" rows="4"
                          @if($currentType === 'boolean') hidden @endif required>{{ $currentValue }}</textarea>
                @error('setting_value')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text"><i class="bi bi-lightbulb"></i> <span id="value_hint">{{ __('القيمة المخزنة لهذا الإعداد') }}</span></div>
            </div>

            <x-admin.form-field name="description" id="description" type="textarea" rows="2" label="الوصف" :value="old('description', $appSetting->description)" />

            <x-admin.toggle name="is_public" id="is_public" :hidden="true" label="متاح للعموم عبر API"
                description="عند تفعيله يقرأ التطبيق هذا الإعداد عبر API" :checked="old('is_public', $appSetting->is_public)" />
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="الحفظ" icon="check2-circle">
            @if($appSetting->exists)
                <dl class="dl">
                    <dt>{{ __('تم الإنشاء') }}</dt><dd><span class="num ltr">{{ $appSetting->created_at?->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('آخر تحديث') }}</dt><dd><span class="num ltr">{{ $appSetting->updated_at?->format('Y-m-d H:i') }}</span></dd>
                </dl>
            @else
                <p class="text-muted small mb-0">{{ __('يُحفظ الإعداد لهذه المنظمة فقط، ويُحدَّث التخزين المؤقت تلقائيًا.') }}</p>
            @endif
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __($submitLabel ?? 'حفظ') }}</button>
                    <a href="{{ route('admin.app-settings.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                </div>
            </x-slot:footer>
        </x-admin.card>

        <x-admin.card title="أمثلة بحسب النوع" icon="lightbulb">
            <dl class="dl">
                <dt><code>String</code></dt><dd>"{{ ($brand ?? null)?->localizedName('ar') ?? config('app.name') }}"</dd>
                <dt><code>Integer</code></dt><dd class="num">2000</dd>
                <dt><code>Boolean</code></dt><dd><span class="num">1</span> {{ __('أو') }} <span class="num">0</span></dd>
                <dt><code>Decimal</code></dt><dd class="num">24.3589</dd>
                <dt><code>JSON</code></dt><dd><code class="ltr">{"key": "value"}</code></dd>
            </dl>
        </x-admin.card>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const type = document.getElementById('setting_type');
    const valueField = document.getElementById('setting_value');
    const booleanDiv = document.getElementById('boolean_value');
    const booleanCheckbox = document.getElementById('boolean_checkbox');
    const valueHint = document.getElementById('value_hint');
    if (!type || !valueField) return;

    const hints = {
        boolean: [@json(__('استعمل المفتاح للتبديل بين نعم ولا')), 4],
        json: [@json(__('أدخل JSON صحيحًا، مثل {"key": "value"}')), 6],
        integer: [@json(__('أدخل عددًا صحيحًا، مثل 2000')), 2],
        decimal: [@json(__('أدخل عددًا عشريًا، مثل 24.3589')), 2],
        string: [@json(__('أدخل نصًا عاديًا')), 4],
    };

    function update() {
        const t = type.value;
        const isBool = t === 'boolean';
        booleanDiv.hidden = !isBool;
        valueField.hidden = isBool;
        valueField.dir = (t === 'json' || t === 'integer' || t === 'decimal') ? 'ltr' : 'auto';
        const [hint, rows] = hints[t] || hints.string;
        valueHint.textContent = hint;
        valueField.rows = rows;
        if (isBool) valueField.value = booleanCheckbox.checked ? '1' : '0';
    }

    booleanCheckbox.addEventListener('change', function () { valueField.value = this.checked ? '1' : '0'; });
    type.addEventListener('change', update);
    update();
})();
</script>
@endpush
