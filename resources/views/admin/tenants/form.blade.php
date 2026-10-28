@extends('layouts.admin')

@php
    $pageTitle = match ($mode) {
        'create' => 'إضافة منظمة جديدة',
        'edit' => __('تعديل المنظمة: :name', ['name' => $tenant->localizedName('ar')]),
        default => 'الهوية والإعدادات',
    };
    $pageIcon = 'palette';
    $breadcrumbs = $platform
        ? ['المنصة' => null, 'المنظمات' => route('admin.tenants.index'), ($mode === 'create' ? 'منظمة جديدة' : $tenant->localizedName('ar')) => null]
        : ['المنظمة' => null, 'الهوية والإعدادات' => null];
    $colorGroups = [
        'brand' => ['title' => 'ألوان العلامة', 'help' => 'لا يلزم إلا اللون الأساسي، ويفحصه حارس التباين تلقائيًا ويغمّقه قليلًا عند الحاجة.', 'keys' => ['primary', 'on_primary', 'secondary', 'on_secondary', 'tertiary']],
        'wallet' => ['title' => 'بطاقة المحفظة', 'help' => 'تحمل نصًا أبيض دائمًا؛ يُغمَّق كل طرف حتى يصل التباين إلى 4.5:1.', 'keys' => ['wallet_card_start', 'wallet_card_end']],
        'neutral' => ['title' => 'الأسطح والنصوص', 'help' => 'تُقبل إذا اجتازت حارس التباين، وإلا اعتُمدت قيم المنصة.', 'keys' => ['background', 'surface', 'on_surface', 'muted', 'outline']],
        'semantic' => ['title' => 'ألوان الحالات (مقفلة في التطبيق)', 'help' => 'ألوان النجاح والتحذير والخطأ والمعلومات ثابتة في التطبيق على مستوى المنصة، ليُقرأ المال والحالة بالطريقة نفسها في كل منظمة. أما هذه القيم فتُستعمل في البريد ولوحة التحكم فقط.', 'keys' => ['success', 'warning', 'error', 'info']],
    ];
    $colorLabels = [
        'primary' => 'الأساسي', 'on_primary' => 'النص على الأساسي',
        'secondary' => 'الثانوي (تمييز)', 'on_secondary' => 'النص على الثانوي',
        'tertiary' => 'الثالث (اختياري)', 'info' => 'معلومات (اختياري)',
        'background' => 'الخلفية', 'surface' => 'الأسطح', 'on_surface' => 'النص على الأسطح',
        'muted' => 'النص الخافت', 'outline' => 'الحدود',
        'success' => 'نجاح', 'warning' => 'تحذير', 'error' => 'خطأ',
        'wallet_card_start' => 'بداية التدرج', 'wallet_card_end' => 'نهاية التدرج',
    ];
    $colorAgainst = ['primary' => '#FFFFFF', 'secondary' => '#FFFFFF', 'wallet_card_start' => '#FFFFFF', 'wallet_card_end' => '#FFFFFF', 'muted' => '#FFFFFF', 'on_surface' => '#FFFFFF'];
    $groupedKeys = collect($colorGroups)->pluck('keys')->flatten()->all();
    $extraColorKeys = array_values(array_diff($colorKeys, $groupedKeys));
    $moduleLabels = [
        'events' => 'الفعاليات', 'tickets' => 'التذاكر', 'calendar' => 'التقويم', 'map' => 'الخريطة',
        'vendors' => 'التجار', 'announcements' => 'الإعلانات', 'favorites' => 'المفضلة', 'notifications' => 'الإشعارات',
        'wallet' => 'المحفظة', 'topup' => 'شحن الرصيد', 'deposits' => 'التأمينات', 'points' => 'النقاط',
        'refunds' => 'الاسترداد', 'ticket_transfer' => 'نقل التذاكر', 'staff_scanner' => 'ماسح الموظفين', 'ai_assistant' => 'ai.admin.module_ai_assistant', 'ai_plan' => 'ai.admin.module_ai_plan',
        'places' => 'الأماكن والخريطة المخصصة', 'content' => 'المجموعات والصفحات',
    ];
    $moduleIcons = [
        'events' => 'calendar-event', 'tickets' => 'ticket-perforated', 'calendar' => 'calendar3', 'map' => 'map', 'vendors' => 'shop-window',
        'announcements' => 'megaphone', 'favorites' => 'heart', 'notifications' => 'bell', 'wallet' => 'wallet2', 'topup' => 'cash-coin',
        'deposits' => 'lock', 'points' => 'stars', 'refunds' => 'arrow-counterclockwise', 'ticket_transfer' => 'send', 'staff_scanner' => 'qr-code-scan', 'ai_assistant' => 'stars', 'ai_plan' => 'map',
        'places' => 'pin-map', 'content' => 'collection',
    ];
    $socialLabels = ['instagram' => 'إنستغرام', 'x' => 'X (تويتر)', 'tiktok' => 'تيك توك', 'youtube' => 'يوتيوب', 'facebook' => 'فيسبوك'];
    $gatewayLabels = ['merchant_id' => 'Merchant ID', 'access_code' => 'Access Code', 'working_key' => 'Working Key', 'gateway_url' => 'Gateway URL',
        'secret_key' => 'Secret key', 'publishable_key' => 'Publishable key', 'webhook_secret' => 'Webhook secret'];
    $gatewayTitles = ['thawani' => ['ثواني', 'Thawani Pay'], 'bank_muscat' => ['بنك مسقط', 'Bank Muscat']];
    $driverLabels = ['thawani' => 'ثواني', 'bankmuscat' => 'بنك مسقط', 'fake' => 'بوابة محلية للتجربة'];
    $selectedMethods = array_filter((array) old('s.wallet.refunds.methods', $tenant->setting('wallet.refunds.methods', [])));
    $logoPreview = \App\Models\Tenant::assetUrl($tenant->setting('branding.logo_url'));
    $theme = $tenant->exists ? \App\View\TenantTheme::for($tenant) : \App\View\TenantTheme::for(null);
    $sections = [
        'sec-identity' => ['building', 'الهوية'], 'sec-logos' => ['image', 'الشعارات'], 'sec-colors' => ['palette', 'الألوان'],
        'sec-type' => ['fonts', 'الخطوط والشكل'], 'sec-locale' => ['globe2', 'اللغة والتواصل'], 'sec-modules' => ['toggles', 'الوحدات'],
        'sec-map' => ['geo-alt', 'الخريطة'], 'sec-wallet' => ['wallet2', 'المحفظة'], 'sec-points' => ['stars', 'النقاط'],
        'sec-app' => ['phone', 'التطبيق'], 'sec-ai' => ['stars', 'ai.admin.ai_settings'], 'sec-gate-sales' => ['cart-check', 'مبيعات البوابات'], 'sec-billing' => ['receipt', 'الفوترة والرسائل'],
    ];
    if ($platform) { $sections['sec-gateway'] = ['shield-lock', 'بوابة الدفع']; }
    $sections['sec-advanced'] = ['code-slash', 'متقدم'];
    if ($mode === 'create') { $sections['sec-admin'] = ['person-badge', 'مدير المنظمة']; }
    $curatedFonts = array_keys(\App\View\TenantTheme::FONTS);
