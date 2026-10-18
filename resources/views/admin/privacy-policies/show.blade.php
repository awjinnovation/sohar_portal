@extends('layouts.admin')

@section('title', 'عرض سياسة الخصوصية')
@section('page-title', 'عرض سياسة الخصوصية')
@section('page-subtitle', $privacyPolicy->title_ar)
@php
    $pageIcon = 'shield-check';
    $breadcrumbs = ['المحتوى' => null, 'سياسة الخصوصية' => route('admin.privacy-policies.index'), 'عرض' => null];
    // Admin preview of the editor HTML: only simple formatting tags, every attribute dropped (no links, images or scripts).
    $legalHtml = function ($html) {
        $html = (string) $html;
        if (! str_contains($html, '<')) {
            return new \Illuminate\Support\HtmlString(nl2br(e($html)));
        }
        $clean = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><blockquote><hr><table><thead><tbody><tr><th><td>');

        return new \Illuminate\Support\HtmlString(preg_replace('/<(\w+)\b[^>]*>/', '<$1>', $clean));
    };
@endphp
@section('page-actions')
    <a href="{{ route('admin.privacy-policies.edit', $privacyPolicy) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.privacy-policies.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@push('styles')
<style>
    .legal-body { line-height: 2; font-size: 15px; max-width: 72ch; overflow-wrap: anywhere; }
    .legal-body[dir="ltr"] { line-height: 1.75; }
    .legal-body h1, .legal-body h2, .legal-body h3, .legal-body h4 { font-size: 17px; font-weight: 700; margin: 1.4em 0 .5em; }
    .legal-body > :first-child { margin-top: 0; }
    .legal-body p, .legal-body ul, .legal-body ol { margin-bottom: 1em; }
    .legal-body ul, .legal-body ol { padding-inline-start: 1.4em; }
    .legal-body blockquote { border-inline-start: 3px solid var(--hairline); padding-inline-start: 12px; color: var(--muted); }
    .legal-body table { width: 100%; margin-bottom: 1em; }
    .legal-body th, .legal-body td { border: 1px solid var(--hairline); padding: 6px 10px; }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="نص المستند" icon="file-earmark-text" subtitle="معاينة النص المنسق كما يحرره المسؤول">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-4">
                    <div class="tab-pane fade show active" id="ar" role="tabpanel">
                        <h3 class="h5 fw-bold mb-3">{{ $privacyPolicy->title_ar }}</h3>
                        <div class="legal-body" dir="rtl">{{ $legalHtml($privacyPolicy->content_ar) }}</div>
                    </div>
                    <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                        <h3 class="h5 fw-bold mb-3">{{ $privacyPolicy->title }}</h3>
                        <div class="legal-body" dir="ltr">{{ $legalHtml($privacyPolicy->content) }}</div>
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="التفاصيل" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('الحالة') }}</dt>
                    <dd><x-admin.status-pill domain="active" :status="$privacyPolicy->is_active" /></dd>
                    <dt>{{ __('العنوان (English)') }}</dt>
                    <dd class="ltr text-start">{{ $privacyPolicy->title }}</dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt>
                    <dd class="num">{{ $privacyPolicy->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt>{{ __('آخر تحديث') }}</dt>
                    <dd class="num">{{ $privacyPolicy->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
                <x-slot:footer>
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <a href="{{ route('admin.privacy-policies.edit', $privacyPolicy) }}" class="btn btn-tonal"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
                        <form action="{{ route('admin.privacy-policies.destroy', $privacyPolicy) }}" method="POST" class="m-0" data-confirm="{{ __('سيتم حذف هذه السياسة نهائيًا.') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('حذف') }}</button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-admin.card>
        </div>
    </div>
@endsection
