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
