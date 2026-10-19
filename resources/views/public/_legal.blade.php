{{-- Shared legal page body. Expects $doc (PrivacyPolicy|TermsAndCondition), $titleAr, $titleEn. --}}
@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $wide = true;
    $docTitle = $isAr ? ($doc->title_ar ?: $titleAr) : ($doc->title ?: $titleEn);
    $updated = $doc->updated_at ?? $doc->created_at;
@endphp
@section('title', $docTitle)
@section('band-sub', $isAr ? 'المستندات القانونية' : 'Legal')
@section('band')
    <h1>{{ $docTitle }}</h1>
    @if($updated)
        <p class="num">{{ $isAr ? 'آخر تحديث' : 'Last updated' }}: {{ $updated->locale($lang)->translatedFormat('j F Y') }}</p>
    @endif
@endsection

@section('content')
    <article class="s-card legal">
        {!! \App\Support\HtmlSanitizer::clean($isAr ? ($doc->content_ar ?: $doc->content) : ($doc->content ?: $doc->content_ar)) !!}
    </article>
@endsection
