{{-- Public share page of a place to visit (share_url in docs/places_content_contract.md §3). --}}
@extends('layouts.public')
@php
    $lang = \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $wide = false;
    $name = $place->t('name', $lang);
    $summary = $place->t('summary', $lang);
    $description = \App\Support\HtmlSanitizer::clean($place->t('description', $lang));
    $cover = \App\Support\MediaPresenter::image($place->getFirstMedia('cover'));
    $category = $place->category?->t('name', $lang);
    $wilayat = $place->t('wilayat', $lang);
    $hours = $place->t('opening_hours', $lang);
    $directions = 'https://www.google.com/maps/search/?api=1&query='.$place->latitude.','.$place->longitude;
@endphp
@section('title', $name)
@section('band-sub', $category ?: ($isAr ? 'أماكن للزيارة' : 'Places to visit'))
@section('band')
    <h1>{{ $name }}</h1>
    @if($wilayat)<p>{{ $wilayat }}</p>@endif
@endsection

@section('content')
    <article class="s-card">
        @if($cover)
            <img src="{{ $cover['url'] }}" alt="{{ $name }}" style="width:100%;border-radius:12px;margin-bottom:16px;aspect-ratio:16/10;object-fit:cover">
        @endif
        @if($summary)<p><strong>{{ $summary }}</strong></p>@endif
        @if($description){!! $description !!}@endif
        @if($hours)<p>{{ $isAr ? 'المواعيد' : 'Opening hours' }}: {{ $hours }}</p>@endif
        <p><a href="{{ $directions }}" target="_blank" rel="noopener">{{ $isAr ? 'الاتجاهات في الخريطة' : 'Directions on the map' }}</a></p>
    </article>
@endsection
