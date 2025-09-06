@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة فئة الموقع جديد</h1>
        <a href="{{ route('admin.location-categories.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.location-categories.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('admin.location-categories._form')
                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
