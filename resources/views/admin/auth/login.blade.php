@php
    $loginLocale = \App\Http\Middleware\SetAdminLocale::current();
    $loginDir = $loginLocale === 'ar' ? 'rtl' : 'ltr';
    $tenantList = collect($tenants ?? []);
    $selectedSlug = old('tenant', request('tenant', ''));
    $defaultSlug = config('tenancy.default');
    $brandData = $tenantList->mapWithKeys(function ($t) use ($loginLocale) {
        $th = \App\View\TenantTheme::for($t);
        return [$t->slug => [
            'name' => $t->localizedName($loginLocale),
            'name_en' => $t->localizedName('en'),
            'tagline' => (string) $t->setting('tenant.tagline.'.$loginLocale),
            'logo' => $th['logo_needs_chip'] ? ($th['logo'] ?? null) : ($th['logo_on_primary'] ?? null),
            'chip' => $th['logo_needs_chip'],
            'start' => $th['shared']['header-start'], 'end' => $th['shared']['header-end'], 'onHeader' => $th['shared']['on-header'],
            'brand' => $th['light']['brand'], 'onBrand' => $th['light']['on-brand'], 'hover' => $th['light']['brand-hover'],
            'text' => $th['light']['brand-text'], 'soft' => $th['light']['brand-soft'],
            'rgb' => \App\Support\ColorGuard::rgbString($th['light']['brand']),
        ]];
    });
    $initial = $brandData[$selectedSlug] ?? $brandData[$defaultSlug] ?? $brandData->first();
    $initialTenant = $tenantList->firstWhere('slug', $selectedSlug) ?? $tenantList->firstWhere('slug', $defaultSlug) ?? $tenantList->first();
@endphp
<!DOCTYPE html>
<html lang="{{ $loginLocale }}" dir="{{ $loginDir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('تسجيل الدخول') }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/platform-logo.svg') }}">
    @include('partials.theme-tokens', ['themeTenant' => $initialTenant])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/{{ $loginDir === 'rtl' ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css' }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
    <style>
        body { background: var(--bg); }
        .auth { min-height: 100vh; display: grid; grid-template-columns: minmax(380px, 520px) 1fr; }
        .auth-form { display: flex; flex-direction: column; padding: 40px clamp(24px, 5vw, 64px); background: var(--surface); }
        .auth-platform { display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--text); }
        .auth-platform img { height: 32px; }
        .auth-inner { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 400px; width: 100%; margin: 0 auto; padding-block: 32px; }
        .auth-inner h1 { font-size: 28px; font-weight: 800; margin-bottom: 6px; }
        .auth-inner .lead-text { color: var(--muted); margin-bottom: 28px; }
        .input-icon { position: relative; }
        .input-icon > i { position: absolute; inset-inline-start: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .input-icon > .form-control, .input-icon > .form-select { padding-inline-start: 42px; min-height: 50px; }
        html[dir="rtl"] .input-icon > [dir="ltr"] { padding-inline-start: .9rem; padding-inline-end: 42px; }
        .btn-login { width: 100%; min-height: 52px; font-size: 15.5px; }
        .auth-foot { color: var(--muted); font-size: 12.5px; text-align: center; }
        .auth-brand {
            position: relative; overflow: hidden; color: var(--on-header); padding: 48px clamp(28px, 5vw, 72px);
            display: flex; flex-direction: column; justify-content: space-between;
            background: linear-gradient(225deg, var(--header-start), var(--header-end)); transition: background .4s;
        }
        .auth-brand::before { content: ""; position: absolute; inset: 0; background-image: var(--pattern); background-size: 96px; opacity: .06; }
        .auth-brand::after { content: ""; position: absolute; width: 520px; height: 520px; border-radius: 50%; inset-inline-end: -160px; bottom: -200px; background: rgba(255,255,255,.08); }
        .auth-brand > * { position: relative; z-index: 1; }
        .brand-logo { width: 96px; height: 96px; border-radius: 28px; display: grid; place-items: center; overflow: hidden; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); }
        .brand-logo.chip { background: #fff; }
        .brand-logo img { max-width: 82%; max-height: 82%; object-fit: contain; }
        .auth-brand h2 { color: var(--on-header); font-size: clamp(28px, 3.2vw, 44px); font-weight: 800; margin: 24px 0 8px; line-height: 1.3; }
        .auth-brand p.tag { opacity: .88; font-size: 17px; max-width: 520px; }
        .brand-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; max-width: 620px; }
        .brand-cards div { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); border-radius: var(--r-lg); padding: 16px; backdrop-filter: blur(6px); }
        .brand-cards i { font-size: 22px; display: block; margin-bottom: 8px; }
        .brand-cards b { display: block; font-size: 14px; }
        .brand-cards span { font-size: 12.5px; opacity: .85; }
        .tenant-pick { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .tenant-pick button { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--hairline); background: var(--surface); border-radius: 999px; padding: 5px 12px 5px 6px; font-size: 13px; font-weight: 600; color: var(--text); }
        html[dir="rtl"] .tenant-pick button { padding: 5px 6px 5px 12px; }
        .tenant-pick button.active { border-color: var(--brand); background: var(--brand-soft); color: var(--brand-text); }
        .tenant-pick .tenant-dot { width: 20px; height: 20px; border-radius: 50%; }
        @media (max-width: 991.98px) {
            .auth { grid-template-columns: 1fr; }
            .auth-brand { order: -1; padding: 28px 20px 56px; min-height: 0; }
            .auth-brand .brand-cards, .auth-brand::after { display: none; }
            .auth-brand h2 { font-size: 24px; margin-top: 14px; }
            .brand-logo { width: 64px; height: 64px; border-radius: 20px; }
            .auth-form { border-radius: var(--r-sheet) var(--r-sheet) 0 0; margin-top: -28px; position: relative; padding-top: 28px; }
        }
    </style>