@endphp

@section('title', $pageTitle)
@section('page-title', $pageTitle)
@section('page-subtitle', 'كل ما يراه زوار التطبيق والموقع من اسم وشعار وألوان وخطوط ووحدات، وتتحدّث المعاينة مباشرة.')
@section('page-actions')
    <button type="submit" form="tenantForm" class="btn btn-primary" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ التغييرات') }}</button>
    <a href="{{ $platform ? route('admin.tenants.index') : route('admin.dashboard') }}" class="btn">{{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="tenantForm">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        <div class="row g-4">
            <div class="col-xl-7 col-xxl-8">
                <nav class="x-card p-2 mb-4 section-nav-bar" aria-label="{{ __('أقسام الإعدادات') }}">
                    <div class="section-nav flex-row flex-wrap" data-section-nav style="position: static">
                        @foreach($sections as $secId => [$secIcon, $secLabel])
                            <a href="#{{ $secId }}"><i class="bi bi-{{ $secIcon }}"></i>{{ __($secLabel) }}</a>
                        @endforeach
                    </div>
                </nav>

                {{-- Identity --}}
                <x-admin.card id="sec-identity" title="الهوية" icon="building" subtitle="الاسم كما يظهر في التطبيق ورأس الصفحات العامة">
                    <div class="row">
                        <div class="col-md-6 field">
                            <label class="form-label" for="name_ar">{{ __('الاسم بالعربية') }}<span class="req">*</span></label>
                            <input type="text" name="name[ar]" id="name_ar" class="form-control @error('name.ar') is-invalid @enderror"
                                   value="{{ old('name.ar', $tenant->name['ar'] ?? '') }}" required>
                            @error('name.ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 field">
                            <label class="form-label" for="name_en">{{ __('الاسم بالإنجليزية') }}<span class="req">*</span></label>
                            <input type="text" name="name[en]" id="name_en" dir="ltr" class="form-control @error('name.en') is-invalid @enderror"
                                   value="{{ old('name.en', $tenant->name['en'] ?? '') }}" required>
                            @error('name.en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if($mode === 'create')
                            <div class="col-md-4 field">
                                <label class="form-label" for="slug">{{ __('معرّف الرابط') }}<span class="req">*</span></label>
                                <input type="text" name="slug" id="slug" dir="ltr" pattern="[a-z0-9-]+" class="form-control @error('slug') is-invalid @enderror"
                                       value="{{ old('slug') }}" placeholder="my-tenant" required>
                                <div class="form-text">{{ __('أحرف إنجليزية صغيرة وأرقام وشرطة فقط. لا يمكن تغييره لاحقًا.') }}</div>
                                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <div class="col-md-4 field">
                                <label class="form-label">{{ __('معرّف الرابط') }}</label>
                                <input type="text" class="form-control" dir="ltr" value="{{ $tenant->slug }}" disabled>
                            </div>
                        @endif

                        @if($platform)
                            <div class="col-md-4 field">
                                <label class="form-label" for="status">{{ __('الحالة') }}<span class="req">*</span></label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="active" @selected(old('status', $tenant->status) === 'active')>{{ __('نشطة') }}</option>
                                    <option value="inactive" @selected(old('status', $tenant->status) === 'inactive')>{{ __('غير نشطة') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 field">
                                <label class="form-label" for="domain">{{ __('النطاق (اختياري)') }}</label>
                                <input type="text" name="domain" id="domain" dir="ltr" class="form-control @error('domain') is-invalid @enderror"
                                       value="{{ old('domain', $tenant->domain) }}" placeholder="events.example.com">
                                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        @include('admin.tenants._field', ['key' => 'tenant.tagline.ar', 'label' => 'الشعار النصي بالعربية', 'inputId' => 'tagline_ar'])
                        @include('admin.tenants._field', ['key' => 'tenant.tagline.en', 'label' => 'الشعار النصي بالإنجليزية', 'ltr' => true, 'inputId' => 'tagline_en'])
                        @include('admin.tenants._field', ['key' => 'tenant.organization.ar', 'label' => 'الجهة المنظِّمة بالعربية'])
                        @include('admin.tenants._field', ['key' => 'tenant.organization.en', 'label' => 'الجهة المنظِّمة بالإنجليزية', 'ltr' => true])
                    </div>
                </x-admin.card>

                {{-- Logos --}}
                <x-admin.card id="sec-logos" title="الشعارات والصور" icon="image" subtitle="يظهر الشعار داخل دائرة بيضاء على الشريط الملون إن لم يتوفر شعار فاتح بخلفية شفافة">
                    <x-admin.file-upload name="logo" id="logoUpload" label="رفع شعار جديد" :current="$logoPreview" accept="image/png,image/jpeg,image/webp"
                        help="PNG أو JPG أو WEBP بحجم أقصاه 2MB، ويحل محل رابط الشعار أدناه." />
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'branding.logo_url', 'label' => 'رابط الشعار أو مساره', 'ltr' => true, 'placeholder' => 'https://example.com/logo.png أو tenants/slug/logo.png', 'inputId' => 'logo_url'])
                        @include('admin.tenants._field', ['key' => 'branding.logo_on_primary_url', 'label' => 'الشعار على اللون الأساسي', 'ltr' => true, 'inputId' => 'logo_on_primary_url'])
                        @include('admin.tenants._field', ['key' => 'branding.icon_url', 'label' => 'الأيقونة', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'branding.splash_url', 'label' => 'شاشة البداية', 'ltr' => true])
                        @php
                            $chipVal = old('s.branding.logo_needs_chip', $tenant->setting('branding.logo_needs_chip'));
                            $chipVal = $chipVal === null || $chipVal === '' ? '' : (filter_var($chipVal, FILTER_VALIDATE_BOOLEAN) ? '1' : '0');
                        @endphp
                        <div class="col-md-6 field">
                            <label class="form-label" for="f_logo_needs_chip">{{ __('خلفية بيضاء للشعار') }}</label>
                            <select name="s[branding][logo_needs_chip]" id="f_logo_needs_chip" class="form-select {{ $errors->has('s.branding.logo_needs_chip') ? 'is-invalid' : '' }}">
                                <option value="" @selected($chipVal === '')>{{ __('تلقائي بحسب توفّر شعار فاتح') }}</option>
                                <option value="1" @selected($chipVal === '1')>{{ __('نعم، اعرض الشعار داخل دائرة بيضاء') }}</option>
                                <option value="0" @selected($chipVal === '0')>{{ __('لا، الشعار يناسب الشريط الملون') }}</option>
                            </select>
                            @error('s.branding.logo_needs_chip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-text">{{ __('يُقبل رابط كامل يبدأ بـ https://، أو مسار يبدأ بـ "/" من مجلد public، أو مسار في قرص التخزين العام.') }}</div>
                </x-admin.card>

                {{-- Colors --}}
                <x-admin.card id="sec-colors" title="الألوان" icon="palette" subtitle="يكفي لون أساسي واحد، وتُشتق بقية الألوان وتُفحص نسب التباين وفق معيار WCAG AA تلقائيًا">
                    @foreach($colorGroups as $groupKey => $group)
                        @php $groupKeys = array_values(array_intersect($group['keys'], $colorKeys)); @endphp
                        @if($groupKeys)
                            <div class="{{ $loop->first ? '' : 'pt-3 mt-1 border-top' }}">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h3 class="h6 fw-bold mb-0">{{ __($group['title']) }}</h3>
                                    @if($groupKey === 'semantic')<span class="pill pill-sm pill-neutral"><i class="bi bi-lock-fill"></i> {{ __('المنصة') }}</span>@endif
                                </div>
                                <p class="form-text mt-0 mb-3">{{ __($group['help']) }}</p>
                                @if($groupKey === 'semantic')
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="pill pill-success"><span class="dot"></span>{{ __('مكتملة') }}</span>
                                        <span class="pill pill-warning"><i class="bi bi-exclamation-triangle-fill"></i>{{ __('قيد المعالجة') }}</span>
                                        <span class="pill pill-error"><i class="bi bi-x-circle-fill"></i>{{ __('فشلت') }}</span>
                                        <span class="pill pill-info"><span class="dot"></span>{{ __('محجوز') }}</span>
                                        <span class="pill pill-points"><i class="bi bi-star-fill"></i>{{ __('نقاط') }}</span>
                                    </div>
                                @endif
                                <div class="row">
                                    @foreach($groupKeys as $ck)
                                        @php
                                            $cErr = 's.theme.colors.'.$ck;
                                            $cVal = old($cErr, $tenant->setting('theme.colors.'.$ck));
                                            $cOptional = in_array($ck, \App\Http\Controllers\Admin\TenantController::OPTIONAL_COLORS, true);
                                        @endphp
                                        <div class="col-md-4 col-sm-6">
                                            <x-admin.color-picker name="s[theme][colors][{{ $ck }}]" id="color_{{ $ck }}" :label="$colorLabels[$ck] ?? $ck" :code="$ck"
                                                :value="$cVal" :against="$colorAgainst[$ck] ?? null" :required="! $cOptional" :placeholder="$cOptional ? __('اختياري') : '#RRGGBB'" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @if($extraColorKeys)
                        <div class="row pt-3 border-top">
                            @foreach($extraColorKeys as $ck)
                                @php $cOptional = in_array($ck, \App\Http\Controllers\Admin\TenantController::OPTIONAL_COLORS, true); @endphp
                                <div class="col-md-4 col-sm-6">
                                    <x-admin.color-picker name="s[theme][colors][{{ $ck }}]" id="color_{{ $ck }}" :label="$colorLabels[$ck] ?? $ck" :code="$ck"
                                        :value="old('s.theme.colors.'.$ck, $tenant->setting('theme.colors.'.$ck))" :required="! $cOptional" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-admin.card>

                {{-- Typography & shape --}}
                <x-admin.card id="sec-type" title="الخطوط والشكل" icon="fonts" subtitle="خطوط Google من القائمة المعتمدة، ويمكن كتابة اسم خط آخر، واستدارة الزوايا التي تُشتق منها كل المقاسات">
                    <datalist id="curatedFonts">
                        @foreach($curatedFonts as $cf)<option value="{{ $cf }}">@endforeach
                    </datalist>
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'theme.font_family_ar', 'label' => 'الخط العربي', 'col' => 'col-md-4', 'required' => true, 'ltr' => true, 'list' => 'curatedFonts', 'inputId' => 'font_ar'])
                        @include('admin.tenants._field', ['key' => 'theme.font_family_en', 'label' => 'الخط الإنجليزي', 'col' => 'col-md-4', 'required' => true, 'ltr' => true, 'list' => 'curatedFonts', 'inputId' => 'font_en'])
                        <div class="col-md-4 field">
                            <label class="form-label">{{ __('الأرقام والمبالغ') }}</label>
                            <input type="text" class="form-control" dir="ltr" value="{{ $theme['fonts']['numeric'] }}" disabled>
                            <div class="form-text">{!! __('من :key في الإعدادات المتقدمة.', ['key' => '<code>theme.font_family_numeric</code>']) !!}</div>
                        </div>
                    </div>
                    <div class="font-sample mb-3" id="fontSample">
                        <div class="fw-bold fs-5" data-font="ar" style="font-family: '{{ $theme['fonts']['ar'] }}'">فعاليات اليوم · احجز تذكرتك الآن</div>
                        <div class="text-muted" data-font="en" dir="ltr" style="font-family: '{{ $theme['fonts']['en'] }}'">Today's events · Book your ticket now</div>
                        <div class="money mt-1" style="font-size: 20px">{{ $tenant->exists ? $tenant->currency() : 'OMR' }} 12,450.000</div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-5 field">
                            <label class="form-label" for="corner_radius">{{ __('استدارة الزوايا') }} <span class="text-muted num" id="radiusValue"></span></label>
                            @php $radiusVal = old('s.theme.corner_radius', $tenant->setting('theme.corner_radius')); @endphp
                            <div class="d-flex gap-2 align-items-center">
                                <input type="range" class="form-range flex-grow-1" min="4" max="24" step="1" value="{{ (int) $radiusVal }}" id="radiusRange" aria-label="{{ __('استدارة الزوايا') }}">
                                <input type="number" name="s[theme][corner_radius]" id="corner_radius" value="{{ $radiusVal }}" class="form-control {{ $errors->has('s.theme.corner_radius') ? 'is-invalid' : '' }}" style="max-width: 88px">
                            </div>
                            @error('s.theme.corner_radius')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7 field">
                            <div class="form-label">{{ __('المقاييس المشتقة') }}</div>
                            <div class="radius-scale" id="radiusScale">
                                @foreach(['sm', 'md', 'lg', 'xl', 'sheet'] as $rk)
                                    <span data-r="{{ $rk }}" style="border-radius: {{ $theme['radii'][$rk] }}px">{{ $rk }}<br>{{ $theme['radii'][$rk] }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6 field">
                            <label class="form-label" for="theme_mode">{{ __('الوضع') }}</label>
                            @php $modeVal = old('s.theme.mode', $tenant->setting('theme.mode')); @endphp
                            <select name="s[theme][mode]" id="theme_mode" class="form-select">
                                @foreach(['system' => 'بحسب النظام (يختار المستخدم)', 'light' => 'فاتح', 'dark' => 'داكن'] as $mv => $ml)
                                    <option value="{{ $mv }}" @selected($modeVal === $mv)>{{ __($ml) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 field">
                            <label class="form-label" for="theme_density">{{ __('الكثافة') }}</label>
                            @php $densityVal = old('s.theme.density', $tenant->setting('theme.density')); @endphp
                            <select name="s[theme][density]" id="theme_density" class="form-select">
                                @foreach(['comfortable' => 'مريحة', 'compact' => 'مضغوطة'] as $dv => $dl)
                                    <option value="{{ $dv }}" @selected($densityVal === $dv)>{{ __($dl) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-admin.card>

                {{-- Locale & contact --}}
                <x-admin.card id="sec-locale" title="اللغة والعملة والتواصل" icon="globe2">
                    <div class="row">
                        <div class="col-md-3 field">
                            <label class="form-label" for="default_locale">{{ __('اللغة الافتراضية') }}</label>
                            @php $localeVal = old('s.tenant.default_locale', $tenant->setting('tenant.default_locale')); @endphp
                            <select name="s[tenant][default_locale]" id="default_locale" class="form-select @error('s.tenant.default_locale') is-invalid @enderror">
                                <option value="ar" @selected($localeVal === 'ar')>{{ __('العربية') }}</option>
                                <option value="en" @selected($localeVal === 'en')>English</option>
                            </select>
                            @error('s.tenant.default_locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @include('admin.tenants._field', ['key' => 'tenant.locales', 'label' => 'اللغات المتاحة', 'col' => 'col-md-3', 'ltr' => true, 'help' => 'يُفصل بينها بفاصلة، مثل ar، en'])
                        @include('admin.tenants._field', ['key' => 'tenant.currency', 'label' => 'العملة', 'col' => 'col-md-2', 'ltr' => true, 'inputId' => 'currency'])
                        @include('admin.tenants._field', ['key' => 'tenant.currency_decimals', 'label' => 'المنازل العشرية', 'type' => 'number', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'tenant.timezone', 'label' => 'المنطقة الزمنية', 'col' => 'col-md-2', 'ltr' => true])
                    </div>
                    <h3 class="h6 fw-bold mt-2">{{ __('الدعم') }}</h3>
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'tenant.support.email', 'label' => 'البريد الإلكتروني', 'type' => 'email', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'tenant.support.phone', 'label' => 'الهاتف', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'tenant.support.whatsapp', 'label' => 'واتساب', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'tenant.support.website', 'label' => 'الموقع الإلكتروني', 'col' => 'col-md-3', 'ltr' => true])
                    </div>
                    <h3 class="h6 fw-bold mt-2">{{ __('التواصل الاجتماعي') }}</h3>
                    <div class="row">
                        @foreach($socialKeys as $sk)
                            @include('admin.tenants._field', ['key' => 'tenant.social.'.$sk, 'label' => $socialLabels[$sk] ?? $sk, 'col' => 'col-md-4', 'ltr' => true])
                        @endforeach
                    </div>
                    <h3 class="h6 fw-bold mt-2">{{ __('الروابط القانونية') }}</h3>
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'tenant.legal.terms_url', 'label' => 'رابط الشروط والأحكام', 'ltr' => true, 'help' => 'يمكن استخدام {lang} ليُستبدل برمز اللغة.'])
                        @include('admin.tenants._field', ['key' => 'tenant.legal.privacy_url', 'label' => 'رابط سياسة الخصوصية', 'ltr' => true, 'help' => 'يمكن استخدام {lang} ليُستبدل برمز اللغة.'])
                    </div>
                </x-admin.card>

                {{-- Modules & navigation --}}
                <x-admin.card id="sec-modules" title="الوحدات والتنقل" icon="toggles" subtitle="تختفي الوحدة المعطّلة من التطبيق كليًّا، فلا تظهر لها أزرار رمادية">
                    <div class="row">
                        @foreach($moduleKeys as $mk)
                            @include('admin.tenants._toggle', ['key' => 'modules.'.$mk, 'label' => $moduleLabels[$mk] ?? $mk, 'code' => $mk])
                        @endforeach
                    </div>
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'navigation', 'label' => 'عناصر شريط التنقل', 'col' => 'col-12', 'ltr' => true, 'help' => 'بالترتيب، ويُفصل بينها بفاصلة، مثل home، events، discover، tickets، more'])
                    </div>
                </x-admin.card>

                {{-- Map --}}
                <x-admin.card id="sec-map" title="الخريطة" icon="geo-alt">
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'map.center.lat', 'label' => 'خط العرض', 'type' => 'number', 'step' => 'any', 'col' => 'col-md-4', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'map.center.lng', 'label' => 'خط الطول', 'type' => 'number', 'step' => 'any', 'col' => 'col-md-4', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'map.zoom', 'label' => 'التكبير', 'type' => 'number', 'step' => 'any', 'col' => 'col-md-4', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'map.venue.ar', 'label' => 'اسم الموقع بالعربية'])
                        @include('admin.tenants._field', ['key' => 'map.venue.en', 'label' => 'اسم الموقع بالإنجليزية', 'ltr' => true])
                    </div>
                </x-admin.card>

                {{-- Wallet --}}
                <x-admin.card id="sec-wallet" title="إعدادات المحفظة" icon="wallet2" :subtitle="__('تُكتب جميع المبالغ بالوحدات الصغرى، فالقيمة 1000 تساوي 1.000 :currency عند ثلاث منازل عشرية', ['currency' => $tenant->currency()])">
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'wallet.topup_presets_minor', 'label' => 'مبالغ الشحن المقترحة', 'col' => 'col-md-6', 'ltr' => true, 'help' => 'يُفصل بينها بفاصلة، مثل 5000، 10000، 20000'])
                        @include('admin.tenants._field', ['key' => 'wallet.min_topup_minor', 'label' => 'أقل شحن', 'type' => 'number', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'wallet.max_topup_minor', 'label' => 'أعلى شحن', 'type' => 'number', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'wallet.max_balance_minor', 'label' => 'أعلى رصيد', 'type' => 'number', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'wallet.promo_expiry_days', 'label' => 'صلاحية الرصيد الترويجي بالأيام', 'type' => 'number', 'col' => 'col-md-6', 'help' => 'اتركه فارغًا ليبقى الرصيد بلا انتهاء. يسري على الرصيد الترويجي المضاف بعد الحفظ، ويُستهلك الأقدم أولًا.'])
                        @include('admin.tenants._json', ['key' => 'wallet.topup_bonus', 'label' => 'مكافأة الشحن', 'rows' => 3, 'help' => '[{"min_minor": 20000, "bonus_percent": 5}]، وتُضاف المكافأة إلى الرصيد الترويجي.'])
                    </div>
                    <h3 class="h6 fw-bold mt-2">{{ __('الاسترداد') }}</h3>
                    <div class="row">
                        @include('admin.tenants._toggle', ['key' => 'wallet.refunds.enabled', 'label' => 'السماح بطلبات الاسترداد', 'col' => 'col-12', 'description' => 'يطلب المستخدم استرداد الرصيد النقدي فقط، دون الرصيد الترويجي'])
                        @include('admin.tenants._field', ['key' => 'wallet.refunds.window_days', 'label' => 'المهلة بالأيام', 'type' => 'number', 'col' => 'col-md-4'])
                        @include('admin.tenants._field', ['key' => 'wallet.refunds.fee_minor', 'label' => 'الرسوم', 'type' => 'number', 'col' => 'col-md-4'])
                        @include('admin.tenants._field', ['key' => 'wallet.refunds.min_minor', 'label' => 'أقل مبلغ', 'type' => 'number', 'col' => 'col-md-4'])
                        <div class="col-12 field">
                            <div class="form-label">{{ __('طرق الاسترداد') }}</div>
                            <input type="hidden" name="s[wallet][refunds][methods][]" value="">
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($refundMethods as $rm)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rm_{{ $rm }}" name="s[wallet][refunds][methods][]" value="{{ $rm }}" @checked(in_array($rm, $selectedMethods, true))>
                                        <label class="form-check-label" for="rm_{{ $rm }}">{{ __(\App\Support\AdminLabels::REFUND_METHODS[$rm] ?? $rm) }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @error('s.wallet.refunds.methods')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </x-admin.card>

                {{-- Points --}}
                <x-admin.card id="sec-points" title="إعدادات النقاط" icon="stars">
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'points.label.ar', 'label' => 'اسم النقاط بالعربية', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'points.label.en', 'label' => 'اسم النقاط بالإنجليزية', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'points.earn_per_currency_unit', 'label' => 'نقاط لكل وحدة عملة', 'type' => 'number', 'step' => 'any', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'points.redeem_value_minor_per_point', 'label' => 'قيمة النقطة بالوحدات الصغرى', 'type' => 'number', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'points.min_redeem_points', 'label' => 'أدنى عدد نقاط للاستبدال', 'type' => 'number', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'points.expiry_days', 'label' => 'مدة الصلاحية بالأيام (0 بلا انتهاء)', 'type' => 'number', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'points.checkin_points', 'label' => 'نقاط حضور الفعالية', 'type' => 'number', 'col' => 'col-md-3'])
                        @include('admin.tenants._toggle', ['key' => 'points.earn_on_checkin', 'label' => 'منح نقاط عند أول دخول', 'col' => 'col-md-3'])
                        @include('admin.tenants._json', ['key' => 'points.tiers', 'label' => 'المستويات', 'rows' => 8, 'help' => '[{"code": "bronze", "name": {"en": "Bronze", "ar": "برونزي"}, "min_points": 0, "multiplier": 1.0, "color": "#B87333"}]'])
                    </div>
                </x-admin.card>

                {{-- App --}}
                <x-admin.card id="sec-app" title="التطبيق" icon="phone">
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'app.min_version', 'label' => 'أقل إصدار مدعوم', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'app.latest_version', 'label' => 'أحدث إصدار', 'col' => 'col-md-3', 'ltr' => true])
                        @include('admin.tenants._toggle', ['key' => 'app.force_update', 'label' => 'فرض التحديث', 'col' => 'col-md-3'])
                        @include('admin.tenants._toggle', ['key' => 'app.maintenance_mode', 'label' => 'وضع الصيانة', 'col' => 'col-md-3'])
                        @include('admin.tenants._field', ['key' => 'app.maintenance_message.ar', 'label' => 'رسالة الصيانة بالعربية'])
                        @include('admin.tenants._field', ['key' => 'app.maintenance_message.en', 'label' => 'رسالة الصيانة بالإنجليزية', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'app.store_urls.ios', 'label' => 'رابط App Store', 'ltr' => true])
                        @include('admin.tenants._field', ['key' => 'app.store_urls.android', 'label' => 'رابط Google Play', 'ltr' => true])
                    </div>
                </x-admin.card>

                @include('admin.ai._tenant-settings')

                @include('admin.gates._sales-settings')

                {{-- Billing & messages --}}
                <x-admin.card id="sec-billing" title="بيانات الفوترة والرسائل" icon="receipt">
                    <div class="row">
                        @include('admin.tenants._field', ['key' => 'payments.billing.address', 'label' => 'عنوان الفوترة', 'col' => 'col-md-4'])
                        @include('admin.tenants._field', ['key' => 'payments.billing.city', 'label' => 'المدينة', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'payments.billing.state', 'label' => 'المحافظة', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'payments.billing.zip', 'label' => 'الرمز البريدي', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'payments.billing.country', 'label' => 'الدولة', 'col' => 'col-md-2'])
                        @include('admin.tenants._field', ['key' => 'messages.otp_sms.ar', 'label' => 'رسالة رمز التحقق بالعربية', 'col' => 'col-12', 'help' => 'المتغيرات: :app :code :minutes'])
                        @include('admin.tenants._field', ['key' => 'messages.otp_sms.en', 'label' => 'رسالة رمز التحقق بالإنجليزية', 'col' => 'col-12', 'ltr' => true])
                    </div>
                </x-admin.card>

                @if($platform)
                    <x-admin.card id="sec-gateway" title="بيانات بوابة الدفع" icon="shield-lock" subtitle="تُحفظ المفاتيح مشفّرة ولا تُعرض. اترك الحقل فارغًا لتبقى القيمة الحالية.">
                        @php
                            $currentDriver = old('payments_driver', data_get($tenant->settings ?? [], 'payments.driver'));
                            $currentMode = old('thawani_mode', data_get($tenant->settings ?? [], 'payments.thawani.mode'));
                            $platformDriver = config('payments.driver');
                        @endphp
                        <div class="row">
                            <div class="col-md-6 field">
                                <label class="form-label" for="payments_driver">{{ __('بوابة الدفع المستخدمة') }}</label>
                                <select name="payments_driver" id="payments_driver" class="form-select @error('payments_driver') is-invalid @enderror">
                                    <option value="">{{ __('افتراضي المنصة (:driver)', ['driver' => $driverLabels[$platformDriver] ?? $platformDriver]) }}</option>
                                    @foreach(\App\Services\Payments\PaymentGatewayManager::DRIVERS as $d)
                                        @continue($d === 'fake' && app()->environment('production'))
                                        <option value="{{ $d }}" @selected($currentDriver === $d)>{{ $driverLabels[$d] ?? $d }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('تبقى كل دفعة على البوابة التي أُنشئت بها.') }}</div>
                                @error('payments_driver')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 field">
                                <label class="form-label" for="thawani_mode">{{ __('بيئة ثواني') }}</label>
                                <select name="thawani_mode" id="thawani_mode" class="form-select @error('thawani_mode') is-invalid @enderror">
                                    <option value="">{{ __('افتراضي المنصة (:mode)', ['mode' => config('services.thawani.mode', 'uat')]) }}</option>
                                    <option value="uat" @selected($currentMode === 'uat')>{{ __('تجريبية') }} · UAT</option>
                                    <option value="production" @selected($currentMode === 'production')>{{ __('التشغيل الفعلي') }} · Production</option>
                                </select>
                                <div class="form-text ltr text-start">uatcheckout.thawani.om · checkout.thawani.om</div>
                                @error('thawani_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @if($tenant->exists)
                            <div class="kv mb-3">
                                <div class="k">{{ __('رابط الإشعارات لإضافته في بوابة تجار ثواني') }} <span class="ltr">· Webhook URL</span></div>
                                <div class="v font-monospace small ltr text-start" style="word-break: break-all">{{ route('payments.webhooks.thawani', ['tenant' => $tenant->slug]) }}</div>
                            </div>
                        @endif
                        @foreach($gatewayFields as $provider => $gFields)
                            <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                <h3 class="h6 fw-bold mb-0">{{ $gatewayTitles[$provider][0] ?? $provider }} <span class="text-muted fw-normal small ltr">{{ $gatewayTitles[$provider][1] ?? '' }}</span></h3>
                                @if($provider === 'thawani' && $tenant->exists)
                                    <button type="submit" form="gwCheckForm" class="btn btn-sm" data-busy><i class="bi bi-plug"></i> {{ __('اختبر الاتصال') }}</button>
                                @endif
                            </div>
                            <div class="row">
                                @foreach($gFields as $gf)
                                    @php $gSet = filled(data_get($tenant->gateway_credentials ?? [], $provider.'.'.$gf)); @endphp
                                    <div class="col-md-6 field">
                                        <label class="form-label d-flex align-items-center gap-2" for="gw_{{ $provider }}_{{ $gf }}">{{ $gatewayLabels[$gf] ?? $gf }}
                                            @if($gSet)<span class="pill pill-sm pill-success"><span class="dot"></span>{{ __('محفوظ') }}</span>@else<span class="pill pill-sm pill-neutral">{{ __('غير مُعيّن') }}</span>@endif
                                        </label>
                                        <input type="{{ $gf === 'gateway_url' ? 'text' : 'password' }}" name="gateway[{{ $provider }}][{{ $gf }}]" id="gw_{{ $provider }}_{{ $gf }}" dir="ltr"
                                               autocomplete="new-password" class="form-control @error('gateway.'.$provider.'.'.$gf) is-invalid @enderror"
                                               placeholder="{{ $gSet ? __('•••••• (اتركه فارغًا لتبقى القيمة)') : '' }}">
                                        @error('gateway.'.$provider.'.'.$gf)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </x-admin.card>
                @endif

                <x-admin.card id="sec-advanced" title="إعدادات متقدمة" icon="code-slash" subtitle="مفاتيح العرض الاختيارية (§5a)، ويُعاين التدرّج هنا مباشرة.">
                    <p class="form-text mt-0 mb-2 ltr text-start">
                        <code>theme.header_style</code> (gradient | solid | image) · <code>theme.header_gradient</code> · <code>theme.header_image_url</code> ·
                        <code>theme.card_pattern_url</code> · <code>theme.font_family_numeric</code> · <code>home_blocks</code> · <code>quick_actions</code> · <code>merchant.confirm_threshold_minor</code>
                    </p>
                    <textarea name="advanced" id="advancedJson" rows="14" dir="ltr" spellcheck="false" class="form-control font-monospace small @error('advanced') is-invalid @enderror">{{ $advancedJson }}</textarea>
                    @error('advanced')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </x-admin.card>

                @if($mode === 'create')
                    <x-admin.card id="sec-admin" title="مدير المنظمة (اختياري)" icon="person-badge" subtitle="إن كان البريد مسجّلًا من قبل مُنح صاحبه دور المدير في هذه المنظمة، وإلا أُنشئ مستخدم جديد.">
                        <div class="row">
                            <x-admin.form-field col="col-md-4" name="admin_email" label="البريد الإلكتروني" type="email" dir="ltr" :value="old('admin_email')" />
                            <x-admin.form-field col="col-md-4" name="admin_name" label="الاسم" :value="old('admin_name')" />
                            <x-admin.form-field col="col-md-4" name="admin_password" label="كلمة المرور (للمستخدم الجديد)" type="password" autocomplete="new-password" />
                        </div>
                    </x-admin.card>
                @endif
            </div>

            {{-- Live preview --}}
            <div class="col-xl-5 col-xxl-4">
                <div class="preview-sticky">
                    <x-admin.card title="معاينة مباشرة" icon="phone" subtitle="الشاشة الرئيسية للتطبيق بألوانك الآن">
                        <x-slot:actions>
                            <div class="segmented" data-preview-lang>
                                <button type="button" class="active" data-lang="ar">ع</button>
                                <button type="button" data-lang="en">EN</button>
                            </div>
                        </x-slot:actions>
                        <div class="phone" id="phonePreview">
                            <div class="phone-screen" id="phoneScreen" dir="rtl">
                                <div class="phone-notch"></div>
                                <div class="pv-band">
                                    <div class="pv-top">
                                        <span class="pv-logo" id="pvLogo">@if($logoPreview)<img src="{{ $logoPreview }}" alt="">@else<i class="bi bi-star-fill" style="color: var(--pv-brand)"></i>@endif</span>
                                        <span class="pv-hello"><b data-i18n="hello">مساء الخير، أحمد</b><span id="pvName">{{ $tenant->name['ar'] ?? 'اسم المنظمة' }}</span></span>
                                        <span class="pv-icon"><i class="bi bi-bell"></i></span>
                                    </div>
                                    <div class="pv-wallet">
                                        <div class="d-flex justify-content-between align-items-center" style="font-size: 11px">
                                            <span data-i18n="balance">رصيد المحفظة</span><span class="pv-chips m-0"><span><i class="bi bi-qr-code"></i> <span data-i18n="payqr">رمز الدفع</span></span></span>
                                        </div>
                                        <span class="money"><span class="cur" data-currency>{{ $tenant->exists ? $tenant->currency() : 'OMR' }}</span>12.450</span>
                                        <div class="pv-chips">
                                            <span>★ 340 <span data-i18n="pts">نقطة</span></span>
                                            <span><i class="bi bi-lock"></i> <span data-i18n="held">2 محجوز</span></span>
                                        </div>
                                        <div class="pv-btns"><span class="on" data-i18n="pay">ادفع</span><span class="ghost" data-i18n="topup">شحن</span></div>
                                    </div>
                                </div>
                                <div class="pv-sheet">
                                    <div class="pv-section"><span data-i18n="featured">فعاليات مميزة</span><a data-i18n="all">عرض الكل</a></div>
                                    <div class="pv-card">
                                        <span class="pv-thumb"><i class="bi bi-music-note-beamed"></i></span>
                                        <span><span class="t d-block" data-i18n="ev1">أمسية موسيقية</span><span class="s d-block" data-i18n="ev1s">الجمعة 20:00 · المسرح</span></span>
                                        <span class="pv-badge" data-i18n="feat">مميز</span>
                                    </div>
                                    <div class="pv-card">
                                        <span class="pv-thumb"><i class="bi bi-palette2"></i></span>
                                        <span><span class="t d-block" data-i18n="ev2">معرض الحرف</span><span class="s d-block" data-i18n="ev2s">اليوم 16:00 · القاعة B</span></span>
                                    </div>
                                    <span class="pv-cta" data-i18n="cta">احجز تذكرة</span>
                                </div>
                                <div class="pv-nav">
                                    <span class="act"><i class="bi bi-house-door-fill"></i></span><i class="bi bi-compass"></i>
                                    <span class="mid"><i class="bi bi-wallet2"></i></span><i class="bi bi-ticket-perforated"></i><i class="bi bi-list"></i>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>

                    <x-admin.card title="حارس التباين" icon="shield-check" subtitle="النتيجة التي يعتمدها التطبيق والموقع">
                        <table class="guard-table" id="guardReport"><tbody></tbody></table>
                        <p class="form-text mb-0 mt-2" id="guardNote"></p>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </form>

    @if($platform && $tenant->exists)
        {{-- Outside the main form (which sends _method=PUT). --}}
        <form id="gwCheckForm" action="{{ route('admin.tenants.gateway-check', $tenant) }}" method="POST" class="d-none">@csrf</form>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    var G = window.ColorGuard;
    if (!G) return;
    var screen = document.getElementById('phoneScreen');
    var report = document.querySelector('#guardReport tbody');
    var note = document.getElementById('guardNote');
    var defaults = { primary: '#2F6FED', background: '#F7F8FA', surface: '#FFFFFF', on_surface: '#111827', muted: '#6B7280' };
    var lang = 'ar';
    var i18n = {
        en: { hello: 'Good evening, Ahmed', balance: 'Wallet balance', payqr: 'Pay QR', pts: 'pts', held: '2 held', pay: 'Pay', topup: 'Top up', featured: 'Featured events', all: 'See all', ev1: 'Music night', ev1s: 'Fri 20:00 · Theatre', feat: 'Featured', ev2: 'Crafts fair', ev2s: 'Today 16:00 · Hall B', cta: 'Book a ticket' },
        ar: {}
    };
    document.querySelectorAll('[data-i18n]').forEach(function (el) { i18n.ar[el.dataset.i18n] = el.textContent; });

    function val(key) { var el = document.getElementById('color_' + key); return el ? G.norm(el.value) : null; }
    function advanced() { try { return JSON.parse(document.getElementById('advancedJson').value || '{}'); } catch (e) { return null; } }
    function row(label, swatches, ratio, ok, extra) {
        var sw = swatches.map(function (c) { return '<span class="sw" style="background:' + c + '"></span>'; }).join('');
        return '<tr><td>' + label + '</td><td class="ltr text-start" style="font-family:var(--font-num)">' + sw + swatches.join(' → ') + '</td><td class="text-end"><span class="contrast-chip ' + (ok ? 'ok' : 'fix') + '">' + ratio.toFixed(2) + ':1</span>' + (extra || '') + '</td></tr>';
    }

    function render() {
        var seed = val('primary') || defaults.primary;
        var bgIn = val('background'), surfIn = val('surface'), onSurf = val('on_surface'), mutedIn = val('muted');
        var surface = (surfIn && onSurf && G.contrast(onSurf, surfIn) >= 7) ? surfIn : defaults.surface;
        var text = (surfIn && onSurf && G.contrast(onSurf, surfIn) >= 7) ? onSurf : defaults.on_surface;
        var bg = (bgIn && bgIn !== surface && G.contrast(mutedIn || defaults.muted, bgIn) >= 4.5) ? bgIn : defaults.background;
        var muted = (mutedIn && G.contrast(mutedIn, bg) >= 4.5 && G.contrast(mutedIn, surface) >= 4.5) ? mutedIn : defaults.muted;
        var p = G.guardAction(seed, val('on_primary'), bg);
        var secSeed = val('secondary') || seed;
        var s = G.guardAction(secSeed, val('on_secondary'), bg);
        var adv = advanced();
        var hg = adv && adv.theme && Array.isArray(adv.theme.header_gradient) ? adv.theme.header_gradient.map(G.norm).filter(Boolean) : [];
        var h1 = G.guardOnWhite(hg[0] || p.fill), h2 = G.guardOnWhite(hg[1] || G.withTone(p.fill, G.tone(p.fill) - 12));
        if (adv && adv.theme && adv.theme.header_style === 'solid') { h1 = h2 = G.guardOnWhite(p.fill); }
        var w1 = G.guardOnWhite(val('wallet_card_start') || p.fill), w2 = G.guardOnWhite(val('wallet_card_end') || G.withTone(p.fill, G.tone(p.fill) - 14));
        var r = Math.max(4, Math.min(24, parseInt(document.getElementById('corner_radius').value, 10) || 16));
        var even = function (v) { return 2 * Math.round(v / 2); };
        var radii = { sm: Math.max(2, even(r * .5)), md: Math.max(4, even(r * .75)), lg: even(r), xl: even(r * 1.5), sheet: even(Math.max(16, Math.min(32, r * 1.75))) };

        var vars = {
            '--pv-brand': p.fill, '--pv-on-brand': p.on, '--pv-brand-text': p.text, '--pv-brand-soft': p.soft,
            '--pv-accent': s.fill, '--pv-on-accent': s.on, '--pv-accent-soft': s.soft,
            '--pv-h1': h1, '--pv-h2': h2, '--pv-w1': w1, '--pv-w2': w2,
            '--pv-bg': bg, '--pv-surface': surface, '--pv-text': text, '--pv-muted': muted,
            '--pv-md': radii.md + 'px', '--pv-lg': radii.lg + 'px', '--pv-xl': radii.xl + 'px', '--pv-sheet': radii.sheet + 'px'
        };
        Object.keys(vars).forEach(function (k) { screen.style.setProperty(k, vars[k]); });
        var fAr = document.getElementById('font_ar'), fEn = document.getElementById('font_en');
        screen.style.setProperty('--pv-font', "'" + ((lang === 'ar' ? fAr : fEn) || {}).value + "', var(--font-ui)");

        document.querySelectorAll('#radiusScale [data-r]').forEach(function (el) {
            el.style.borderRadius = radii[el.dataset.r] + 'px';
            el.innerHTML = el.dataset.r + '<br>' + radii[el.dataset.r];
        });
        document.getElementById('radiusValue').textContent = '(' + r + 'px)';

        var rows = '';
        rows += row(@json(__('الأساسي (تعبئة)')), p.fill === seed ? [seed] : [seed, p.fill], G.contrast(p.on, p.fill), G.contrast(p.on, p.fill) >= 4.5,
            p.step === 1 ? ' <span class="pill pill-sm pill-info">' + @json(__('غُمّق :shift')).replace(':shift', p.shift) + '</span>' : (p.step === 2 ? ' <span class="pill pill-sm pill-warning">' + @json(__('نص داكن')) + '</span>' : ''));
        rows += row(@json(__('الأساسي للنص')), [p.text], G.contrast(p.text, bg), G.contrast(p.text, bg) >= 4.5);
        rows += row(@json(__('الثانوي')), s.fill === secSeed ? [secSeed] : [secSeed, s.fill], G.contrast(s.on, s.fill), G.contrast(s.on, s.fill) >= 4.5,
            s.step === 2 ? ' <span class="pill pill-sm pill-warning">' + @json(__('نص داكن')) + '</span>' : '');
        rows += row(@json(__('شريط الرأس')), [h1, h2], Math.min(G.contrast('#FFFFFF', h1), G.contrast('#FFFFFF', h2)), Math.min(G.contrast('#FFFFFF', h1), G.contrast('#FFFFFF', h2)) >= 4.5);
        rows += row(@json(__('بطاقة المحفظة')), [w1, w2], Math.min(G.contrast('#FFFFFF', w1), G.contrast('#FFFFFF', w2)), Math.min(G.contrast('#FFFFFF', w1), G.contrast('#FFFFFF', w2)) >= 4.5);
        rows += row(@json(__('النص على الأسطح')), [text], G.contrast(text, surface), G.contrast(text, surface) >= 7);
        report.innerHTML = rows;
        var raw = G.contrast('#FFFFFF', seed);
        note.textContent = p.step === 0
            ? @json(__('يحقق اللون الأساسي التباين المطلوب دون تعديل (:ratio:1 مع الأبيض).')).replace(':ratio', raw.toFixed(2))
            : (p.step === 1
                ? @json(__('النص الأبيض على :seed لا يتجاوز تباينه :ratio:1، فغمّق الحارس اللون إلى :fill بمقدار :shift مع الحفاظ على هوية العلامة.')).replace(':seed', seed).replace(':ratio', raw.toFixed(2)).replace(':shift', p.shift).replace(':fill', p.fill)
                : @json(__('اللون فاتح، فيبقى تعبئةً مع نص داكن، ويُشتق منه لون أغمق (:text) للنصوص والأيقونات.')).replace(':text', p.text));
    }

    function setLang(l) {
        lang = l;
        screen.setAttribute('dir', l === 'ar' ? 'rtl' : 'ltr');
        document.querySelectorAll('#phoneScreen [data-i18n]').forEach(function (el) { var t = i18n[l][el.dataset.i18n]; if (t) el.textContent = t; });
        var nm = document.getElementById(l === 'ar' ? 'name_ar' : 'name_en');
        var tg = document.getElementById(l === 'ar' ? 'tagline_ar' : 'tagline_en');
        document.getElementById('pvName').textContent = (tg && tg.value) || (nm && nm.value) || '';
        document.querySelectorAll('[data-preview-lang] button').forEach(function (b) { b.classList.toggle('active', b.dataset.lang === l); });
        render();
    }
    document.querySelectorAll('[data-preview-lang] button').forEach(function (b) { b.addEventListener('click', function () { setLang(b.dataset.lang); }); });

    var range = document.getElementById('radiusRange'), radius = document.getElementById('corner_radius');
    range.addEventListener('input', function () { radius.value = range.value; render(); });
    radius.addEventListener('input', function () { range.value = radius.value; render(); });
    ['name_ar', 'name_en', 'tagline_ar', 'tagline_en'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('input', function () { setLang(lang); }); });
    ['font_ar', 'font_en'].forEach(function (id) {
        var el = document.getElementById(id); if (!el) return;
        el.addEventListener('change', function () {
            if (/^[\p{L}\p{N} ]{2,60}$/u.test(el.value)) {
                var link = document.createElement('link'); link.rel = 'stylesheet';
                link.href = 'https://fonts.googleapis.com/css2?family=' + el.value.trim().replace(/ /g, '+') + ':wght@400;700&display=swap';
                document.head.appendChild(link);
                var sample = document.querySelector('#fontSample [data-font="' + (id === 'font_ar' ? 'ar' : 'en') + '"]');
                if (sample) sample.style.fontFamily = "'" + el.value.trim() + "'";
            }
            render();
        });
    });
    var currency = document.getElementById('currency');
    if (currency) currency.addEventListener('input', function () { document.querySelectorAll('[data-currency]').forEach(function (c) { c.textContent = currency.value.toUpperCase() || 'OMR'; }); });
    document.getElementById('advancedJson').addEventListener('input', render);
    document.addEventListener('color:change', render);
    document.addEventListener('upload:preview', function (e) {
        if (e.detail.input && e.detail.input.name === 'logo') document.getElementById('pvLogo').innerHTML = '<img alt="" src="' + e.detail.url + '">';
    });
    setLang(@json($tenant->setting('tenant.default_locale') === 'en' ? 'en' : 'ar'));
})();
</script>
@endpush
