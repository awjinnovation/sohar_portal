{{-- Bilingual legal document form (privacy policy / terms).
     @include('admin.partials.legal-form', ['item' => $model|null, 'submitLabel' => '…', 'cancelUrl' => route(…)]) --}}
<div class="row g-4">
    <div class="col-xl-8">
        <x-admin.card title="نص المستند" icon="translate" subtitle="يظهر للمستخدم بلغته في صفحة التطبيق العامة">
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
                    <x-admin.form-field name="title_ar" id="title_ar" label="العنوان (العربية)" :value="old('title_ar', $item->title_ar ?? '')" required />
                    <div class="field mb-0">
                        <label for="content_ar" class="form-label">{{ __('المحتوى (العربية)') }}<span class="req">*</span></label>
                        <textarea name="content_ar" id="content_ar" rows="12" class="form-control @error('content_ar') is-invalid @enderror">{{ old('content_ar', $item->content_ar ?? '') }}</textarea>
                        @error('content_ar')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                    <x-admin.form-field name="title" id="title" label="Title (English)" dir="ltr" :value="old('title', $item->title ?? '')" required />
                    <div class="field mb-0">
                        <label for="content" class="form-label">Content (English)<span class="req">*</span></label>
                        <textarea name="content" id="content" rows="12" dir="ltr" class="form-control @error('content') is-invalid @enderror">{{ old('content', $item->content ?? '') }}</textarea>
                        @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="النشر" icon="sliders">
            <x-admin.toggle name="is_active" id="is_active" label="نشط" description="النسخة النشطة هي التي تظهر في التطبيق" :checked="old('is_active', $item->is_active ?? true)" />
            @if($item)
                <dl class="dl mt-2">
                    <dt>{{ __('تاريخ الإنشاء') }}</dt>
                    <dd class="num">{{ $item->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $item->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
            @endif
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __($submitLabel ?? 'حفظ') }}</button>
                    @isset($cancelUrl)<a href="{{ $cancelUrl }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>@endisset
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</div>

@push('scripts')
<script src="https://cdn.ckeditor.com/4.20.1/standard/ckeditor.js"></script>
<script>
    (function () {
        if (! window.CKEDITOR) return;
        var opts = { ar: { language: 'ar', contentsLangDirection: 'rtl', height: 380, versionCheck: false }, en: { language: 'en', contentsLangDirection: 'ltr', height: 380, versionCheck: false } };
        CKEDITOR.replace('content_ar', opts.ar);
        // The English editor lives in a hidden tab; create it the first time that tab is shown.
        var enTab = document.getElementById('en-tab');
        var started = false;
        function startEn() { if (! started) { started = true; CKEDITOR.replace('content', opts.en); } }
        if (enTab) { enTab.addEventListener('shown.bs.tab', startEn); } else { startEn(); }
    })();
</script>
@endpush
