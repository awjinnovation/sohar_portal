<?php

namespace App\Services\Gate;

use App\Models\Tenant;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The gate "operating day" of a tenant.
 *
 * Festival nights run past midnight, so a day starts at
 * `gate.day_rollover_hour` (tenant setting, default 4) in the tenant time
 * zone: a scan at 01:30 on the 19th belongs to the 18th, the manifest of the
 * 18th stays valid until 04:00 and a ticket booked for the 18th still
 * admits. Every gate count / chart / manifest / insight uses this window:
 * [D rollover:00 local, D+1 rollover:00 local).
 */
class GateDay
{
    public static function rolloverHour(?Tenant $tenant = null): int
    {
        $tenant ??= app(TenantContext::class)->get();

        return max(0, min(12, (int) ($tenant?->setting('gate.day_rollover_hour', 4) ?? 4)));
    }

    /** Operating date (Y-m-d) an instant belongs to. */
    public static function dateFor(CarbonInterface $instant, ?Tenant $tenant = null): string
    {
        return Carbon::instance($instant)->setTimezone(TenantTime::zone($tenant))
            ->subHours(self::rolloverHour($tenant))->toDateString();
    }

    public static function today(?Tenant $tenant = null): string
    {
        return self::dateFor(Carbon::now(), $tenant);
    }

    /**
     * UTC bounds of an operating day: [from, to).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(string $date, ?Tenant $tenant = null): array
    {
        $from = Carbon::parse($date, TenantTime::zone($tenant))->startOfDay()->addHours(self::rolloverHour($tenant));

        return [$from->copy()->utc(), $from->copy()->addDay()->utc()];
    }

    /** Valid Y-m-d from user input, else today. */
    public static function parse(?string $date, ?Tenant $tenant = null): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return Carbon::createFromFormat('!Y-m-d', $date)->toDateString();
            } catch (\Throwable) {
            }
        }

        return self::today($tenant);
    }
}
