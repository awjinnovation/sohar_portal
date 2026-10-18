@extends('layouts.admin')

@section('title', 'تعديل الفعالية')
@section('page-title', 'تعديل الفعالية')
@section('page-subtitle', $event->title_ar)
@php
    $pageIcon = 'calendar-event';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الفعاليات' => route('admin.events.index'), 'تعديل' => null];
    $model = $event;
    $mainImage = old('image_url', $event->image_url);
    $galleryJson = old('images', json_encode($event->images ?? []));
@endphp
@section('page-actions')
    <a href="{{ route('admin.events.show', $event) }}" class="btn"><i class="bi bi-eye"></i> {{ __('عرض') }}</a>
    <a href="{{ route('admin.events.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.events.update', $event) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="معلومات الفعالية" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي حسب لغة المستخدم">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="arabic-tab" data-bs-toggle="tab" data-bs-target="#arabic-content" type="button" role="tab">{{ __('العربية') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="english-tab" data-bs-toggle="tab" data-bs-target="#english-content" type="button" role="tab">English</button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3">
                        <div class="tab-pane fade show active" id="arabic-content" role="tabpanel">
                            <x-admin.form-field name="title_ar" label="عنوان الفعالية" :value="old('title_ar', $model?->title_ar)" required />
                            <x-admin.form-field name="description_ar" label="الوصف" type="textarea" rows="5" :value="old('description_ar', $model?->description_ar)" required />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="location_ar" label="الموقع" :value="old('location_ar', $model?->location_ar)" />
                                <x-admin.form-field col="col-md-6" name="organizer_name_ar" label="اسم المنظم" :value="old('organizer_name_ar', $model?->organizer_name_ar)" />
                            </div>
                        </div>
                        <div class="tab-pane fade" id="english-content" role="tabpanel" dir="ltr">
                            <x-admin.form-field name="title" label="Event Title" :value="old('title', $model?->title)" required />
                            <x-admin.form-field name="description" label="Description" type="textarea" rows="5" :value="old('description', $model?->description)" required />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="location" label="Location" :value="old('location', $model?->location)" required />
                                <x-admin.form-field col="col-md-6" name="organizer_name" label="Organizer Name" :value="old('organizer_name', $model?->organizer_name)" />
                            </div>
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="الصور" icon="images" subtitle="الصورة الرئيسية تظهر في البطاقات، والمعرض في صفحة الفعالية">
                    <div class="field">
                        <label class="form-label" for="main-image-input">{{ __('الصورة الرئيسية') }}</label>
                        <div id="main-image-zone" class="upload {{ $mainImage ? 'd-none' : '' }}" role="button" tabindex="0">
                            <div class="preview"><i class="bi bi-image"></i></div>
                            <div>
                                <div class="u-title">{{ __('اضغط لتحميل الصورة الرئيسية') }}</div>
                                <div class="u-help">{{ __('PNG, JPG, JPEG حتى 10MB') }}</div>
                            </div>
                            <input type="file" id="main-image-input" accept="image/*" class="d-none">
                        </div>
                        <div id="main-image-preview" class="{{ $mainImage ? '' : 'd-none' }}">
                            <div class="ratio ratio-21x9 rounded overflow-hidden border bg-light">
                                <img id="main-image-display" src="{{ $mainImage }}" alt="" class="object-fit-cover">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="remove-main-image">
                                <i class="bi bi-trash"></i> {{ __('حذف الصورة') }}
                            </button>
                        </div>
                        <input type="hidden" name="image_url" id="image_url" value="{{ $mainImage }}">
                        @error('image_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="field mb-0">
                        <label class="form-label" for="file-input">{{ __('معرض الصور الإضافية') }}</label>
                        <div id="upload-zone" class="upload" role="button" tabindex="0">
                            <div class="preview"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div>
                                <div class="u-title">{{ __('اسحب الصور هنا أو اضغط للتحميل') }}</div>
                                <div class="u-help">{{ __('يمكن اختيار عدة صور · PNG, JPG, JPEG حتى 10MB') }}</div>
                            </div>
                            <input type="file" id="file-input" multiple accept="image/*" class="d-none">
                        </div>
                        <div id="upload-progress" class="progress mt-3 d-none" role="progressbar" aria-label="{{ __('تقدم التحميل') }}">
                            <div class="progress-bar bg-primary" style="width: 0%">0%</div>
                        </div>
                        <div id="image-gallery-preview" class="row g-3 mt-0"></div>
                        <input type="hidden" name="images" id="images-input" value="{{ $galleryJson }}">
                        @error('images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="الموعد والمكان" icon="calendar3">
                    <x-admin.form-field name="category_id" label="الفئة" type="select" required placeholder="اختر الفئة"
                        :value="old('category_id', $model?->category_id)" :options="$categories->pluck('name_ar', 'id')->all()" />
                    <x-admin.form-field name="start_time" label="تاريخ البداية" type="datetime-local" required
                        :value="old('start_time', $model?->start_time?->format('Y-m-d\TH:i'))" />
                    <x-admin.form-field name="end_time" label="تاريخ النهاية" type="datetime-local" required
                        :value="old('end_time', $model?->end_time?->format('Y-m-d\TH:i'))" />
                    <x-admin.form-field name="map_location_id" label="موقع الخريطة" type="select" placeholder="اختر الموقع على الخريطة"
                        :value="old('map_location_id', $model?->map_location_id)"
                        :options="$mapLocations->mapWithKeys(fn ($loc) => [$loc->id => $loc->name_ar.' ('.$loc->latitude.', '.$loc->longitude.')'])->all()"
                        help="يُستخدم لعرض الفعالية على خريطة التطبيق (خط العرض والطول)" />
                </x-admin.card>

                <x-admin.card title="التذاكر والسعر" icon="ticket-perforated">
                    <div class="row">
                        <x-admin.form-field col="col-7" name="price" label="السعر" type="number" step="0.001" min="0" dir="ltr" required
                            :value="old('price', $model ? $model->price : 0)" />
                        <x-admin.form-field col="col-5" name="currency" label="العملة" type="select"
                            :value="old('currency', $model?->currency)" :options="['OMR' => 'OMR', 'USD' => 'USD', 'EUR' => 'EUR']" />
                    </div>
                    <div class="row">
                        <x-admin.form-field col="col-6" name="available_tickets" label="التذاكر المتاحة" type="number" min="0" dir="ltr"
                            :value="old('available_tickets', $model?->available_tickets)" />
                        <x-admin.form-field col="col-6" name="total_tickets" label="إجمالي التذاكر" type="number" min="0" dir="ltr"
                            :value="old('total_tickets', $model?->total_tickets)" />
                    </div>
                    <div class="form-text mt-n2">{{ __('السعر 0 يعني فعالية مجانية.') }}</div>
                </x-admin.card>

                <x-admin.card title="النشر" icon="broadcast">
                    <x-admin.toggle name="is_active" id="is_active" label="نشط" description="تظهر الفعالية في التطبيق" :checked="old('is_active', $model ? $model->is_active : true)" />
                    <x-admin.toggle name="is_featured" id="is_featured" label="مميز" description="تظهر في قسم الفعاليات المميزة" :checked="old('is_featured', $model?->is_featured)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
                            <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var uploadUrl = @json(route('admin.media.upload'));
    var maxBytes = 10 * 1024 * 1024;

    var mainZone = document.getElementById('main-image-zone');
    var mainInput = document.getElementById('main-image-input');
    var mainPreview = document.getElementById('main-image-preview');
    var mainDisplay = document.getElementById('main-image-display');
    var mainHidden = document.getElementById('image_url');
    var removeMain = document.getElementById('remove-main-image');

    var zone = document.getElementById('upload-zone');
    var fileInput = document.getElementById('file-input');
    var progress = document.getElementById('upload-progress');
    var bar = progress.querySelector('.progress-bar');
    var gallery = document.getElementById('image-gallery-preview');
    var imagesInput = document.getElementById('images-input');

    var images = [];
    try {
        var parsed = JSON.parse(imagesInput.value || '[]');
        if (Array.isArray(parsed)) images = parsed;
    } catch (e) { images = []; }

    function validFile(file) {
        if (!file.type.startsWith('image/')) { alert(@json(__(':name ليس ملف صورة صحيح')).replace(':name', file.name)); return false; }
        if (file.size > maxBytes) { alert(@json(__(':name حجم الملف أكبر من 10MB')).replace(':name', file.name)); return false; }
        return true;
    }

    async function uploadFile(file) {
        var formData = new FormData();
        formData.append('file', file);
        var response = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: formData });
        if (!response.ok) throw new Error('Upload failed');
        var data = await response.json();
        return data.url;
    }

    function bindZone(el, input) {
        el.addEventListener('click', function () { input.click(); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        el.addEventListener('dragover', function (e) { e.preventDefault(); el.classList.add('drag'); });
        el.addEventListener('dragleave', function () { el.classList.remove('drag'); });
    }

    /* main image */
    function showMain(url) {
        mainHidden.value = url || '';
        mainDisplay.src = url || '';
        mainZone.classList.toggle('d-none', !!url);
        mainPreview.classList.toggle('d-none', !url);
    }
    bindZone(mainZone, mainInput);
    mainZone.addEventListener('drop', function (e) {
        e.preventDefault(); mainZone.classList.remove('drag');
        if (e.dataTransfer.files[0]) handleMain(e.dataTransfer.files[0]);
    });
    mainInput.addEventListener('change', function (e) { if (e.target.files[0]) handleMain(e.target.files[0]); mainInput.value = ''; });
    async function handleMain(file) {
        if (!validFile(file)) return;
        try { showMain(await uploadFile(file)); } catch (err) { alert(@json(__('فشل تحميل الصورة'))); }
    }
    removeMain.addEventListener('click', function () { showMain(''); });

    /* gallery */
    bindZone(zone, fileInput);
    zone.addEventListener('drop', function (e) { e.preventDefault(); zone.classList.remove('drag'); handleFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change', function (e) { handleFiles(e.target.files); });

    async function handleFiles(files) {
        if (!files.length) return;
        progress.classList.remove('d-none');
        var total = files.length, done = 0;
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (validFile(file)) {
                try { images.push(await uploadFile(file)); } catch (err) { alert(@json(__('فشل تحميل :name')).replace(':name', file.name)); }
            }
            done++;
            var pct = Math.round((done / total) * 100);
            bar.style.width = pct + '%';
            bar.textContent = pct + '%';
        }
        setTimeout(function () { progress.classList.add('d-none'); bar.style.width = '0%'; bar.textContent = '0%'; }, 800);
        renderGallery();
        fileInput.value = '';
    }

    function renderGallery() {
        gallery.innerHTML = '';
        images.forEach(function (url, index) {
            var col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-xxl-3';
            var wrap = document.createElement('div');
            wrap.className = 'position-relative rounded overflow-hidden border';
            var ratio = document.createElement('div');
            ratio.className = 'ratio ratio-4x3 bg-light';
            var img = document.createElement('img');
            img.src = url; img.alt = @json(__('صورة :number')).replace(':number', index + 1); img.className = 'object-fit-cover'; img.loading = 'lazy';
            ratio.appendChild(img);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 m-2 px-2';
            btn.title = @json(__('حذف')); btn.setAttribute('aria-label', @json(__('حذف الصورة :number')).replace(':number', index + 1));
            btn.innerHTML = '<i class="bi bi-trash"></i>';
            btn.addEventListener('click', function () { images.splice(index, 1); renderGallery(); });
            wrap.appendChild(ratio); wrap.appendChild(btn); col.appendChild(wrap);
            gallery.appendChild(col);
        });
        imagesInput.value = JSON.stringify(images);
    }

    renderGallery();
});
</script>
@endpush
