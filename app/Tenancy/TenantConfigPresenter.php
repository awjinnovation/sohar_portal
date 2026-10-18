<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Builds the public tenant config document (contract §5). Server-only
 * settings (payments, messages, legacy, gateway credentials) are omitted.
 */
class TenantConfigPresenter
{
    public static function present(Tenant $tenant): array
    {
        $s = $tenant->resolvedSettings();
        $url = fn ($v) => Tenant::assetUrl($v) ?? '';

        $branding = $s['branding'];
        foreach (['logo_url', 'logo_on_primary_url', 'icon_url', 'splash_url'] as $k) {
            $branding[$k] = $url($branding[$k] ?? null);
        }
        $branding['onboarding'] = array_values(array_map(fn ($o) => array_merge($o, ['image_url' => $url($o['image_url'] ?? null)]), $branding['onboarding'] ?? []));

        $modules = array_map('boolval', $s['modules']);
        $navigation = array_values(array_filter($s['navigation'], function ($item) use ($modules) {
            $module = ['wallet' => 'wallet', 'tickets' => 'tickets', 'events' => 'events', 'map' => 'map', 'calendar' => 'calendar', 'vendors' => 'vendors'][$item] ?? null;

            return $module === null || ($modules[$module] ?? false);
        }));

        $theme = $s['theme'];
        foreach (['header_image_url', 'card_pattern_url'] as $k) {
            $theme[$k] = isset($theme[$k]) && $theme[$k] ? $url($theme[$k]) : null;
        }

        $tenantBlock = $s['tenant'];
        unset($tenantBlock['organization']);

        return [
            'tenant' => array_merge([
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ], $tenantBlock),
            'branding' => $branding,
            'theme' => $theme,
            'modules' => $modules,
            'navigation' => $navigation,
            'map' => $s['map'],
            'wallet' => $s['wallet'],
            'points' => $s['points'],
            'app' => $s['app'],
            // §5a optional presentation keys (pass-through)
            'home_blocks' => array_values($s['home_blocks'] ?? []),
            'quick_actions' => array_values($s['quick_actions'] ?? []),
            'merchant' => $s['merchant'] ?? ['confirm_threshold_minor' => 20000],
            'auth' => array_merge(
                ['country_code' => '968', 'phone_length' => 8, 'phone_hint' => '9123 4567'],
                (array) ($s['auth'] ?? []),
            ),
        ];
    }
}
