@php
    $adminLocale = \App\Http\Middleware\SetAdminLocale::current();
    $adminDir = $adminLocale === 'ar' ? 'rtl' : 'ltr';
    $tt = \App\View\TenantTheme::for($brand ?? null);
    $brandName = $brand?->localizedName($adminLocale) ?? config('app.name');
    $brandMark = $tt['icon'] ?? null;
    $panelTenants = collect($adminTenants ?? []);
    $user = auth()->user();
    $isSuper = (bool) $user?->isSuperAdmin();
    $pageTitle = __(trim($__env->yieldContent('page-title')) ?: trim($__env->yieldContent('title')) ?: 'لوحة التحكم');
    $pendingRefunds = 0;
    try {
        if (\Illuminate\Support\Facades\Route::has('admin.refund-requests.index') && $user?->can('manage refunds')) {
            $pendingRefunds = \App\Models\RefundRequest::where('status', 'pending')->count();
        }
    } catch (\Throwable $e) { $pendingRefunds = 0; }

    // [route, active pattern, icon, label, permission (null = any panel user), badge]
    $menuSections = [
        ['title' => 'نظرة عامة', 'items' => [
            ['admin.dashboard', 'admin.dashboard', 'grid-1x2', 'لوحة التحكم', null],
        ]],
        ['title' => 'الفعاليات والتذاكر', 'items' => [
            ['admin.events.index', 'admin.events.*', 'calendar-event', 'الفعاليات', 'manage events'],
            ['admin.tickets.index', 'admin.tickets.*', 'ticket-perforated', 'التذاكر', 'manage tickets'],
            ['admin.categories.index', 'admin.categories.*', 'grid', 'الفئات', 'manage events'],
            ['admin.event-tags.index', 'admin.event-tags.*', 'tags', 'الوسوم', 'manage events'],
        ]],
        ['title' => 'المحفظة والمدفوعات', 'items' => [
            ['admin.wallets.index', 'admin.wallets.*', 'wallet2', 'المحافظ', 'manage wallets'],
            ['admin.payments.index', 'admin.payments.*', 'credit-card-2-front', 'المدفوعات', 'view payments'],
            ['admin.refund-requests.index', 'admin.refund-requests.*', 'arrow-counterclockwise', 'طلبات الاسترداد', 'manage refunds', $pendingRefunds],
            ['admin.deposits.index', 'admin.deposits.*', 'safe2', 'التأمينات', 'manage deposits'],
            ['admin.vendors.index', 'admin.vendors.*', 'shop-window', 'التجار', 'manage vendors'],
            ['admin.points.index', 'admin.points.*', 'stars', 'النقاط', 'manage wallets'],
        ]],
        ['title' => 'المحتوى', 'items' => [
            ['admin.announcements.index', 'admin.announcements.*', 'megaphone', 'الإعلانات', 'manage announcements'],
            ['admin.notifications.index', 'admin.notifications.*', 'bell', 'الإشعارات', 'manage notifications'],
            ['admin.map-locations.index', 'admin.map-locations.*', 'geo-alt', 'مواقع الخريطة', 'manage locations'],
            ['admin.restaurants.index', 'admin.restaurants.*', 'cup-hot', 'المطاعم والأكشاك', 'manage restaurants'],
            ['admin.privacy-policies.index', 'admin.privacy-policies.*', 'shield-check', 'سياسة الخصوصية', 'manage settings'],
            ['admin.terms-and-conditions.index', 'admin.terms-and-conditions.*', 'file-earmark-text', 'الشروط والأحكام', 'manage settings'],
        ]],
        ['title' => 'المنظمة', 'items' => [
            ['admin.branding.edit', 'admin.branding.*', 'palette', 'الهوية والإعدادات', 'manage settings'],
            ['admin.users.index', 'admin.users.*', 'people', 'المستخدمون', 'manage users'],
            ['admin.roles.index', 'admin.roles.*', 'shield-lock', 'الأدوار والصلاحيات', 'manage roles'],
            ['admin.app-settings.index', 'admin.app-settings.*', 'sliders', 'إعدادات التطبيق', 'manage settings'],
        ]],
        ['title' => 'المنصة', 'items' => [
            ['admin.tenants.index', 'admin.tenants.*', 'buildings', 'المنظمات', 'manage tenants'],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $adminLocale }}" dir="{{ $adminDir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $tt['shared']['header-start'] }}">
    <title>{{ __(trim($__env->yieldContent('title')) ?: $pageTitle) }} · {{ $brandName }}</title>
    @if($brandMark)
        <link rel="icon" href="{{ $brandMark }}">
    @endif
    @include('partials.theme-tokens', ['themeTenant' => $brand ?? null])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/{{ $adminDir === 'rtl' ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css' }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
    @stack('styles')
</head>
<body>
<a href="#main" class="visually-hidden-focusable btn btn-primary position-fixed m-2" style="z-index:2000">{{ __('تخطي إلى المحتوى') }}</a>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="{{ __('القائمة الرئيسية') }}">
        <div class="sidebar-head">
            @php
                $tenantInitial = mb_substr($brandName, 0, 1);
            @endphp
            @if($panelTenants->count() > 1)
                <div class="dropdown">
                    <button class="tenant-card" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('التبديل بين المنظمات') }}">
                        <span class="tenant-mark">@if($brandMark)<img src="{{ $brandMark }}" alt="">@else<span class="initial">{{ $tenantInitial }}</span>@endif</span>
                        <span class="tenant-meta">
                            <span class="tenant-name d-block">{{ $brandName }}</span>
                            <span class="tenant-sub d-block">{{ $isSuper ? __('مشرف المنصة') : __('لوحة الإدارة') }} · {{ __(':count منظمات', ['count' => $panelTenants->count()]) }}</span>
                        </span>
                        <i class="bi bi-chevron-expand chev"></i>
                    </button>
                    <ul class="dropdown-menu tenant-menu">
                        <li><h6 class="dropdown-header">{{ __('التبديل بين المنظمات') }}</h6></li>
                        @foreach($panelTenants as $panelTenant)
                            @php $ptTheme = \App\View\TenantTheme::for($panelTenant); @endphp
                            <li>
                                <form method="POST" action="{{ route('admin.tenant.switch') }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="tenant_id" value="{{ $panelTenant->id }}">
                                    <button type="submit" class="dropdown-item {{ $currentTenant?->id === $panelTenant->id ? 'active' : '' }}">
                                        <span class="tenant-dot" style="background: linear-gradient(135deg, {{ $ptTheme['shared']['header-start'] }}, {{ $ptTheme['shared']['header-end'] }});"></span>
                                        <span class="flex-grow-1 text-truncate">{{ $panelTenant->localizedName($adminLocale) }}
                                            <small class="d-block text-muted ltr" style="font-size:11px">{{ $panelTenant->slug }}</small></span>
                                        @if($currentTenant?->id === $panelTenant->id)<i class="bi bi-check2"></i>@endif
                                    </button>
                                </form>
                            </li>
                        @endforeach
                        @can('manage tenants')
                            @if(\Illuminate\Support\Facades\Route::has('admin.tenants.index'))
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('admin.tenants.index') }}"><i class="bi bi-buildings"></i> {{ __('إدارة المنظمات') }}</a></li>
                            @endif
                        @endcan
                    </ul>
                </div>
            @else
                <div class="tenant-card">
                    <span class="tenant-mark">@if($brandMark)<img src="{{ $brandMark }}" alt="">@else<span class="initial">{{ $tenantInitial }}</span>@endif</span>
                    <span class="tenant-meta">
                        <span class="tenant-name d-block">{{ $brandName }}</span>
                        <span class="tenant-sub d-block">{{ __('لوحة الإدارة') }}</span>
                    </span>
                </div>
            @endif
        </div>

        <nav class="sidebar-nav">
            @foreach($menuSections as $menuSection)
                @php
                    $menuItems = array_values(array_filter($menuSection['items'], function ($item) use ($user) {
                        if (! \Illuminate\Support\Facades\Route::has($item[0])) return false;
                        return $item[4] === null || ($user && $user->can($item[4]));
                    }));
                @endphp
                @if($menuItems)
                    <div class="nav-group">
                        <div class="nav-group-title">{{ __($menuSection['title']) }}</div>
                        @foreach($menuItems as $menuItem)
                            @php $isActive = request()->routeIs($menuItem[1]); @endphp
                            <a href="{{ route($menuItem[0]) }}" class="nav-item-link {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                                <i class="bi bi-{{ $menuItem[2] }}"></i>
                                <span>{{ __($menuItem[3]) }}</span>
                                @if(! empty($menuItem[5]))
                                    <span class="nav-count" title="{{ __('بانتظار المراجعة') }}">{{ $menuItem[5] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-foot">
            <img src="{{ asset('images/platform-logo.svg') }}" alt="">
            <span>{{ config('app.name') }}</span>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <div class="masthead {{ $tt['header_image'] ? 'has-image' : '' }}" @if($tt['header_image']) style="--header-image: url('{{ $tt['header_image'] }}')" @endif>
            <header class="topbar">
                <button class="tb-btn tb-burger" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('القائمة') }}">
                    <i class="bi bi-list"></i>
                </button>
                <span class="tb-tenant d-none d-lg-inline">{{ $brandName }}</span>
                <span class="spacer"></span>

                <div class="dropdown">
                    <button class="tb-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('المظهر') }}" title="{{ __('المظهر') }}">
                        <i class="bi bi-sun" data-theme-icon></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ __('المظهر') }}</h6></li>
                        <li><button class="dropdown-item" type="button" data-theme-set="light"><i class="bi bi-sun"></i> {{ __('فاتح') }}</button></li>
                        <li><button class="dropdown-item" type="button" data-theme-set="dark"><i class="bi bi-moon-stars"></i> {{ __('داكن') }}</button></li>
                        <li><button class="dropdown-item" type="button" data-theme-set="auto"><i class="bi bi-circle-half"></i> {{ __('تلقائي') }} ({{ __(['system' => 'حسب النظام', 'light' => 'فاتح', 'dark' => 'داكن'][$tt['mode']]) }})</button></li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('admin.locale') }}" class="m-0">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $adminLocale === 'ar' ? 'en' : 'ar' }}">
                    <button class="tb-btn" type="submit" title="{{ $adminLocale === 'ar' ? 'English' : 'العربية' }}" aria-label="{{ $adminLocale === 'ar' ? 'Switch to English' : 'التبديل إلى العربية' }}" data-locale-switch>
                        <i class="bi bi-translate"></i><span class="ms-1 small fw-bold" @if($adminLocale === 'ar') dir="ltr" lang="en" @else lang="ar" @endif>{{ $adminLocale === 'ar' ? 'EN' : 'ع' }}</span>
                    </button>
                </form>

                <div class="dropdown">
                    <button class="tb-btn tb-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="tb-avatar">{{ mb_substr($user->name ?? 'A', 0, 1) }}</span>
                        <span class="tb-name">{{ $user->name ?? 'Admin' }}</span>
                        <i class="bi bi-chevron-down" style="font-size: 11px"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 240px">
                        <li class="px-3 py-2">
                            <div class="fw-bold">{{ $user->name ?? 'Admin' }}</div>
                            <div class="small text-muted ltr text-truncate">{{ $user->email ?? '' }}</div>
                            <div class="mt-2"><span class="pill pill-brand pill-sm">{{ $isSuper ? __('مشرف المنصة') : ($user?->roles->first()->name ?? __('مسؤول')) }}</span></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @can('manage settings')
                            <li><a class="dropdown-item" href="{{ route('admin.branding.edit') }}"><i class="bi bi-palette"></i> {{ __('الهوية والإعدادات') }}</a></li>
                        @endcan
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-left dir-icon"></i> {{ __('تسجيل الخروج') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <x-admin.page-header :title="$pageTitle" :subtitle="trim($__env->yieldContent('page-subtitle'))" :icon="$pageIcon ?? null" :breadcrumbs="$breadcrumbs ?? []">
                @hasSection('page-actions')
                    <x-slot:actions>@yield('page-actions')</x-slot:actions>
                @endif
            </x-admin.page-header>
        </div>

        <main class="content" id="main">
            @yield('content')
        </main>
        <footer class="app-footer">
            <span>{{ $brandName }} · {{ now()->year }}</span>
            <span class="ltr">{{ config('app.name') }}</span>
        </footer>
    </div>
</div>

<x-admin.toast />
<x-admin.confirm-modal />

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/admin.js') }}?v={{ @filemtime(public_path('js/admin.js')) }}"></script>
@stack('scripts')
</body>
</html>
