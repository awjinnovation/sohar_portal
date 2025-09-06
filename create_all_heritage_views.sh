#!/bin/bash

# Create directories
mkdir -p resources/views/admin/village-images
mkdir -p resources/views/admin/village-attractions  
mkdir -p resources/views/admin/craft-demonstrations
mkdir -p resources/views/admin/traditional-activities
mkdir -p resources/views/admin/cultural-workshops
mkdir -p resources/views/admin/photo-spots

# ========== VILLAGE IMAGES VIEWS ==========

# Index View
cat > resources/views/admin/village-images/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">صور القرى التراثية</h1>
        <a href="{{ route('admin.village-images.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة صورة جديدة
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>القرية</th>
                            <th>الوصف</th>
                            <th>مميزة</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($images as $image)
                            <tr>
                                <td>
                                    <img src="{{ $image->image_url }}" alt="صورة" style="width: 100px; height: 60px; object-fit: cover;">
                                </td>
                                <td>{{ $image->village->name_ar ?? '-' }}</td>
                                <td>{{ $image->caption_ar ?? $image->caption_en ?? '-' }}</td>
                                <td>
                                    @if($image->is_featured)
                                        <span class="badge bg-success">مميزة</span>
                                    @else
                                        <span class="badge bg-secondary">عادية</span>
                                    @endif
                                </td>
                                <td>{{ $image->display_order ?? '-' }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.village-images.show', $image) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.village-images.edit', $image) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.village-images.destroy', $image) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">لا توجد صور</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $images->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# Create View
cat > resources/views/admin/village-images/create.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة صورة جديدة</h1>
        <a href="{{ route('admin.village-images.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.village-images.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}" {{ old('heritage_village_id') == $village->id ? 'selected' : '' }}>
                                {{ $village->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label">الصورة</label>
                    <input type="file" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url" required accept="image/*">
                    @error('image_url')
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
                            <label for="caption_ar" class="form-label">الوصف (العربية)</label>
                            <input type="text" class="form-control @error('caption_ar') is-invalid @enderror" id="caption_ar" name="caption_ar" value="{{ old('caption_ar') }}">
                            @error('caption_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="caption_en" class="form-label">Caption (English)</label>
                            <input type="text" class="form-control @error('caption_en') is-invalid @enderror" id="caption_en" name="caption_en" value="{{ old('caption_en') }}">
                            @error('caption_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="display_order" class="form-label">الترتيب</label>
                    <input type="number" class="form-control @error('display_order') is-invalid @enderror" id="display_order" name="display_order" value="{{ old('display_order', 0) }}">
                    @error('display_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_featured">
                            صورة مميزة
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Edit View
cat > resources/views/admin/village-images/edit.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل الصورة</h1>
        <a href="{{ route('admin.village-images.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.village-images.update', $villageImage) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}" {{ old('heritage_village_id', $villageImage->heritage_village_id) == $village->id ? 'selected' : '' }}>
                                {{ $village->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">الصورة الحالية</label>
                    <div>
                        <img src="{{ $villageImage->image_url }}" alt="الصورة الحالية" style="max-width: 300px; height: auto;">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label">صورة جديدة (اختياري)</label>
                    <input type="file" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url" accept="image/*">
                    @error('image_url')
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
                            <label for="caption_ar" class="form-label">الوصف (العربية)</label>
                            <input type="text" class="form-control @error('caption_ar') is-invalid @enderror" id="caption_ar" name="caption_ar" value="{{ old('caption_ar', $villageImage->caption_ar) }}">
                            @error('caption_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="caption_en" class="form-label">Caption (English)</label>
                            <input type="text" class="form-control @error('caption_en') is-invalid @enderror" id="caption_en" name="caption_en" value="{{ old('caption_en', $villageImage->caption_en) }}">
                            @error('caption_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="display_order" class="form-label">الترتيب</label>
                    <input type="number" class="form-control @error('display_order') is-invalid @enderror" id="display_order" name="display_order" value="{{ old('display_order', $villageImage->display_order) }}">
                    @error('display_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $villageImage->is_featured) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_featured">
                            صورة مميزة
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

# Show View
cat > resources/views/admin/village-images/show.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل الصورة</h1>
        <div>
            <a href="{{ route('admin.village-images.edit', $villageImage) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.village-images.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <img src="{{ $villageImage->image_url }}" alt="صورة" class="img-fluid mb-3">
                </div>
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th>القرية التراثية:</th>
                            <td>
                                <a href="{{ route('admin.heritage-villages.show', $villageImage->village) }}">
                                    {{ $villageImage->village->name_ar ?? '-' }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>الوصف (العربية):</th>
                            <td>{{ $villageImage->caption_ar ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Caption (English):</th>
                            <td>{{ $villageImage->caption_en ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>الترتيب:</th>
                            <td>{{ $villageImage->display_order ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($villageImage->is_featured)
                                    <span class="badge bg-success">مميزة</span>
                                @else
                                    <span class="badge bg-secondary">عادية</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ الإضافة:</th>
                            <td>{{ $villageImage->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOF

echo "Village Images views created successfully!"