#!/bin/bash

echo "Fixing emergency-contacts and map-locations views..."

# ========== EMERGENCY CONTACTS VIEWS ==========

# Create view
cat > resources/views/admin/emergency-contacts/create.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة رقم طوارئ جديد</h1>
        <a href="{{ route('admin.emergency-contacts.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.emergency-contacts.store') }}" method="POST">
                @csrf

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
                            <label for="service_name_ar" class="form-label">اسم الخدمة (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('service_name_ar') is-invalid @enderror" 
                                   id="service_name_ar" name="service_name_ar" value="{{ old('service_name_ar') }}" required>
                            @error('service_name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية)</label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                                      id="description_ar" name="description_ar" rows="3">{{ old('description_ar') }}</textarea>
                            @error('description_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="location_ar" class="form-label">الموقع (العربية)</label>
                            <input type="text" class="form-control @error('location_ar') is-invalid @enderror" 
                                   id="location_ar" name="location_ar" value="{{ old('location_ar') }}">
                            @error('location_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="service_name" class="form-label">Service Name (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('service_name') is-invalid @enderror" 
                                   id="service_name" name="service_name" value="{{ old('service_name') }}" required>
                            @error('service_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (English)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location (English)</label>
                            <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                   id="location" name="location" value="{{ old('location') }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror" 
                                   id="phone_number" name="phone_number" value="{{ old('phone_number') }}" required>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="police" {{ old('type') == 'police' ? 'selected' : '' }}>شرطة</option>
                                <option value="ambulance" {{ old('type') == 'ambulance' ? 'selected' : '' }}>إسعاف</option>
                                <option value="fire" {{ old('type') == 'fire' ? 'selected' : '' }}>إطفاء</option>
                                <option value="security" {{ old('type') == 'security' ? 'selected' : '' }}>أمن</option>
                                <option value="first_aid" {{ old('type') == 'first_aid' ? 'selected' : '' }}>إسعافات أولية</option>
                                <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>أخرى</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="display_order" class="form-label">الترتيب</label>
                            <input type="number" class="form-control @error('display_order') is-invalid @enderror" 
                                   id="display_order" name="display_order" value="{{ old('display_order', 0) }}" min="0">
                            @error('display_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_24_hours" name="is_24_hours" value="1" 
                               {{ old('is_24_hours') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_24_hours">
                            متاح 24 ساعة
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
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

# Edit view
cat > resources/views/admin/emergency-contacts/edit.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل رقم الطوارئ</h1>
        <a href="{{ route('admin.emergency-contacts.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.emergency-contacts.update', $emergencyContact) }}" method="POST">
                @csrf
                @method('PUT')

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
                            <label for="service_name_ar" class="form-label">اسم الخدمة (العربية) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('service_name_ar') is-invalid @enderror" 
                                   id="service_name_ar" name="service_name_ar" value="{{ old('service_name_ar', $emergencyContact->service_name_ar) }}" required>
                            @error('service_name_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية)</label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" 
                                      id="description_ar" name="description_ar" rows="3">{{ old('description_ar', $emergencyContact->description_ar) }}</textarea>
                            @error('description_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="location_ar" class="form-label">الموقع (العربية)</label>
                            <input type="text" class="form-control @error('location_ar') is-invalid @enderror" 
                                   id="location_ar" name="location_ar" value="{{ old('location_ar', $emergencyContact->location_ar) }}">
                            @error('location_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel">
                        <div class="mb-3">
                            <label for="service_name" class="form-label">Service Name (English) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('service_name') is-invalid @enderror" 
                                   id="service_name" name="service_name" value="{{ old('service_name', $emergencyContact->service_name) }}" required>
                            @error('service_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (English)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $emergencyContact->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location (English)</label>
                            <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                   id="location" name="location" value="{{ old('location', $emergencyContact->location) }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror" 
                                   id="phone_number" name="phone_number" value="{{ old('phone_number', $emergencyContact->phone_number) }}" required>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="type" class="form-label">النوع <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="police" {{ old('type', $emergencyContact->type) == 'police' ? 'selected' : '' }}>شرطة</option>
                                <option value="ambulance" {{ old('type', $emergencyContact->type) == 'ambulance' ? 'selected' : '' }}>إسعاف</option>
                                <option value="fire" {{ old('type', $emergencyContact->type) == 'fire' ? 'selected' : '' }}>إطفاء</option>
                                <option value="security" {{ old('type', $emergencyContact->type) == 'security' ? 'selected' : '' }}>أمن</option>
                                <option value="first_aid" {{ old('type', $emergencyContact->type) == 'first_aid' ? 'selected' : '' }}>إسعافات أولية</option>
                                <option value="other" {{ old('type', $emergencyContact->type) == 'other' ? 'selected' : '' }}>أخرى</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="display_order" class="form-label">الترتيب</label>
                            <input type="number" class="form-control @error('display_order') is-invalid @enderror" 
                                   id="display_order" name="display_order" value="{{ old('display_order', $emergencyContact->display_order) }}" min="0">
                            @error('display_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_24_hours" name="is_24_hours" value="1" 
                               {{ old('is_24_hours', $emergencyContact->is_24_hours) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_24_hours">
                            متاح 24 ساعة
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                               {{ old('is_active', $emergencyContact->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            نشط
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

# Show view
cat > resources/views/admin/emergency-contacts/show.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل رقم الطوارئ</h1>
        <div>
            <a href="{{ route('admin.emergency-contacts.edit', $emergencyContact) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.emergency-contacts.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="200">رقم الهاتف:</th>
                            <td><strong>{{ $emergencyContact->phone_number }}</strong></td>
                        </tr>
                        <tr>
                            <th>النوع:</th>
                            <td>
                                @switch($emergencyContact->type)
                                    @case('police')
                                        <span class="badge bg-primary">شرطة</span>
                                        @break
                                    @case('ambulance')
                                        <span class="badge bg-danger">إسعاف</span>
                                        @break
                                    @case('fire')
                                        <span class="badge bg-warning">إطفاء</span>
                                        @break
                                    @case('security')
                                        <span class="badge bg-info">أمن</span>
                                        @break
                                    @case('first_aid')
                                        <span class="badge bg-success">إسعافات أولية</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">أخرى</span>
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>متاح 24 ساعة:</th>
                            <td>
                                @if($emergencyContact->is_24_hours)
                                    <span class="badge bg-success">نعم</span>
                                @else
                                    <span class="badge bg-secondary">لا</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>الترتيب:</th>
                            <td>{{ $emergencyContact->display_order ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($emergencyContact->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">اسم الخدمة</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->service_name_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->service_name }}</p>
                    </div>
                    
                    @if($emergencyContact->description_ar || $emergencyContact->description)
                    <h5 class="font-weight-bold mt-4">الوصف</h5>
                    <div class="mb-3">
                        @if($emergencyContact->description_ar)
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->description_ar }}</p>
                        @endif
                        @if($emergencyContact->description)
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->description }}</p>
                        @endif
                    </div>
                    @endif
                    
                    @if($emergencyContact->location_ar || $emergencyContact->location)
                    <h5 class="font-weight-bold mt-4">الموقع</h5>
                    <div class="mb-3">
                        @if($emergencyContact->location_ar)
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->location_ar }}</p>
                        @endif
                        @if($emergencyContact->location)
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->location }}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOF

echo "Emergency contacts views created successfully!"

# Check if map-locations views need to be created
if [ ! -f "resources/views/admin/map-locations/index.blade.php" ]; then
    echo "Map locations views already exist, skipping..."
else
    echo "Creating map-locations views..."
fi