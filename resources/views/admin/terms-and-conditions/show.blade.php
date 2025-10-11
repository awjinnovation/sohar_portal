@extends('layouts.admin')

@section('title', 'عرض الشروط والأحكام')
@section('page-title', 'عرض الشروط والأحكام')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="mb-3">
            <strong>النوع:</strong>
            <span class="badge bg-primary">{{ ucfirst($termsAndCondition->type) }}</span>
        </div>

        <div class="mb-3">
            <strong>اللغة:</strong>
            <span class="badge bg-info">{{ strtoupper($termsAndCondition->language) }}</span>
        </div>

        <div class="mb-3">
            <strong>العنوان:</strong>
            <p>{{ $termsAndCondition->title }}</p>
        </div>

        <div class="mb-3">
            <strong>الحالة:</strong>
            @if($termsAndCondition->is_active)
                <span class="badge bg-success">نشط</span>
            @else
                <span class="badge bg-secondary">غير نشط</span>
            @endif
        </div>

        <div class="mb-3">
            <strong>المحتوى:</strong>
            <div class="border p-3 rounded mt-2" style="min-height: 200px;">
                {!! nl2br(e($termsAndCondition->content)) !!}
            </div>
        </div>

        <div class="mb-3">
            <strong>تاريخ الإنشاء:</strong>
            <p>{{ $termsAndCondition->created_at->format('Y-m-d H:i:s') }}</p>
        </div>

        <div class="mb-3">
            <strong>آخر تحديث:</strong>
            <p>{{ $termsAndCondition->updated_at->format('Y-m-d H:i:s') }}</p>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.terms-and-conditions.index') }}" class="btn btn-secondary">رجوع</a>
            <div>
                <a href="{{ route('admin.terms-and-conditions.edit', $termsAndCondition) }}" class="btn btn-warning">تعديل</a>
                <form action="{{ route('admin.terms-and-conditions.destroy', $termsAndCondition) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الشروط؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
