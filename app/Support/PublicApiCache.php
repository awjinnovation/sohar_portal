<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keys and invalidation of the public API response cache
 * (App\Http\Middleware\CachePublicApi, docs/caching.md).
 *
 * Invalidation is by version: each tenant has a random version string (plus
 * a global one); every key contains it, so bumping it makes all of that
 * tenant's entries unreachable at once (they expire on their own TTL). A
 * random version — not a counter — means a lost version key can never bring
 * old entries back.
 */
class PublicApiCache
{
    /** Bump once the surrounding transaction commits (no stale re-cache in between). */
    public static function bumpAfterCommit(?int $tenantId): void
    {
        DB::afterCommit(fn () => self::bump($tenantId));
    }

    /** Drop every cached response of a tenant (null = all tenants). */
    public static function bump(?int $tenantId): void
    {
        Cache::forever(self::versionKey($tenantId), Str::random(10));
    }

    public static function key(int $tenantId, Request $request): string
    {
        $query = $request->query();
        ksort($query);
        [$global, $tenant] = self::versions($tenantId);

        return sprintf('api:%s:%d:%s:%s:%s', $global, $tenantId, $tenant, app()->getLocale(),
            sha1($request->path().'?'.http_build_query($query)));
    }

    /** [global version, tenant version] in one cache round trip. */
    private static function versions(int $tenantId): array
    {
        $keys = [self::versionKey(null), self::versionKey($tenantId)];
        $found = Cache::many($keys);
        foreach ($keys as $key) {
            if ($found[$key] === null) {
                Cache::add($key, Str::random(10));
                $found[$key] = Cache::get($key, 'x');
            }
        }

        return [(string) $found[$keys[0]], (string) $found[$keys[1]]];
    }

    private static function versionKey(?int $tenantId): string
    {
        return 'api:ver:'.($tenantId ?? 'global');
    }
}
