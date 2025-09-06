@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة موقع التصوير جديد</h1>
        <a href="{{ route('admin.photo-spots.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.photo-spots.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('admin.photo-spots._form')
                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
