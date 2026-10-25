<?php

namespace Database\Seeders;

use App\Models\Tenant;

/**
 * Fills the generic legal-page templates (terms / privacy) from the current
 * tenant's settings, so no organizer name, e-mail or phone is hard-coded.
 *
 * Placeholders: {app_en} {app_ar} (tenant name), {org_en} {org_ar}
 * (tenant.organization, falls back to the name), {email} (tenant.support.email),
 * {phone} (tenant.support.phone).
 */
class LegalTemplate
{
    public static function fill(string $html, ?Tenant $tenant): string
    {
        $name = fn (string $l) => $tenant?->localizedName($l) ?? config('app.name');
        $org = fn (string $l) => (string) ($tenant?->setting("tenant.organization.$l") ?: $name($l));
        $email = (string) ($tenant?->setting('tenant.support.email') ?: '');
        $phone = (string) ($tenant?->setting('tenant.support.phone') ?: '');

        $html = strtr($html, [
            '{app_en}' => e($name('en')), '{app_ar}' => e($name('ar')),
            '{org_en}' => e($org('en')), '{org_ar}' => e($org('ar')),
            '{email}' => e($email), '{phone}' => e($phone),
        ]);

        // Drop contact lines whose value is not configured.
        return preg_replace('/^[^\n]*(?:Email|Phone|البريد الإلكتروني|الهاتف): (?:<br>)?\n/mu', '', $html) ?? $html;
    }
}
