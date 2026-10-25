<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel language (EN/AR switch). The choice lives in the session
 * (`admin_locale`); Arabic is the default. Panel strings are written in
 * Arabic and translated through lang/en.json (`__('…')`), so an
 * untranslated string simply stays Arabic.
 */
class SetAdminLocale
{
    public const SESSION_KEY = 'admin_locale';

    public const LOCALES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get(self::SESSION_KEY, 'ar');
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        return $next($request);
    }

    public static function current(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ar';
    }
}
