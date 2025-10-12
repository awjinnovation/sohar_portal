@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-plus-circle text-primary"></i> إضافة موقع جديد على الخريطة
            </h1>
            <p class="text-muted mb-0">أضف موقعاً جديداً للظهور على الخريطة التفاعلية</p>
        </div>
        <a href="{{ route('admin.map-locations.index') }}" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> هناك بعض الأخطاء!</h5>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('admin.map-locations.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        @include('admin.map-locations._form')

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> حفظ الموقع
                    </button>
                    <a href="{{ route('admin.map-locations.index') }}" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle"></i> إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