</head>
<body>
<div class="auth">
    <section class="auth-form">
        <div class="auth-platform">
            <img src="{{ asset('images/platform-logo.svg') }}" alt="{{ config('app.name') }}">
            <span>{{ config('app.name') }}</span>
            <form method="POST" action="{{ route('admin.locale') }}" class="ms-auto m-0">
                @csrf
                <input type="hidden" name="locale" value="{{ $loginLocale === 'ar' ? 'en' : 'ar' }}">
                <button type="submit" class="btn btn-sm btn-tonal" title="{{ $loginLocale === 'ar' ? 'English' : 'العربية' }}" aria-label="{{ $loginLocale === 'ar' ? 'Switch to English' : 'التبديل إلى العربية' }}">
                    <i class="bi bi-translate"></i><span class="ms-1 fw-bold" @if($loginLocale === 'ar') dir="ltr" lang="en" @else lang="ar" @endif>{{ $loginLocale === 'ar' ? 'EN' : 'ع' }}</span>
                </button>
            </form>
        </div>

        <div class="auth-inner">
            <h1>{{ __('مرحباً بعودتك') }}</h1>
            <p class="lead-text">{{ __('سجّل الدخول إلى لوحة إدارة منظمتك لإدارة الفعاليات والتذاكر والمحافظ.') }}</p>

            @if($errors->any())
                <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" id="loginForm" novalidate>
                @csrf

                @if($tenantList->count() > 0)
                    <div class="field">
                        <label for="tenant" class="form-label">{{ __('الجهة المنظمة') }} <span class="text-muted fw-normal">({{ __('اختياري') }})</span></label>
                        <div class="input-icon">
                            <i class="bi bi-buildings"></i>
                            <select class="form-select @error('tenant') is-invalid @enderror" id="tenant" name="tenant">
                                <option value="">— {{ __('الجهة الافتراضية') }} —</option>
                                @foreach($tenantList as $tenantOption)
                                    <option value="{{ $tenantOption->slug }}" @selected($selectedSlug === $tenantOption->slug)>{{ $tenantOption->localizedName($loginLocale) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($tenantList->count() > 1)
                            <div class="tenant-pick" aria-hidden="true">
                                @foreach($brandData as $slug => $bd)
                                    <button type="button" data-pick="{{ $slug }}" class="{{ $selectedSlug === $slug ? 'active' : '' }}" tabindex="-1">
                                        <span class="tenant-dot" style="background: linear-gradient(135deg, {{ $bd['start'] }}, {{ $bd['end'] }})"></span>{{ $bd['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <div class="field">
                    <label for="email" class="form-label">{{ __('البريد الإلكتروني') }}</label>
                    <div class="input-icon">
                        <i class="bi bi-envelope"></i>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}"
                               placeholder="admin@example.com" dir="ltr" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label for="password" class="form-label">{{ __('كلمة المرور') }}</label>
                    <div class="input-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="••••••••" dir="ltr" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">{{ __('تذكرني') }}</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-login" data-busy>
                    <i class="bi bi-box-arrow-in-left dir-icon"></i> {{ __('تسجيل الدخول') }}
                </button>
            </form>
        </div>

        <p class="auth-foot mb-0">© {{ date('Y') }} {{ config('app.name') }}</p>
    </section>

    <section class="auth-brand" aria-live="polite">
        <div>
            <div class="brand-logo {{ ($initial['chip'] ?? false) ? 'chip' : '' }}" id="brandLogo">
                @if(! empty($initial['logo']))<img src="{{ $initial['logo'] }}" alt="">@else<i class="bi bi-calendar2-heart fs-1"></i>@endif
            </div>
            <h2 id="brandName">{{ $initial['name'] ?? config('app.name') }}</h2>
            <p class="tag" id="brandTag">{{ ($initial['tagline'] ?? '') ?: __('منصة إدارة الفعاليات المتكاملة') }}</p>
        </div>
        <div class="brand-cards">
            <div><i class="bi bi-calendar-event"></i><b>{{ __('الفعاليات والتذاكر') }}</b><span>{{ __('جدولة، بيع، وتحقق عند البوابة') }}</span></div>
            <div><i class="bi bi-wallet2"></i><b>{{ __('المحفظة والمدفوعات') }}</b><span>{{ __('شحن، استرداد، وتأمينات') }}</span></div>
            <div><i class="bi bi-palette"></i><b>{{ __('هوية منظمتك') }}</b><span>{{ __('ألوانك وشعارك في كل شاشة') }}</span></div>
        </div>
    </section>
</div>

<script>
(function () {
    var brands = @json($brandData);
    var fallback = @json($defaultSlug);
    var select = document.getElementById('tenant');
    var root = document.documentElement;
    function apply(slug) {
        var b = brands[slug] || brands[fallback] || brands[Object.keys(brands)[0]];
        if (!b) return;
        var set = { '--header-start': b.start, '--header-end': b.end, '--on-header': b.onHeader, '--brand': b.brand, '--on-brand': b.onBrand,
            '--brand-hover': b.hover, '--brand-text': b.text, '--brand-soft': b.soft, '--brand-rgb': b.rgb };
        if (root.getAttribute('data-theme') !== 'dark') Object.keys(set).forEach(function (k) { root.style.setProperty(k, set[k]); });
        else ['--header-start', '--header-end', '--on-header'].forEach(function (k) { root.style.setProperty(k, set[k]); });
        document.getElementById('brandName').textContent = b.name;
        document.getElementById('brandTag').textContent = b.tagline || @json(__('منصة إدارة الفعاليات المتكاملة'));
        var logo = document.getElementById('brandLogo');
        logo.classList.toggle('chip', !!b.chip);
        logo.innerHTML = b.logo ? '<img alt="" src="' + b.logo + '">' : '<i class="bi bi-calendar2-heart fs-1"></i>';
        document.querySelectorAll('[data-pick]').forEach(function (p) { p.classList.toggle('active', p.dataset.pick === slug); });
    }
    if (select) {
        select.addEventListener('change', function () { apply(select.value); });
        apply(select.value);
    }
    document.querySelectorAll('[data-pick]').forEach(function (p) {
        p.addEventListener('click', function () { select.value = p.dataset.pick; apply(p.dataset.pick); });
    });
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        var b = e.submitter; if (b && b.hasAttribute('data-busy')) setTimeout(function () { b.disabled = true; }, 0);
    });
})();
</script>
</body>
</html>
