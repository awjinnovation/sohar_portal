<?php

namespace App\Http\Middleware;

use App\Support\PublicApiCache;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared cache for public, user-independent GET endpoints:
 * `->middleware('cache.api:catalog')` (tiers in config/api_cache.php).
 *
 *  - key: tenant + tenant version + language + path + sorted query, so an
 *    admin change (version bump) is visible on the next request;
 *  - a miss is computed by one request while concurrent ones wait for it
 *    (no stampede on the database when a popular entry expires);
 *  - ETag on every response, 304 for a matching If-None-Match;
 *  - only 200 JSON responses without cookies are stored;
 *  - X-Cache: HIT | MISS | BYPASS.
 *
 * Never use it on a route whose response depends on the signed-in user.
 */
class CachePublicApi
{
    public function __construct(private TenantContext $tenants) {}

    public function handle(Request $request, Closure $next, string $tier = 'catalog'): Response
    {
        $tenantId = $this->tenants->id();
        if (! config('api_cache.enabled') || ! $request->isMethodCacheable() || $tenantId === null) {
            return $next($request);
        }

        $key = PublicApiCache::key($tenantId, $request);
        $entry = Cache::get($key);
        $state = 'HIT';

        if ($entry === null) {
            $lock = Cache::lock($key.':lock', 15);
            try {
                $lock->block((int) config('api_cache.lock_wait_seconds', 5));
            } catch (LockTimeoutException) {
                // The first request is slow: answer without the cache.
                return $this->mark($next($request), 'BYPASS');
            }

            try {
                $entry = Cache::get($key);
                if ($entry === null) {
                    $response = $next($request);
                    if (! $this->storable($response)) {
                        return $this->mark($response, 'BYPASS');
                    }
                    $entry = [
                        'body' => (string) $response->getContent(),
                        'type' => (string) $response->headers->get('Content-Type', 'application/json'),
                        'etag' => $response->headers->get('ETag') ?: '"'.sha1((string) $response->getContent()).'"',
                    ];
                    Cache::put($key, $entry, $this->ttl($tier));
                    $state = 'MISS';
                }
            } finally {
                $lock->release();
            }
        }

        return $this->respond($request, $entry, $state);
    }

    /** 200 JSON without cookies and without an explicit no-store. */
    private function storable(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type', ''), 'json')
            && ! $response->headers->has('Set-Cookie')
            // (Symfony's default "no-cache, private" is not a refusal.)
            && ! $response->headers->hasCacheControlDirective('no-store');
    }

    private function ttl(string $tier): int
    {
        $ttl = (int) config("api_cache.ttl.$tier", config('api_cache.ttl.catalog', 120));
        $jitter = (int) round($ttl * (float) config('api_cache.jitter', 0.1));

        return max(1, $ttl + random_int(-$jitter, $jitter));
    }

    private function respond(Request $request, array $entry, string $state): Response
    {
        $headers = [
            'ETag' => $entry['etag'],
            // Clients and proxies revalidate (cheap 304); the tenant is a header.
            'Cache-Control' => 'no-cache',
            'Vary' => 'X-Tenant, Accept-Language',
            'X-Cache' => $state,
        ];

        $sent = array_map(fn ($t) => trim(str_replace('W/', '', $t)), explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array($entry['etag'], $sent, true)) {
            return response('', 304, $headers);
        }

        return response($entry['body'], 200, $headers + ['Content-Type' => $entry['type']]);
    }

    private function mark(Response $response, string $state): Response
    {
        $response->headers->set('X-Cache', $state);

        return $response;
    }
}
