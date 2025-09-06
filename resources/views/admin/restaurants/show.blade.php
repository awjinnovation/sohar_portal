@extends('layouts.admin')

@section('title', 'عرض المطعم')
@section('page-title', 'تفاصيل المطعم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">معلومات المطعم</h5>
                        <div>
                            <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil"></i>
                                تعديل
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">الاسم بالعربية</label>
                            <h5>{{ $restaurant->name_ar }}</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">الاسم بالإنجليزية</label>
                            <h5>{{ $restaurant->name }}</h5>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="text-muted small">الوصف</label>
                            <p>{{ $restaurant->description_ar }}</p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <label class="text-muted small">نوع المطبخ</label>
                            <p>{{ $restaurant->cuisine_ar }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">الموقع</label>
                            <p>{{ $restaurant->location_ar }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">التقييم</label>
                            <p>
                                {{ $restaurant->rating }} <i class="bi bi-star-fill text-warning"></i>
                                <small class="text-muted">({{ $restaurant->total_ratings }} تقييم)</small>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">مستوى السعر</label>
                            <p>{{ $restaurant->price_range }}</p>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <label class="text-muted small">الهاتف</label>
                            <p>{{ $restaurant->phone ?? 'غير محدد' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">الموقع الإلكتروني</label>
                            <p>
                                @if($restaurant->website)
                                <a href="{{ $restaurant->website }}" target="_blank">{{ $restaurant->website }}</a>
                                @else
                                غير محدد
                                @endif
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">الحالة</label>
                            <p>
                                @if($restaurant->is_active)
                                <span class="badge bg-success">نشط</span>
                                @else
                                <span class="badge bg-secondary">غير نشط</span>
                                @endif
                                @if($restaurant->is_featured)
                                <span class="badge bg-warning">مميز</span>
                                @endif
                                @if($restaurant->is_open)
                                <span class="badge bg-info">مفتوح</span>
                                @else
                                <span class="badge bg-danger">مغلق</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">ساعات العمل</h5>
                </div>
                <div class="card-body">
                    @if($restaurant->openingHours && $restaurant->openingHours->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($restaurant->openingHours as $hours)
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $hours->day_of_week }}</strong>
                                @if($hours->is_closed)
                                <span class="text-danger">مغلق</span>
                                @else
                                <span>{{ $hours->open_time }} - {{ $hours->close_time }}</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted text-center">لم يتم تحديد ساعات العمل</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">المميزات</h5>
                </div>
                <div class="card-body">
                    @if($restaurant->features && $restaurant->features->count() > 0)
                        @foreach($restaurant->features as $feature)
                        <span class="badge bg-primary me-1 mb-1">{{ $feature->feature_ar }}</span>
                        @endforeach
                    @else
                    <p class="text-muted text-center">لا توجد مميزات محددة</p>
                    @endif
                </div>
            </div>

            @if($restaurant->images && $restaurant->images->count() > 0)
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">الصور</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach($restaurant->images->take(4) as $image)
                        <div class="col-6">
                            <img src="{{ $image->image_url }}" class="img-fluid rounded" alt="صورة المطعم">
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection