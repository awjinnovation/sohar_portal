@extends('layouts.admin')

@section('title', 'إضافة فعالية جديدة')
@section('page-title', 'إضافة فعالية جديدة')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">معلومات الفعالية الجديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.events.store') }}" method="POST">
                        @csrf
                        
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#arabic-content">
                                    <i class="bi bi-translate"></i> العربية
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#english-content">
                                    <i class="bi bi-globe"></i> English
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="arabic-content">
                                <div class="mb-3">
                                    <label for="title_ar" class="form-label">عنوان الفعالية <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title_ar') is-invalid @enderror" 
                                           id="title_ar" name="title_ar" value="{{ old('title_ar') }}" required>
                                    @error('title_ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description_ar" class="form-label">الوصف <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description_ar') is-invalid @enderror"
                                              id="description_ar" name="description_ar" rows="4" required>{{ old('description_ar') }}</textarea>
                                    @error('description_ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="location_ar" class="form-label">الموقع</label>
                                            <input type="text" class="form-control @error('location_ar') is-invalid @enderror" 
                                                   id="location_ar" name="location_ar" value="{{ old('location_ar') }}">
                                            @error('location_ar')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="organizer_name_ar" class="form-label">اسم المنظم</label>
                                            <input type="text" class="form-control @error('organizer_name_ar') is-invalid @enderror" 
                                                   id="organizer_name_ar" name="organizer_name_ar" value="{{ old('organizer_name_ar') }}">
                                            @error('organizer_name_ar')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="english-content">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description') is-invalid @enderror"
                                              id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                                   id="location" name="location" value="{{ old('location') }}" required>
                                            @error('location')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="organizer_name" class="form-label">Organizer Name</label>
                                            <input type="text" class="form-control @error('organizer_name') is-invalid @enderror" 
                                                   id="organizer_name" name="organizer_name" value="{{ old('organizer_name') }}">
                                            @error('organizer_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">الفئة <span class="text-danger">*</span></label>
                                    <select class="form-select @error('category_id') is-invalid @enderror" 
                                            id="category_id" name="category_id" required>
                                        <option value="">اختر الفئة</option>
                                        @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name_ar }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">تاريخ البداية <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('start_time') is-invalid @enderror" 
                                           id="start_time" name="start_time" value="{{ old('start_time') }}" required>
                                    @error('start_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">تاريخ النهاية <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('end_time') is-invalid @enderror" 
                                           id="end_time" name="end_time" value="{{ old('end_time') }}" required>
                                    @error('end_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="price" class="form-label">السعر <span class="text-danger">*</span></label>
                                    <input type="number" step="0.001" class="form-control @error('price') is-invalid @enderror" 
                                           id="price" name="price" value="{{ old('price', 0) }}" min="0" required>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">العملة</label>
                                    <select class="form-select @error('currency') is-invalid @enderror" 
                                            id="currency" name="currency">
                                        <option value="OMR" {{ old('currency') == 'OMR' ? 'selected' : '' }}>OMR</option>
                                        <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                                        <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                                    </select>
                                    @error('currency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="available_tickets" class="form-label">التذاكر المتاحة</label>
                                    <input type="number" class="form-control @error('available_tickets') is-invalid @enderror" 
                                           id="available_tickets" name="available_tickets" value="{{ old('available_tickets') }}" min="0">
                                    @error('available_tickets')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="total_tickets" class="form-label">إجمالي التذاكر</label>
                                    <input type="number" class="form-control @error('total_tickets') is-invalid @enderror" 
                                           id="total_tickets" name="total_tickets" value="{{ old('total_tickets') }}" min="0">
                                    @error('total_tickets')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="latitude" class="form-label">خط العرض</label>
                                    <input type="number" step="0.00000001" class="form-control @error('latitude') is-invalid @enderror" 
                                           id="latitude" name="latitude" value="{{ old('latitude') }}" min="-90" max="90">
                                    @error('latitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="longitude" class="form-label">خط الطول</label>
                                    <input type="number" step="0.00000001" class="form-control @error('longitude') is-invalid @enderror" 
                                           id="longitude" name="longitude" value="{{ old('longitude') }}" min="-180" max="180">
                                    @error('longitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الصورة الرئيسية</label>
                            <div class="card">
                                <div class="card-body">
                                    <div id="main-image-zone" class="border border-2 border-dashed rounded p-3 text-center mb-2" style="cursor: pointer; background: #f8f9fa;">
                                        <i class="bi bi-image" style="font-size: 2rem; color: #6c757d;"></i>
                                        <p class="mb-1 mt-2"><strong>اضغط لتحميل الصورة الرئيسية</strong></p>
                                        <p class="text-muted small mb-0">PNG, JPG, JPEG حتى 10MB</p>
                                        <input type="file" id="main-image-input" accept="image/*" style="display: none;">
                                    </div>
                                    <div id="main-image-preview" class="text-center" style="display: none;">
                                        <img id="main-image-display" src="" class="img-fluid rounded" style="max-height: 200px;">
                                        <button type="button" class="btn btn-sm btn-danger mt-2" id="remove-main-image">
                                            <i class="bi bi-trash"></i> حذف الصورة
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="image_url" id="image_url" value="{{ old('image_url') }}">
                            @error('image_url')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">معرض الصور الإضافية</label>
                            <div class="card">
                                <div class="card-body">
                                    <!-- Upload Zone -->
                                    <div id="upload-zone" class="border border-2 border-dashed rounded p-4 text-center mb-3" style="cursor: pointer; background: #f8f9fa;">
                                        <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                                        <p class="mb-2 mt-2"><strong>اسحب الصور هنا أو اضغط للتحميل</strong></p>
                                        <p class="text-muted small mb-0">PNG, JPG, JPEG حتى 10MB</p>
                                        <input type="file" id="file-input" multiple accept="image/*" style="display: none;">
                                    </div>

                                    <!-- Upload Progress -->
                                    <div id="upload-progress" class="progress mb-3" style="display: none; height: 25px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                                    </div>

                                    <!-- Gallery Preview -->
                                    <div id="image-gallery-preview" class="row g-3">
                                        <!-- Images will be displayed here -->
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="images" id="images-input" value="{{ old('images', '[]') }}">
                            @error('images')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        نشط
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                           value="1" {{ old('is_featured') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_featured">
                                        مميز
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">
                                رجوع
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i>
                                حفظ الفعالية
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let images = [];
    let mainImageUrl = '';
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');
    const mainImageZone = document.getElementById('main-image-zone');
    const mainImageInput = document.getElementById('main-image-input');
    const mainImagePreview = document.getElementById('main-image-preview');
    const mainImageDisplay = document.getElementById('main-image-display');
    const removeMainImageBtn = document.getElementById('remove-main-image');
    const progressBar = document.getElementById('upload-progress');
    const progressBarInner = progressBar.querySelector('.progress-bar');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Try to load existing images from old input
    try {
        const existingImages = document.getElementById('images-input').value;
        if (existingImages && existingImages !== '[]') {
            images = JSON.parse(existingImages);
            renderGallery();
        }

        const existingMainImage = document.getElementById('image_url').value;
        if (existingMainImage) {
            mainImageUrl = existingMainImage;
            showMainImage();
        }
    } catch (e) {
        console.error('Error parsing existing images:', e);
    }

    // Main image upload
    mainImageZone.addEventListener('click', () => mainImageInput.click());

    mainImageInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('الرجاء اختيار ملف صورة صحيح');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            alert('حجم الملف أكبر من 10MB');
            return;
        }

        try {
            mainImageUrl = await uploadFile(file);
            document.getElementById('image_url').value = mainImageUrl;
            showMainImage();
        } catch (error) {
            alert('فشل تحميل الصورة');
        }
    });

    removeMainImageBtn.addEventListener('click', () => {
        if (confirm('هل تريد حذف الصورة الرئيسية؟')) {
            mainImageUrl = '';
            document.getElementById('image_url').value = '';
            mainImageZone.style.display = 'block';
            mainImagePreview.style.display = 'none';
        }
    });

    function showMainImage() {
        mainImageDisplay.src = mainImageUrl;
        mainImageZone.style.display = 'none';
        mainImagePreview.style.display = 'block';
    }

    // Gallery images upload
    uploadZone.addEventListener('click', () => fileInput.click());

    // File selection
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.style.background = '#e9ecef';
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.style.background = '#f8f9fa';
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.style.background = '#f8f9fa';
        handleFiles(e.dataTransfer.files);
    });

    async function handleFiles(files) {
        if (!files.length) return;

        progressBar.style.display = 'block';
        const totalFiles = files.length;
        let uploadedFiles = 0;

        for (let file of files) {
            if (!file.type.startsWith('image/')) {
                alert(`${file.name} ليس ملف صورة صحيح`);
                continue;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert(`${file.name} حجم الملف أكبر من 10MB`);
                continue;
            }

            try {
                const url = await uploadFile(file);
                images.push(url);
                uploadedFiles++;

                // Update progress
                const progress = (uploadedFiles / totalFiles) * 100;
                progressBarInner.style.width = progress + '%';
                progressBarInner.textContent = Math.round(progress) + '%';

            } catch (error) {
                console.error('Upload error:', error);
                alert(`فشل تحميل ${file.name}`);
            }
        }

        // Hide progress bar after 1 second
        setTimeout(() => {
            progressBar.style.display = 'none';
            progressBarInner.style.width = '0%';
            progressBarInner.textContent = '0%';
        }, 1000);

        renderGallery();
        fileInput.value = ''; // Reset input
    }

    async function uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch('{{ route('admin.media.upload') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        return data.url;
    }

    function renderGallery() {
        const gallery = document.getElementById('image-gallery-preview');
        gallery.innerHTML = '';

        images.forEach((url, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-6';
            col.innerHTML = `
                <div class="card h-100 shadow-sm">
                    <img src="${url}" class="card-img-top" style="height: 200px; object-fit: cover;" alt="صورة ${index + 1}">
                    <div class="card-body p-2 text-center">
                        <button type="button" class="btn btn-sm btn-danger w-100" onclick="removeImage(${index})">
                            <i class="bi bi-trash"></i> حذف
                        </button>
                    </div>
                </div>
            `;
            gallery.appendChild(col);
        });

        // Update hidden input
        document.getElementById('images-input').value = JSON.stringify(images);
    }

    window.removeImage = function(index) {
        if (confirm('هل تريد حذف هذه الصورة؟')) {
            images.splice(index, 1);
            renderGallery();
        }
    };
});
</script>
@endpush