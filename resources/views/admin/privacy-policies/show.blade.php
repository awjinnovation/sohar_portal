@extends('layouts.admin')

@section('title', 'عرض سياسة الخصوصية')
@section('page-title', 'عرض سياسة الخصوصية')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="mb-3">
            <strong>اللغة:</strong>
            <span class="badge bg-info">{{ strtoupper($privacyPolicy->language) }}</span>
        </div>

        <div class="mb-3">
            <strong>العنوان:</strong>
            <p>{{ $privacyPolicy->title }}</p>
        </div>

        <div class="mb-3">
            <strong>الحالة:</strong>
            @if($privacyPolicy->is_active)
                <span class="badge bg-success">نشط</span>
            @else
                <span class="badge bg-secondary">غير نشط</span>
            @endif
        </div>

        <div class="mb-3">
            <strong>المحتوى:</strong>
            <div class="border p-3 rounded mt-2" style="min-height: 200px;">
                {!! nl2br(e($privacyPolicy->content)) !!}
            </div>
        </div>

        <div class="mb-3">
            <strong>تاريخ الإنشاء:</strong>
            <p>{{ $privacyPolicy->created_at->format('Y-m-d H:i:s') }}</p>
        </div>

        <div class="mb-3">
            <strong>آخر تحديث:</strong>
            <p>{{ $privacyPolicy->updated_at->format('Y-m-d H:i:s') }}</p>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.privacy-policies.index') }}" class="btn btn-secondary">رجوع</a>
            <div>
                <a href="{{ route('admin.privacy-policies.edit', $privacyPolicy) }}" class="btn btn-warning">تعديل</a>
                <form action="{{ route('admin.privacy-policies.destroy', $privacyPolicy) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه السياسة؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
