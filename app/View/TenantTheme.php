<?php

namespace App\View;

use App\Models\Tenant;
use App\Support\ColorGuard as G;

/**
 * Resolves a tenant's `theme` settings into web design tokens
 * (docs/design_system.md §2–3): guarded brand roles, platform neutrals,
 * platform-locked semantic colors, radii from corner_radius, fonts.
 *
 * Views use it through resources/views/partials/theme-tokens.blade.php,
 * which prints the tokens as CSS custom properties.
 */
class TenantTheme
{
    /** Curated font list (§3.2.1) → metric adjustments. */
    public const FONTS = [
        'Cairo' => ['size' => 1.00, 'height' => 1.00, 'script' => 'ar'],
        'IBM Plex Sans Arabic' => ['size' => 1.04, 'height' => 1.00, 'script' => 'ar'],
        'Tajawal' => ['size' => 1.06, 'height' => 0.96, 'script' => 'ar'],
        'Almarai' => ['size' => 1.00, 'height' => 0.96, 'script' => 'ar'],
        'Noto Kufi Arabic' => ['size' => 0.96, 'height' => 1.04, 'script' => 'ar'],
        'Readex Pro' => ['size' => 1.00, 'height' => 1.00, 'script' => 'ar'],
        'Inter' => ['size' => 1.00, 'height' => 1.00, 'script' => 'en'],
        'IBM Plex Sans' => ['size' => 1.00, 'height' => 1.00, 'script' => 'en'],
        'Poppins' => ['size' => 0.96, 'height' => 1.00, 'script' => 'en'],
    ];

    public const PLATFORM_PRIMARY = '#2F6FED';

    /** Platform neutrals (§3.1.3). */
    public const LIGHT = [
        'bg' => '#F7F8FA', 'surface' => '#FFFFFF', 'surface-low' => '#F3F4F6', 'surface-container' => '#EEF0F3',
        'surface-high' => '#E5E7EB', 'text' => '#111827', 'muted' => '#6B7280', 'disabled' => '#9CA3AF',
        'hairline' => '#E5E7EB', 'outline-strong' => '#848E9E', 'inverse' => '#1F2937', 'on-inverse' => '#F9FAFB',
    ];

    public const DARK = [
        'bg' => '#0F1115', 'surface' => '#171A21', 'surface-low' => '#1E222B', 'surface-container' => '#232833',
        'surface-high' => '#2A303B', 'text' => '#F3F4F6', 'muted' => '#9CA3AF', 'disabled' => '#4B5563',
        'hairline' => '#2A2F3A', 'outline-strong' => '#6B7280', 'inverse' => '#E5E7EB', 'on-inverse' => '#111827',
    ];

    /** Platform-locked semantic colors (§3.1.2) — tenants cannot change them. */
    public const SEMANTIC_LIGHT = [
        'success' => '#15803D', 'success-container' => '#DCFCE7', 'on-success-container' => '#166534',
        'warning' => '#B45309', 'warning-fill' => '#F59E0B', 'warning-container' => '#FEF3C7', 'on-warning-container' => '#92400E',
        'error' => '#DC2626', 'error-container' => '#FEE2E2', 'on-error-container' => '#991B1B',
        'info' => '#2563EB', 'info-container' => '#DBEAFE', 'on-info-container' => '#1E40AF',
        'points' => '#D4A017', 'points-text' => '#A16207', 'points-container' => '#FEF3C7', 'on-points-container' => '#92400E',
        'neutral-container' => '#EEF0F3', 'on-neutral-container' => '#4B5563',
        'credit' => '#15803D',
    ];

    public const SEMANTIC_DARK = [
        'success' => '#4ADE80', 'success-container' => '#0F2E1C', 'on-success-container' => '#86EFAC',
        'warning' => '#FBBF24', 'warning-fill' => '#F59E0B', 'warning-container' => '#33260A', 'on-warning-container' => '#FCD34D',
        'error' => '#F87171', 'error-container' => '#3A1414', 'on-error-container' => '#FCA5A5',
        'info' => '#60A5FA', 'info-container' => '#132443', 'on-info-container' => '#93C5FD',
        'points' => '#FACC15', 'points-text' => '#FACC15', 'points-container' => '#33260A', 'on-points-container' => '#FDE68A',
        'neutral-container' => '#232833', 'on-neutral-container' => '#C4C9D2',
        'credit' => '#4ADE80',
    ];

    /** @var array<string, array> */
    protected static array $cache = [];

    public static function for(?Tenant $tenant): array
    {
        $key = $tenant ? $tenant->id.'@'.optional($tenant->updated_at)->timestamp.'#'.md5(json_encode($tenant->settings)) : 'platform';

        return self::$cache[$key] ??= self::build(fn (string $k, $d = null) => $tenant ? $tenant->setting($k, $d) : data_get(config('tenancy.defaults'), $k, $d), $tenant);
    }

