<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Tenancy\LegacySettingsMapper;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Legacy /api/v1/config/* endpoints (pre-platform app builds). Values are
 * now derived from the current tenant's settings (see /tenant/config for the
 * structured document), plus the tenant's *public* app_settings rows.
 */
class AppConfigController extends Controller
{
    public function __construct(private TenantContext $context) {}

    private function tenant(): Tenant
    {
        return $this->context->get();
    }

    /** Flat legacy map: tenant-derived keys + public app_settings. */
    private function flat(): array
    {
        return array_merge(
            LegacySettingsMapper::toLegacy($this->tenant()),
            AppSetting::getPublicSettings()
        );
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->flat(),
            'timestamp' => \App\Support\TenantTime::iso(now()),
        ]);
    }

    /**
     * Single *public* configuration value. Non-public app_settings are never
     * exposed here.
     */
    public function show(string $key): JsonResponse
    {
        $flat = $this->flat();

        if (! array_key_exists($key, $flat) || $flat[$key] === null) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration key not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['key' => $key, 'value' => $flat[$key]],
        ]);
    }

    public function theme(): JsonResponse
    {
        $t = $this->tenant();
        $c = fn (string $k) => $t->setting("theme.colors.$k");

        return response()->json([
            'success' => true,
            'data' => [
                'primary_color' => $c('primary'),
                'primary_dark_color' => $c('wallet_card_end') ?? $c('primary'),
                'primary_light_color' => $c('primary'),
                'accent_color' => $c('secondary'),
                'accent_dark_color' => $c('secondary'),
                'accent_light_color' => $c('secondary'),
                'background_color' => $c('background'),
                'surface_color' => $c('surface'),
                'error_color' => $c('error'),
                'success_color' => $c('success'),
                'warning_color' => $c('warning'),
                'text_primary_color' => $c('on_surface'),
                'text_secondary_color' => $c('muted'),
                'text_disabled_color' => $c('outline'),
                'default_theme_mode' => $t->setting('theme.mode'),
                'enable_dark_mode' => $t->setting('theme.mode') !== 'light',
                'font_family_ar' => $t->setting('theme.font_family_ar'),
                'font_family_en' => $t->setting('theme.font_family_en'),
            ],
        ]);
    }

    public function branding(): JsonResponse
    {
        $t = $this->tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => $t->localizedName('ar'),
                'app_name_en' => $t->localizedName('en'),
                'app_tagline' => $t->setting('tenant.tagline.ar'),
                'app_tagline_en' => $t->setting('tenant.tagline.en'),
                'app_logo_url' => Tenant::assetUrl($t->setting('branding.logo_url')),
                'app_icon_url' => Tenant::assetUrl($t->setting('branding.icon_url')),
                'splash_image_url' => Tenant::assetUrl($t->setting('branding.splash_url')),
                'loading_text' => $t->setting('legacy.loading_text', 'جاري التحميل...'),
                'loading_text_en' => $t->setting('legacy.loading_text_en', 'Loading...'),
                'splash_duration_ms' => $t->setting('legacy.splash_duration_ms', 2000),
            ],
        ]);
    }

    public function features(): JsonResponse
    {
        $t = $this->tenant();
        $legacy = fn (string $k, $d = true) => $t->setting("legacy.$k", $d);

        return response()->json([
            'success' => true,
            'data' => [
                'enable_notifications' => $t->moduleEnabled('notifications'),
                'enable_favorites' => $t->moduleEnabled('favorites'),
                'enable_sharing' => $legacy('enable_sharing'),
                'enable_tickets' => $t->moduleEnabled('tickets'),
                'enable_maps' => $t->moduleEnabled('map'),
                'enable_search' => $legacy('enable_search'),
                'enable_filters' => $legacy('enable_filters'),
                'show_bottom_nav' => $legacy('show_bottom_nav'),
                'enable_drawer_menu' => $legacy('enable_drawer_menu'),
                'enable_animations' => $legacy('enable_animations'),
                'enable_haptic_feedback' => $legacy('enable_haptic_feedback'),
                'enable_wallet' => $t->moduleEnabled('wallet'),
                'enable_points' => $t->moduleEnabled('points'),
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        $t = $this->tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'maintenance_mode' => (bool) $t->setting('app.maintenance_mode'),
                'maintenance_message' => $t->setting('app.maintenance_message.ar'),
                'maintenance_message_en' => $t->setting('app.maintenance_message.en'),
                'app_version' => $t->setting('app.latest_version'),
                'min_supported_version' => $t->setting('app.min_version'),
                'force_update' => (bool) $t->setting('app.force_update'),
            ],
        ]);
    }
}
