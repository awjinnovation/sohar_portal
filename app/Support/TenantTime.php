<?php

namespace App\Support;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Tenant time zone helpers.
 *
 * Two kinds of datetimes exist in the database:
 * - instants (created_at, paid_at, used_at, expires_at...) stored in UTC
 *   (config('app.timezone'));
 * - wall-clock schedule times typed by organizers (events.start_time /
 *   end_time, announcements.start_datetime / end_datetime) stored as the
 *   tenant's local time — see App\Casts\TenantLocalDateTime.
 *
 * Every API datetime is printed with iso(): ISO 8601 in the tenant's time
 * zone with an explicit offset (e.g. 2026-10-18T18:00:00+04:00).
 */
class TenantTime
{
    public static function zone(?Tenant $tenant = null): string
    {
        $tenant ??= app(TenantContext::class)->get();
        $tz = (string) ($tenant?->setting('tenant.timezone') ?: config('tenancy.defaults.tenant.timezone', config('app.timezone')));

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone');
    }

    /** Zone of a tenant id (cached per request; falls back to the current tenant). */
    public static function zoneForTenantId(?int $tenantId): string
    {
        static $cache = [];
        $current = app(TenantContext::class)->get();
        if (! $tenantId || $current?->id === $tenantId) {
            return self::zone($current);
        }

        $key = spl_object_id(app()).':'.$tenantId;

        return $cache[$key] ??= self::zone(Tenant::find($tenantId));
    }

    /** ISO 8601 with the tenant offset, or null. */
    public static function iso(?DateTimeInterface $value, ?Tenant $tenant = null): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::instance($value)->setTimezone(self::zone($tenant))->toIso8601String();
    }

    /** iso() in the zone of a given tenant id (model serialization). */
    public static function isoForTenantId(?DateTimeInterface $value, ?int $tenantId): ?string
    {
        return $value ? Carbon::instance($value)->setTimezone(self::zoneForTenantId($tenantId))->toIso8601String() : null;
    }

    /**
     * A date-only value (e.g. tickets.booking_date) as the start of that
     * calendar day in the tenant zone: 2026-10-18T00:00:00+04:00.
     */
    public static function isoDate(?DateTimeInterface $value, ?Tenant $tenant = null): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value->format('Y-m-d'), self::zone($tenant))->toIso8601String();
    }

    /** "Now" as stored in wall-clock (tenant-local) columns, for queries. */
    public static function localNow(?Tenant $tenant = null): string
    {
        return Carbon::now(self::zone($tenant))->format('Y-m-d H:i:s');
    }

    /** Today's date in the tenant time zone (Y-m-d). */
    public static function today(?Tenant $tenant = null): string
    {
        return Carbon::now(self::zone($tenant))->toDateString();
    }
}
