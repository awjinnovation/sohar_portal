<?php

namespace App\Support;

/** Small geo helpers (portable haversine; no DB functions needed). */
class Geo
{
    public const EARTH_RADIUS_M = 6371008.8;

    /** "lat,lng" → [lat, lng] or null when missing / out of range. */
    public static function parse(?string $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $value, $m)) {
            return null;
        }
        $lat = (float) $m[1];
        $lng = (float) $m[2];

        return abs($lat) <= 90 && abs($lng) <= 180 ? [$lat, $lng] : null;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
