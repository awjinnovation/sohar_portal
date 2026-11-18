<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Small live signals for the operations room (Admin → Operations room), kept
 * in the cache (Redis on the servers) and shared by every process:
 *
 * - counters per UTC hour, e.g. SMS sent / failed (`count`, `sumSince`);
 * - heartbeats of the background processes: queue worker and scheduler
 *   (`beat`, `lastBeat`);
 * - last values, e.g. how long the last SMS waited in the queue (`put`, `get`).
 *
 * Platform-wide (one queue and one worker for every tenant). Never throws:
 * a cache failure must not stop an SMS or a job.
 */
final class OpsSignals
{
    /** Hourly counters are kept two days, enough for "today" in any time zone. */
    private const COUNTER_TTL = 60 * 60 * 49;

    /** A heartbeat is written at most once per this many seconds per process. */
    private const BEAT_EVERY = 10;

    /** @var array<string, int> last beat written by this process */
    private static array $written = [];

    public static function count(string $metric, int $by = 1): void
    {
        try {
            $key = self::counterKey($metric, Carbon::now('UTC'));
            Cache::add($key, 0, self::COUNTER_TTL);
            Cache::increment($key, $by);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Sum of the hourly counters from the hour of $from up to now. */
    public static function sumSince(string $metric, CarbonInterface $from): int
    {
        try {
            $hour = Carbon::instance($from)->utc()->startOfHour();
            $now = Carbon::now('UTC');
            if ($hour->lt($now->copy()->subHours(48))) {
                $hour = $now->copy()->subHours(48)->startOfHour();
            }
            $keys = [];
            for ($h = $hour->copy(); $h->lte($now); $h->addHour()) {
                $keys[] = self::counterKey($metric, $h);
            }

            return (int) array_sum(array_map('intval', Cache::many($keys)));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    public static function beat(string $process): void
    {
        $now = time();
        if (($now - (self::$written[$process] ?? 0)) < self::BEAT_EVERY) {
            return;
        }
        self::$written[$process] = $now;
        self::put("beat:{$process}", $now);
    }

    /** Unix time of the last heartbeat, or null if none in the last day. */
    public static function lastBeat(string $process): ?int
    {
        $at = self::get("beat:{$process}");

        return is_numeric($at) ? (int) $at : null;
    }

    public static function put(string $name, mixed $value, int $ttl = 86400): void
    {
        try {
            Cache::put("ops:{$name}", $value, $ttl);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function get(string $name): mixed
    {
        try {
            return Cache::get("ops:{$name}");
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Tests: forget this process's throttle. */
    public static function resetBeats(): void
    {
        self::$written = [];
    }

    private static function counterKey(string $metric, CarbonInterface $hour): string
    {
        return "ops:{$metric}:".$hour->format('YmdH');
    }
}