    /** Build from an arbitrary settings document (used by the branding editor preview). */
    public static function fromSettings(array $settings): array
    {
        return self::build(fn (string $k, $d = null) => data_get($settings, $k, $d), null);
    }

    protected static function build(callable $s, ?Tenant $tenant): array
    {
        $c = fn (string $k) => G::normalize($s('theme.colors.'.$k));

        // ---- neutrals (accepted only if they pass the guard) -------------
        $light = self::LIGHT;
        $surface = $c('surface');
        $onSurface = $c('on_surface');
        if ($surface && $onSurface && G::contrast($onSurface, $surface) >= 7) {
            $light['surface'] = $surface;
            $light['text'] = $onSurface;
        }
        $background = $c('background');
        $muted = $c('muted') ?? $light['muted'];
        // A background identical to the surface would make cards disappear on
        // the web dashboard, so the platform background is kept in that case.
        if ($background && $background !== $light['surface'] && G::contrast($muted, $background) >= 4.5) {
            $light['bg'] = $background;
        }
        if (G::contrast($muted, $light['bg']) >= 4.5 && G::contrast($muted, $light['surface']) >= 4.5) {
            $light['muted'] = $muted;
        }
        if ($outline = $c('outline')) {
            $light['hairline'] = $outline;
        }

        // ---- brand roles -------------------------------------------------
        $seed = $c('primary') ?? self::PLATFORM_PRIMARY;
        $primary = G::guardAction($seed, $c('on_primary'), $light['bg']);
        $secondarySeed = $c('secondary') ?? G::withTone($seed, G::tone($seed), 0.6);
        $secondary = G::guardAction($secondarySeed, $c('on_secondary'), $light['bg']);

        // dark mode: tone 80/20 unless dark_colors.* provided (then guarded the same way)
        $darkPrimarySeed = G::normalize($s('theme.dark_colors.primary'));
        $darkPrimary = $darkPrimarySeed
            ? self::guardDark($darkPrimarySeed)
            : G::darkRole($seed, self::DARK['surface']);
        $darkSecondarySeed = G::normalize($s('theme.dark_colors.secondary'));
        $darkSecondary = $darkSecondarySeed ? self::guardDark($darkSecondarySeed) : G::darkRole($secondarySeed, self::DARK['surface']);

        // ---- brand surfaces carrying white text -------------------------
        $hg = array_values(array_filter(array_map(fn ($v) => G::normalize($v), (array) ($s('theme.header_gradient') ?? []))));
        $headerStart = G::guardOnWhite($hg[0] ?? $primary['fill']);
        $headerEnd = G::guardOnWhite($hg[1] ?? G::withTone($primary['fill'], G::tone($primary['fill']) - 12));
        $walletStart = G::guardOnWhite($c('wallet_card_start') ?? $primary['fill']);
        $walletEnd = G::guardOnWhite($c('wallet_card_end') ?? G::withTone($primary['fill'], G::tone($primary['fill']) - 14));
        $headerStyle = in_array($s('theme.header_style'), ['gradient', 'solid', 'image'], true) ? $s('theme.header_style') : 'gradient';
        if ($headerStyle === 'solid') {
            $headerEnd = $headerStart = G::guardOnWhite($primary['fill']);
        }
        $onHeader = min(G::contrast(G::WHITE, $headerStart), G::contrast(G::WHITE, $headerEnd)) >= 4.5 ? G::WHITE : G::bestOn($headerStart);

        // ---- radii (§3.4) -------------------------------------------------
        $r = (int) max(4, min(24, (int) ($s('theme.corner_radius') ?? 16) ?: 16));
        $even = fn (float $v) => (int) (2 * round($v / 2));
        $radii = [
            'sm' => max(2, $even(0.5 * $r)), 'md' => max(4, $even(0.75 * $r)), 'lg' => $even($r),
            'xl' => $even(1.5 * $r), 'sheet' => $even(max(16, min(32, 1.75 * $r))), 'pill' => 999,
        ];

        // ---- fonts ----------------------------------------------------------
        $font = function ($name, string $fallback) {
            $name = is_string($name) ? trim($name) : '';

            return preg_match('/^[\pL\pN ]{2,60}$/u', $name) ? $name : $fallback;
        };
        $fontAr = $font($s('theme.font_family_ar'), 'Cairo');
        $fontEn = $font($s('theme.font_family_en'), 'Inter');
        $fontNum = $font($s('theme.font_family_numeric'), 'Inter');
        $families = array_values(array_unique([$fontAr, $fontEn, $fontNum]));
        $fontsUrl = 'https://fonts.googleapis.com/css2?'.implode('&', array_map(
            fn ($f) => 'family='.str_replace(' ', '+', $f).':wght@400;500;600;700;800', $families
        )).'&display=swap';

        // ---- identity -------------------------------------------------------
        $logo = Tenant::assetUrl($s('branding.logo_url'));
        $logoOnPrimary = Tenant::assetUrl($s('branding.logo_on_primary_url'));
        $icon = Tenant::assetUrl($s('branding.icon_url'));
        $needsChip = $s('branding.logo_needs_chip');
        if ($needsChip === null) {
            $needsChip = ! $logoOnPrimary || self::sameAsset($s('branding.logo_url'), $s('branding.logo_on_primary_url'));
        }

        $lightTokens = [
            'brand' => $primary['fill'], 'on-brand' => $primary['on'], 'brand-text' => $primary['text'], 'brand-soft' => $primary['soft'],
            'brand-hover' => G::withTone($primary['fill'], G::tone($primary['fill']) - 6),
            'brand-seed' => $seed,
            'accent' => $secondary['fill'], 'on-accent' => $secondary['on'], 'accent-text' => $secondary['text'], 'accent-soft' => $secondary['soft'],
        ] + $light + self::SEMANTIC_LIGHT;

        $darkTokens = [
            'brand' => $darkPrimary['fill'], 'on-brand' => $darkPrimary['on'], 'brand-text' => $darkPrimary['text'], 'brand-soft' => $darkPrimary['soft'],
            'brand-hover' => G::withTone($darkPrimary['fill'], G::tone($darkPrimary['fill']) + 6),
            'brand-seed' => $seed,
            'accent' => $darkSecondary['fill'], 'on-accent' => $darkSecondary['on'], 'accent-text' => $darkSecondary['text'], 'accent-soft' => $darkSecondary['soft'],
        ] + self::DARK + self::SEMANTIC_DARK;

        $mode = in_array($s('theme.mode'), ['system', 'light', 'dark'], true) ? $s('theme.mode') : 'system';

        return [
            'mode' => $mode,
            'light' => $lightTokens,
            'dark' => $darkTokens,
            'shared' => [
                'header-start' => $headerStart, 'header-end' => $headerEnd, 'on-header' => $onHeader,
                'wallet-start' => $walletStart, 'wallet-end' => $walletEnd,
            ],
            'radii' => $radii,
            'density' => $s('theme.density') === 'compact' ? 'compact' : 'comfortable',
            'header_style' => $headerStyle,
            'header_image' => $headerStyle === 'image' ? Tenant::assetUrl($s('theme.header_image_url')) : null,
            'pattern' => $s('theme.card_pattern_url') ? Tenant::assetUrl($s('theme.card_pattern_url')) : null,
            'fonts' => [
                'ar' => $fontAr, 'en' => $fontEn, 'numeric' => $fontNum, 'url' => $fontsUrl,
                'ar_size' => self::FONTS[$fontAr]['size'] ?? 1.0, 'ar_height' => self::FONTS[$fontAr]['height'] ?? 1.0,
            ],
            'logo' => $logo,
            'logo_on_primary' => $logoOnPrimary ?: $logo,
            'icon' => $icon ?: $logo,
            'logo_needs_chip' => (bool) $needsChip,
            'report' => [
                'seed' => $seed,
                'primary' => $primary,
                'secondary' => $secondary,
                'primary_ratio' => round(G::contrast($primary['on'], $primary['fill']), 2),
                'primary_text_ratio' => round(G::contrast($primary['text'], $light['bg']), 2),
                'seed_white_ratio' => round(G::contrast(G::WHITE, $seed), 2),
                'header_ratio' => round(min(G::contrast($onHeader, $headerStart), G::contrast($onHeader, $headerEnd)), 2),
                'wallet_ratio' => round(min(G::contrast(G::WHITE, $walletStart), G::contrast(G::WHITE, $walletEnd)), 2),
            ],
        ];
    }

