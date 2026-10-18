<?php

namespace App\View;

use App\Models\Tenant;

/**
 * Locale/direction for customer-facing web pages (payment results, tickets,
 * legal pages): ?lang= when the tenant supports it, else the tenant's
 * default_locale. Also applies it to the app so Carbon/translations follow.
 */
class PublicPage
{
    public static function locale(?Tenant $tenant): string
    {
        $locales = array_values(array_intersect(['ar', 'en'], (array) ($tenant?->setting('tenant.locales', ['ar', 'en']) ?? ['ar', 'en']))) ?: ['ar', 'en'];
        $requested = strtolower((string) request()->query('lang', ''));
        $default = (string) ($tenant?->setting('tenant.default_locale') ?: app()->getLocale());
        $lang = in_array($requested, ['ar', 'en'], true) ? $requested : (in_array($default, ['ar', 'en'], true) ? $default : 'ar');
        if (! in_array($lang, $locales, true) && $requested === '') {
            $lang = $locales[0];
        }
        app()->setLocale($lang);

        return $lang;
    }

    /** Current URL with a different ?lang= (keeps other query params, e.g. tenant / transaction_id). */
    public static function langUrl(string $lang): string
    {
        return request()->fullUrlWithQuery(['lang' => $lang]);
    }

    /** Localized model attribute: title / title_ar style pairs or {"ar":…,"en":…} arrays. */
    public static function pick(mixed $model, string $attr, string $lang): string
    {
        if (! $model) {
            return '';
        }
        $value = data_get($model, $attr);
        if (is_array($value)) {
            return Fmt::t($value, $lang);
        }
        if ($lang === 'ar') {
            $ar = data_get($model, $attr.'_ar');
            if (filled($ar)) {
                return (string) $ar;
            }
        }

        return (string) ($value ?? data_get($model, $attr.'_ar', ''));
    }
}
