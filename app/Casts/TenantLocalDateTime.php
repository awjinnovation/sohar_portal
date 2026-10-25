<?php

namespace App\Casts;

use App\Support\TenantTime;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A wall-clock datetime stored as the tenant's local time ("Y-m-d H:i:s",
 * no offset) — what organizers type in the admin panel.
 *
 * get: a Carbon in the tenant time zone (so ->format('H:i') shows the typed
 *      time and ->toIso8601String() carries the right offset, e.g. +04:00).
 * set: strings without an offset are taken as tenant-local as is; Carbon /
 *      DateTime values (any zone) are converted to tenant-local first.
 */
class TenantLocalDateTime implements CastsAttributes, SerializesCastableAttributes
{
    /** Each access returns a fresh Carbon (callers may mutate it). */
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, $this->zone($attributes));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $zone = $this->zone($attributes);

        $carbon = $value instanceof DateTimeInterface
            ? Carbon::instance($value)->setTimezone($zone)
            : Carbon::parse((string) $value, $zone)->setTimezone($zone);

        return $carbon->format('Y-m-d H:i:s');
    }

    /** toArray()/JSON: ISO 8601 with the tenant offset. */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value)->toIso8601String() : $value;
    }

    private function zone(array $attributes): string
    {
        return TenantTime::zoneForTenantId(isset($attributes['tenant_id']) ? (int) $attributes['tenant_id'] : null);
    }
}