    protected static function guardDark(string $hex): array
    {
        $fill = G::toneShift($hex, fn () => [self::DARK['bg']], 4.5, 0) ?? G::withTone($hex, max(70, G::tone($hex)));
        $on = G::bestOn($fill);

        return ['fill' => $fill, 'on' => $on, 'text' => $fill, 'soft' => G::blend($fill, self::DARK['surface'], 0.14)];
    }

    protected static function sameAsset(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return ! $b;
        }
        if ($a === $b) {
            return true;
        }
        $local = fn (string $p) => ! preg_match('#^(https?:)?//#', $p) && str_starts_with($p, '/') ? public_path(ltrim($p, '/')) : null;
        $pa = $local($a);
        $pb = $local($b);
        if ($pa && $pb && is_file($pa) && is_file($pb)) {
            return filesize($pa) === filesize($pb) && md5_file($pa) === md5_file($pb);
        }

        return false;
    }

    /** CSS custom properties for one token set. */
    public static function vars(array $tokens, string $indent = '    '): string
    {
        $out = '';
        foreach ($tokens as $name => $value) {
            $out .= $indent.'--'.$name.': '.$value.";\n";
            if (str_starts_with((string) $value, '#') && in_array($name, ['brand', 'accent', 'text', 'surface', 'bg', 'success', 'warning', 'error', 'info', 'header-start', 'header-end'], true)) {
                $out .= $indent.'--'.$name.'-rgb: '.G::rgbString($value).";\n";
            }
        }

        return $out;
    }
}
