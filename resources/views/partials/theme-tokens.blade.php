{{--
    Tenant design tokens → CSS custom properties (docs/design_system.md §2–3).
    Usage: @include('partials.theme-tokens') inside <head>. Optional: $themeTenant (defaults to $brand).
    Light tokens on :root, dark tokens via prefers-color-scheme (no-JS fallback) and
    :root[data-theme="dark"] (manual toggle stored in localStorage "ui-theme").
--}}
@php
    $tt = \App\View\TenantTheme::for($themeTenant ?? ($brand ?? null));
    $ttRoot = $tt['shared'] + [
        'r-sm' => $tt['radii']['sm'].'px', 'r-md' => $tt['radii']['md'].'px', 'r-lg' => $tt['radii']['lg'].'px',
        'r-xl' => $tt['radii']['xl'].'px', 'r-sheet' => $tt['radii']['sheet'].'px', 'r-pill' => '999px',
        'font-ar' => "'".$tt['fonts']['ar']."'", 'font-en' => "'".$tt['fonts']['en']."'", 'font-num' => "'".$tt['fonts']['numeric']."'",
        'ar-size' => $tt['fonts']['ar_size'], 'ar-height' => $tt['fonts']['ar_height'],
        'gutter' => $tt['density'] === 'compact' ? '12px' : '16px',
        'section-gap' => $tt['density'] === 'compact' ? '20px' : '24px',
    ];
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $tt['fonts']['url'] }}">
<style id="tenant-tokens">
:root {
{!! \App\View\TenantTheme::vars($ttRoot) !!}{!! \App\View\TenantTheme::vars($tt['light']) !!}    color-scheme: light;
}
@if($tt['mode'] !== 'light')
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
{!! \App\View\TenantTheme::vars($tt['dark'], '    ') !!}    color-scheme: dark;
  }
}
@endif
:root[data-theme="dark"] {
{!! \App\View\TenantTheme::vars($tt['dark']) !!}    color-scheme: dark;
}
</style>
<script>
  /* Resolve the color scheme before first paint: manual choice > tenant mode > OS. */
  (function () {
    var tenantMode = @json($tt['mode']);
    var stored = null;
    try { stored = localStorage.getItem('ui-theme'); } catch (e) {}
    var pref = stored === 'light' || stored === 'dark' ? stored : tenantMode;
    var dark = pref === 'dark' || (pref === 'system' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
    var root = document.documentElement;
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-theme-pref', stored || 'auto');
    window.__tenantThemeMode = tenantMode;
  })();
</script>
