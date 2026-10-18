<?php

namespace App\Tenancy;

/**
 * Translates between the legacy flat `app_settings` keys (served by the old
 * /api/v1/config endpoints and seeded by the former FlutterAppConfigSeeder)
 * and the structured tenant settings document (contract §5).
 */
class LegacySettingsMapper
{
    /**
     * legacy key => dotted path in the tenant settings document.
     */
    public const MAP = [
        'app_tagline' => 'tenant.tagline.ar',
        'app_tagline_en' => 'tenant.tagline.en',
        'default_language' => 'tenant.default_locale',
        'supported_languages' => 'tenant.locales',
        'default_currency' => 'tenant.currency',
        'support_email' => 'tenant.support.email',
        'support_phone' => 'tenant.support.phone',
        'instagram_url' => 'tenant.social.instagram',
        'twitter_url' => 'tenant.social.x',
        'facebook_url' => 'tenant.social.facebook',
        'terms_url' => 'tenant.legal.terms_url',
        'privacy_url' => 'tenant.legal.privacy_url',

        'app_logo_url' => 'branding.logo_url',
        'app_icon_url' => 'branding.icon_url',
        'splash_image_url' => 'branding.splash_url',

        'primary_color' => 'theme.colors.primary',
        'accent_color' => 'theme.colors.secondary',
        'background_color' => 'theme.colors.background',
        'surface_color' => 'theme.colors.surface',
        'error_color' => 'theme.colors.error',
        'success_color' => 'theme.colors.success',
        'warning_color' => 'theme.colors.warning',
        'text_primary_color' => 'theme.colors.on_surface',
        'text_secondary_color' => 'theme.colors.muted',
        'font_family_ar' => 'theme.font_family_ar',
        'font_family_en' => 'theme.font_family_en',
        'default_theme_mode' => 'theme.mode',

        'enable_notifications' => 'modules.notifications',
        'enable_favorites' => 'modules.favorites',
        'enable_tickets' => 'modules.tickets',
        'enable_maps' => 'modules.map',

        'default_map_latitude' => 'map.center.lat',
        'default_map_longitude' => 'map.center.lng',
        'default_map_zoom' => 'map.zoom',

        'app_version' => 'app.latest_version',
        'min_supported_version' => 'app.min_version',
        'force_update' => 'app.force_update',
        'maintenance_mode' => 'app.maintenance_mode',
        'maintenance_message' => 'app.maintenance_message.ar',
        'maintenance_message_en' => 'app.maintenance_message.en',
    ];

    /** Keys that map to the tenant's `name` column instead of settings. */
    public const NAME_KEYS = ['app_name' => 'ar', 'app_name_en' => 'en'];

    /**
     * @param  array<string, mixed>  $legacy  flat key => typed value
     * @return array{name: array, settings: array}
     */
    public static function toTenant(array $legacy): array
    {
        $settings = [];
        $name = [];

        foreach ($legacy as $key => $value) {
            if (isset(self::NAME_KEYS[$key])) {
                $name[self::NAME_KEYS[$key]] = $value;

                continue;
            }
            if (isset(self::MAP[$key])) {
                data_set($settings, self::MAP[$key], $value);

                continue;
            }
            $settings['legacy'][$key] = $value;
        }

        return ['name' => $name, 'settings' => $settings];
    }

    /**
     * Flat legacy representation of a tenant (for /api/v1/config).
     */
    public static function toLegacy(\App\Models\Tenant $tenant): array
    {
        $flat = [
            'app_name' => $tenant->localizedName('ar'),
            'app_name_en' => $tenant->localizedName('en'),
        ];

        foreach (self::MAP as $key => $path) {
            $flat[$key] = $tenant->setting($path);
        }

        $flat['enable_dark_mode'] = $tenant->setting('theme.mode') !== 'light' || ! empty($tenant->setting('theme.dark_colors'));
        $flat['currency_decimals'] = $tenant->setting('tenant.currency_decimals');

        return array_merge($flat, (array) $tenant->setting('legacy', []));
    }
}
