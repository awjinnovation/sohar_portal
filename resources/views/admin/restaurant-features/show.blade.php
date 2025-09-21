@extends('layouts.admin')

@section('title', 'تفاصيل ميزة المطعم')
@section('page-title', 'تفاصيل ميزة المطعم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">معلومات الميزة</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="200">المعرف:</th>
                            <td>{{ $restaurantFeature->id }}</td>
                        </tr>
                        <tr>
                            <th>المطعم:</th>
                            <td>{{ $restaurantFeature->restaurant->name ?? 'غير محدد' }}</td>
                        </tr>
                        <tr>
                            <th>الميزة (عربي):</th>
                            <td>{{ $restaurantFeature->feature_ar }}</td>
                        </tr>
                        <tr>
                            <th>الميزة (انجليزي):</th>
                            <td>{{ $restaurantFeature->feature }}</td>
                        </tr>
                        <tr>
                            <th>تاريخ الإنشاء:</th>
                            <td>{{ $restaurantFeature->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>آخر تحديث:</th>
                            <td>{{ $restaurantFeature->updated_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">الإجراءات</h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.restaurant-features.edit', $restaurantFeature) }}"
                       class="btn btn-warning btn-block mb-2">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <a href="{{ route('admin.restaurant-features.index') }}"
                       class="btn btn-secondary btn-block">
                        <i class="bi bi-arrow-right"></i> رجوع للقائمة
                    </a>

                    <hr>

                    <form action="{{ route('admin.restaurant-features.destroy', $restaurantFeature) }}"
                          method="POST"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذه الميزة؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="bi bi-trash"></i> حذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
