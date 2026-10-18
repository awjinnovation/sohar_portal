{{-- Customer-facing pages (payment results, tickets, legal). Mobile-first, tenant-branded:
     brand header band + overlapping sheet, bilingual direction per locale.
     Child sets: $lang (App\View\PublicPage::locale($brand)), optional $wide (bool), $hideLangSwitch. --}}
@php
    $lang = $lang ?? \App\View\PublicPage::locale($brand ?? null);
    $isAr = $lang === 'ar';
    $tt = \App\View\TenantTheme::for($brand ?? null);
    $pbName = $brand?->localizedName($lang) ?? config('app.name');
    $pbLogo = $tt['logo_needs_chip'] ? ($tt['logo'] ?? null) : ($tt['logo_on_primary'] ?? null);
    $pbOrg = $brand?->setting('tenant.organization.'.$lang) ?: $brand?->setting('tenant.organization.'.($isAr ? 'en' : 'ar'));
    $pbEmail = $brand?->setting('tenant.support.email');
    $pbPhone = $brand?->setting('tenant.support.phone');
    $pbLocales = (array) ($brand?->setting('tenant.locales', ['ar', 'en']) ?? ['ar', 'en']);
    $legalQuery = array_filter(['tenant' => $brand?->slug, 'lang' => $lang]);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $tt['shared']['header-start'] }}">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $pbName }}</title>
    @if($tt['icon'])<link rel="icon" href="{{ $tt['icon'] }}">@endif
    @include('partials.theme-tokens', ['themeTenant' => $brand ?? null])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/public.css') }}?v={{ @filemtime(public_path('css/public.css')) }}">
    @stack('head')
</head>
<body class="{{ ! empty($wide) ? 'is-wide' : '' }}">
    <header class="pb-band {{ $tt['header_image'] ? 'has-image' : '' }}" @if($tt['header_image']) style="--header-image: url('{{ $tt['header_image'] }}')" @endif>
        <div class="pb-wrap pb-top">
            <span class="pb-logo {{ $tt['logo_needs_chip'] ? 'chip' : '' }}">
                @if($pbLogo)<img src="{{ $pbLogo }}" alt="">@else<i class="bi bi-calendar2-heart"></i>@endif
            </span>
            <span class="pb-name">
                <b>{{ $pbName }}</b>
                @hasSection('band-sub')<span>@yield('band-sub')</span>@endif
            </span>
            @if(empty($hideLangSwitch) && count(array_intersect(['ar', 'en'], $pbLocales)) > 1)
                <a class="pb-icon-btn" href="{{ \App\View\PublicPage::langUrl($isAr ? 'en' : 'ar') }}" lang="{{ $isAr ? 'en' : 'ar' }}">
                    <i class="bi bi-translate"></i><span>{{ $isAr ? 'English' : 'العربية' }}</span>
                </a>
            @endif
        </div>
        @hasSection('band')
            <div class="pb-wrap pb-band-content">@yield('band')</div>
        @endif
    </header>

    <main class="pb-sheet">
        <div class="pb-wrap">
            @yield('content')
        </div>
    </main>

    <footer class="pb-foot">
        <div class="pb-wrap">
            @if($pbOrg)<div class="pb-org">{{ $isAr ? 'تحت إدارة' : 'Managed by' }} {{ $pbOrg }}</div>@endif
            <nav class="pb-links">
                @if(\Illuminate\Support\Facades\Route::has('public.privacy-policy'))
                    <a href="{{ route('public.privacy-policy', $legalQuery) }}"><i class="bi bi-shield-check"></i> {{ $isAr ? 'سياسة الخصوصية' : 'Privacy Policy' }}</a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('public.terms-and-conditions'))
                    <a href="{{ route('public.terms-and-conditions', $legalQuery) }}"><i class="bi bi-file-earmark-text"></i> {{ $isAr ? 'الشروط والأحكام' : 'Terms & Conditions' }}</a>
                @endif
                @if($pbEmail)<a href="mailto:{{ $pbEmail }}"><i class="bi bi-envelope"></i> <span class="ltr">{{ $pbEmail }}</span></a>@endif
                @if($pbPhone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $pbPhone) }}"><i class="bi bi-telephone"></i> <span class="ltr">{{ $pbPhone }}</span></a>@endif
            </nav>
            <div class="pb-platform">© {{ date('Y') }} {{ $pbName }}</div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
