<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Booking site API (/api/v1/booking/events/{event}/*): the tenant comes from
 * the event, not from an X-Tenant header, so one booking site serves every
 * tenant and the public event URL alone decides the response (CDN-cacheable).
 *
 *  - an inactive event, an inactive tenant, or a tenant without the
 *    `web_booking` / `tickets` modules is a 404 (EVENT_NOT_FOUND);
 *  - language: `lang` (query or body) when the tenant offers it, else the
 *    tenant's default; Accept-Language is ignored, so the URL stays the
 *    cache key;
 *  - the public GET leaves with `Cache-Control: public, max-age=0,
 *    s-maxage=…` and no Vary on X-Tenant / Accept-Language
 *    (config/booking.php cdn_max_age).
 *
 * Runs instead of ResolveTenant (routes/api.php) and before cache.api, so the
 * shared response cache is keyed on the event's tenant.
 */
class ResolveBookingEvent
{
    public const EVENT = 'booking.event';

    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $event = Event::withoutTenantScope()->where('is_active', true)->find((int) $request->route('event'));
        $tenant = $event ? Tenant::find($event->tenant_id) : null;

        if (! $tenant || ! $tenant->isActive() || ! $tenant->moduleEnabled('web_booking') || ! $tenant->moduleEnabled('tickets')) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
                'code' => 'EVENT_NOT_FOUND',
            ], 404, ['Cache-Control' => 'no-store']);
        }

        $this->context->set($tenant);
        app()->setLocale(self::locale($request, $tenant));
        $request->attributes->set(self::EVENT, $event);

        $response = $next($request);

        if ($request->isMethod('GET') && $request->routeIs('api.booking.event') && in_array($response->getStatusCode(), [200, 304], true)) {
            $ttl = max(0, (int) config('booking.cdn_max_age', 15));
            $response->headers->set('Cache-Control', $ttl > 0
                ? "public, max-age=0, s-maxage={$ttl}, stale-while-revalidate={$ttl}"
                : 'no-cache');
            $response->headers->remove('Vary');
        } elseif (! $response->headers->hasCacheControlDirective('no-store')) {
            // Sign-in, tickets and checkout are per visitor.
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** `lang` from the query or the body when the tenant offers it, else its default locale. */
    public static function locale(Request $request, Tenant $tenant): string
    {
        $locales = array_values(array_intersect(['ar', 'en'], (array) $tenant->setting('tenant.locales', ['ar', 'en']))) ?: ['ar'];
        $asked = strtolower((string) ($request->query('lang') ?? $request->input('lang', '')));
        if (in_array($asked, $locales, true)) {
            return $asked;
        }
        $default = (string) $tenant->setting('tenant.default_locale', $locales[0]);

        return in_array($default, $locales, true) ? $default : $locales[0];
    }
}
