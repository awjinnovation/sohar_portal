@extends('layouts.admin')

@section('title', 'عرض الموقع')
@section('page-title', 'تفاصيل الموقع')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">تفاصيل الموقع</h5>
                    <div>
                        <a href="{{ route('admin.locations.edit', $location) }}" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> تعديل
                        </a>
                        <a href="{{ route('admin.locations.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">الاسم (English)</h6>
                            <p class="lead">{{ $location->name }}</p>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">الاسم (عربي)</h6>
                            <p class="lead">{{ $location->name_ar }}</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">نوع الموقع</h6>
                            @php
                                $typeLabels = [
                                    'restaurant' => 'مطعم',
                                    'activity' => 'نشاط',
                                    'service' => 'خدمة',
                                    'emergency' => 'طوارئ',
                                    'parking' => 'موقف سيارات',
                                    'restroom' => 'دورة مياه',
                                    'first_aid' => 'إسعاف أولي'
                                ];
                                $typeColors = [
                                    'restaurant' => 'success',
                                    'activity' => 'info',
                                    'service' => 'primary',
                                    'emergency' => 'danger',
                                    'parking' => 'secondary',
                                    'restroom' => 'warning',
                                    'first_aid' => 'danger'
                                ];
                            @endphp
                            <p>
                                <span class="badge bg-{{ $typeColors[$location->type] ?? 'secondary' }} fs-6">
                                    {{ $typeLabels[$location->type] ?? $location->type }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">الحالة</h6>
                            <p>
                                @if($location->is_active)
                                    <span class="badge bg-success fs-6">نشط</span>
                                @else
                                    <span class="badge bg-secondary fs-6">غير نشط</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">الوصف (English)</h6>
                            <p>{{ $location->description ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">الوصف (عربي)</h6>
                            <p>{{ $location->description_ar ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">العنوان (English)</h6>
                            <p>{{ $location->address ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h6 class="text-muted mb-2">العنوان (عربي)</h6>
                            <p>{{ $location->address_ar ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <h6 class="text-muted mb-2">رقم الاتصال</h6>
                            <p>
                                @if($location->contact_number)
                                    <a href="tel:{{ $location->contact_number }}" class="text-decoration-none">
                                        <i class="bi bi-telephone"></i> {{ $location->contact_number }}
                                    </a>
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h6 class="text-muted mb-2">خط العرض</h6>
                            <p>{{ $location->latitude ?? '-' }}</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h6 class="text-muted mb-2">خط الطول</h6>
                            <p>{{ $location->longitude ?? '-' }}</p>
                        </div>
                    </div>

                    @if($location->latitude && $location->longitude)
                    <div class="row">
                        <div class="col-12 mb-4">
                            <h6 class="text-muted mb-2">الخريطة</h6>
                            <div class="ratio ratio-16x9" style="max-height: 400px;">
                                <iframe
                                    src="https://maps.google.com/maps?q={{ $location->latitude }},{{ $location->longitude }}&hl=ar&z=15&output=embed"
                                    style="border:0; border-radius: 8px;"
                                    allowfullscreen=""
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($location->additional_info && is_array($location->additional_info))
                    <div class="row">
                        <div class="col-12 mb-4">
                            <h6 class="text-muted mb-2">معلومات إضافية</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    @foreach($location->additional_info as $key => $value)
                                    <tr>
                                        <th style="width: 200px;">{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                                        <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <h6 class="text-muted mb-2">تاريخ الإنشاء</h6>
                            <p>{{ $location->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="col-md-6 mb-2">
                            <h6 class="text-muted mb-2">آخر تحديث</h6>
                            <p>{{ $location->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <form action="{{ route('admin.locations.destroy', $location) }}" method="POST"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> حذف الموقع
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
