<?php

namespace App\Services\Admin;

use App\Models\BoxOfficeOrder;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GateScan;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateStatsService;
use App\Support\OpsSignals;
use App\Support\TenantTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Admin → Operations room: one payload for the wall screen of the technical
 * operations room, polled every 10 s (cached 5 s, so several screens cost one
 * set of queries):
 *
 * - tickets  issued in the current operating day (GateDay), the last 15 and
 *            60 minutes, per sales channel, every 5 minutes over 2 hours;
 * - sms      the SMS queue (its own `sms` queue: login codes and box office
 *            payment links): waiting, being sent, oldest wait, sent / failed
 *            today, workers of its Horizon pool;
 * - orders   ticket orders waiting: visitors on the payment page, box office
 *            links not paid yet, the payments queue, paid without tickets,
 *            payments to review;
 * - gates    the busiest gates now (entries in the last 15 minutes), with
 *            today's totals, devices and a 5-minute series over the hour;
 * - system   queue worker and scheduler heartbeats (OpsSignals) and the
 *            Horizon status when Horizon runs the workers.
 *
 * Tickets, orders and gates are the current tenant's; the queues, SMS and
 * system figures are platform-wide (one worker serves every tenant).
 */
class OpsRoomMetrics
{
    /** Channels in display order. */
    public const CHANNELS = ['online', 'link', 'card', 'wallet', 'other'];

    /** Seconds without a heartbeat before a process is shown as stopped. */
    public const WORKER_STALE = 90;

    public const SCHEDULER_STALE = 150;

    public function __construct(private GateStatsService $gateStats) {}

    public function payload(Tenant $tenant): array
    {
        $now = Carbon::now();
        $date = GateDay::today($tenant);
        [$dayStart] = GateDay::window($date, $tenant);

        $system = $this->system($now);
        $workload = $this->horizonWorkload();

        return [
            'generated_at' => TenantTime::iso($now, $tenant),
            'now' => $now->getTimestamp(),
            'zone' => TenantTime::zone($tenant),
            'date' => $date,
            'tickets' => $this->tickets($dayStart, $now),
            'sms' => $this->sms($dayStart, $now, $system['worker']['status'], $workload),
            'orders' => $this->orders($now, $system['worker']['status'], $workload),
            'gates' => $this->gates($tenant, $dayStart, $now),
            'system' => $system,
        ];
    }

    private function tickets(CarbonInterface $dayStart, CarbonInterface $now): array
    {
        $today = Ticket::query()->where('tickets.created_at', '>=', $dayStart);
        $row = (clone $today)->toBase()
            ->selectRaw('count(*) as c, coalesce(sum(case when max_check_ins > 1 then max_check_ins else 1 end), 0) as people')
            ->first();
        $since = fn (int $minutes) => (clone $today)->where('tickets.created_at', '>=', $now->copy()->subMinutes($minutes))->count();
        $last15 = $since(15);

        // Channel from the payment: the box office order (card terminal or
        // payment link), the wallet, or online checkout (app and website).
        $channels = (clone $today)->toBase()
            ->leftJoin('payments', 'payments.transaction_id', '=', 'tickets.transaction_id')
            ->leftJoin('box_office_orders as bo', 'bo.payment_id', '=', 'payments.id')
            ->selectRaw("case when bo.payment_method = 'pos' then 'card' when bo.id is not null then 'link'
                when payments.method = 'wallet' then 'wallet' when payments.id is not null then 'online' else 'other' end as channel, count(*) as c")
            ->groupBy('channel')->pluck('c', 'channel');

        return [
            'today' => (int) ($row->c ?? 0),
            'people' => (int) ($row->people ?? 0),
            'last_15m' => $last15,
            'last_hour' => $since(60),
            'per_minute' => round($last15 / 15, 1),
            'channels' => array_map(fn ($k) => ['key' => $k, 'count' => (int) ($channels[$k] ?? 0)], self::CHANNELS),
            'series' => [
                'bucket_minutes' => 5,
                'counts' => $this->buckets(Ticket::query(), 'tickets.created_at', $this->seriesStart($now, 5, 24), 5, 24),
            ],
        ];
    }

    private function sms(CarbonInterface $dayStart, CarbonInterface $now, string $worker, array $workload): array
    {
        $name = $this->smsQueue();
        $q = $this->queue($name, $now) + ['workers' => $workload[$name] ?? null];
        $lastSent = OpsSignals::get('sms:last_sent_at');
        $lastWait = OpsSignals::get('sms:last_wait');

        return $q + [
            'sent_hour' => OpsSignals::sumSince('sms.sent', $now->copy()->startOfHour()),
            'sent_today' => OpsSignals::sumSince('sms.sent', $dayStart),
            'failed_today' => OpsSignals::sumSince('sms.failed', $dayStart),
            'skipped_today' => OpsSignals::sumSince('sms.skipped', $dayStart),
            'failed_jobs' => $this->failedJobs($name, $now),
            'last_sent_ago' => is_numeric($lastSent) ? max(0, $now->getTimestamp() - (int) $lastSent) : null,
            'last_wait' => is_numeric($lastWait) ? (int) $lastWait : null,
            'status' => $this->queueStatus($q, $worker, 20),
        ];
    }

    private function orders(CarbonInterface $now, string $worker, array $workload): array
    {
        $name = (string) config('payments.queue', 'payments');
        $q = $this->queue($name, $now) + ['workers' => $workload[$name] ?? null];
        $tickets = fn () => Payment::query()->where('payment_type', Payment::TYPE_TICKET);

        $checkout = $tickets()->whereIn('status', ['pending', 'processing'])
            ->where('method', '!=', Payment::METHOD_POS)
            ->where('created_at', '>=', $now->copy()->subMinutes(30))->count();
        $links = BoxOfficeOrder::query()->where('status', BoxOfficeOrder::STATUS_PENDING)
            ->where('expires_at', '>', $now)->count();
        // Paid in the last day, tickets not issued after 2 minutes; and box
        // office orders paid after expiry (to refund).
        $unissued = $tickets()->where('status', 'completed')->whereNull('fulfilled_at')
            ->whereBetween('created_at', [$now->copy()->subDay(), $now->copy()->subMinutes(2)])->count()
            + BoxOfficeOrder::query()->where('status', BoxOfficeOrder::STATUS_PAID_UNFULFILLED)->count();
        $review = Payment::query()->where('needs_review', true)->count()
            + BoxOfficeOrder::query()->where('needs_review', true)->count();

        $status = $this->queueStatus($q, $worker, 50);
        if ($unissued > 0) {
            $status = 'critical';
        } elseif ($review > 0 && $status === 'ok') {
            $status = 'warn';
        }

        return $q + [
            'checkout' => $checkout,
            'links' => $links,
            'unissued' => $unissued,
            'review' => $review,
            'failed_jobs' => $this->failedJobs($name, $now),
            'status' => $status,
        ];
    }

    private function gates(Tenant $tenant, CarbonInterface $dayStart, CarbonInterface $now): array
    {
        $locale = app()->getLocale();
        $s = $this->gateStats;
        $gates = Gate::query()->orderBy('sort_order')->orderBy('id')->get();
        $devices = GateDevice::where('status', GateDevice::STATUS_ACTIVE)->get(['id', 'gate_id', 'last_seen_at', 'status', 'tenant_id']);
        $recentFrom = $now->copy()->subMinutes(15);

        $rows = GateScan::query()->where('scanned_at', '>=', $dayStart)->toBase()
            ->selectRaw('gate_id, '.$s->sumIn().' as i, '.$s->sumOut().' as o, '.$s->sumRejects().' as r, max(scanned_at) as last_at')
            ->groupBy('gate_id')->get()->keyBy(fn ($r) => (string) ($r->gate_id ?? 'none'));
        $recent = GateScan::query()->where('scanned_at', '>=', $recentFrom)->toBase()
            ->selectRaw('gate_id, '.$s->sumIn().' as i, '.$s->sumRejects().' as r')
            ->groupBy('gate_id')->get()->keyBy(fn ($r) => (string) ($r->gate_id ?? 'none'));
        $series = $this->gateSeries($this->seriesStart($now, 5, 12));

        $list = $gates->map(function (Gate $g) use ($rows, $recent, $series, $devices, $now, $tenant, $locale) {
            $key = (string) $g->id;
            $gd = $devices->where('gate_id', $g->id);

            return $this->gateRow($key, $rows->get($key), $recent->get($key), $series[$key] ?? null, $now, $tenant) + [
                'id' => $g->id,
                'code' => $g->code,
                'name' => $g->localizedName($locale),
                'active' => (bool) $g->active,
                'devices' => $gd->count(),
                'devices_online' => $gd->filter(fn (GateDevice $d) => $d->connectivity() === 'online')->count(),
            ];
        });
        // Scans of the staff app without a gate (before gates existed).
        if ($rows->has('none') || $recent->has('none')) {
            $list->push($this->gateRow('none', $rows->get('none'), $recent->get('none'), $series['none'] ?? null, $now, $tenant) + [
                'id' => null, 'code' => '', 'name' => __('تطبيق الموظفين (بلا بوابة)'), 'active' => true, 'devices' => 0, 'devices_online' => 0,
            ]);
        }

        $list = $list->sort(fn ($a, $b) => [$b['in_15m'], $b['in_today'], $a['name']] <=> [$a['in_15m'], $a['in_today'], $b['name']])->values();
        $in = $list->sum('in_today');
        $out = $list->sum('out_today');
        $in15 = $list->sum('in_15m');

        return [
            'in' => $in,
            'out' => $out,
            'rejects' => $list->sum('rejects_today'),
            'inside' => max(0, $in - $out),
            'in_15m' => $in15,
            'per_minute' => round($in15 / 15, 1),
            'devices_online' => $devices->filter(fn (GateDevice $d) => $d->connectivity() === 'online')->count(),
            'devices_total' => $devices->count(),
            'list' => $list->all(),
        ];
    }

    private function gateRow(string $key, ?object $today, ?object $recent, ?array $series, CarbonInterface $now, Tenant $tenant): array
    {
        $last = $today?->last_at ? Carbon::parse($today->last_at, 'UTC') : null;
        $in15 = (int) ($recent->i ?? 0);

        return [
            'in_15m' => $in15,
            'rejects_15m' => (int) ($recent->r ?? 0),
            'per_minute' => round($in15 / 15, 1),
            'in_today' => (int) ($today->i ?? 0),
            'out_today' => (int) ($today->o ?? 0),
            'rejects_today' => (int) ($today->r ?? 0),
            'last_scan_at' => TenantTime::iso($last, $tenant),
            'last_scan_ago' => $last ? max(0, $now->getTimestamp() - $last->getTimestamp()) : null,
            'series' => $series ?? array_fill(0, 12, 0),
        ];
    }

    /** @return array<string, list<int>> gate id (or "none") => entries per 5 minutes over the last hour */
    private function gateSeries(CarbonInterface $from): array
    {
        $s = $this->gateStats;
        $rows = GateScan::query()->where('scanned_at', '>=', $from)->toBase()
            ->selectRaw('gate_id, '.$this->bucketExpr('scanned_at').' as b, '.$s->sumIn().' as i', [$from->copy()->utc()->format('Y-m-d H:i:s')])
            ->groupBy('gate_id', 'b')->get();
        $out = [];
        foreach ($rows as $r) {
            $key = (string) ($r->gate_id ?? 'none');
            $out[$key] ??= array_fill(0, 12, 0);
            $b = (int) $r->b;
            if ($b >= 0 && $b < 12) {
                $out[$key][$b] += (int) $r->i;
            }
        }

        return $out;
    }

    /**
     * Start of $n buckets of $minutes on the clock (…:00, …:05), the last one
     * being the current, still open bucket.
     */
    private function seriesStart(CarbonInterface $now, int $minutes, int $n): Carbon
    {
        return Carbon::instance($now)->utc()->floorMinutes($minutes)->subMinutes(($n - 1) * $minutes);
    }

    /** @return list<int> count per $minutes bucket from $from, $n buckets */
    private function buckets($query, string $column, CarbonInterface $from, int $minutes, int $n): array
    {
        $counts = array_fill(0, $n, 0);
        $rows = $query->where($column, '>=', $from)->toBase()
            ->selectRaw($this->bucketExpr($column, $minutes).' as b, count(*) as c', [$from->copy()->utc()->format('Y-m-d H:i:s')])
            ->groupBy('b')->get();
        foreach ($rows as $r) {
            $b = (int) $r->b;
            if ($b >= 0 && $b < $n) {
                $counts[$b] += (int) $r->c;
            }
        }

        return $counts;
    }

    /** Bucket index of $column from a bound start (UTC), per driver. */
    private function bucketExpr(string $column, int $minutes = 5): string
    {
        $seconds = $minutes * 60;

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(CAST(((julianday({$column}) - julianday(?)) * 86400 + 0.5) AS INTEGER) / {$seconds})",
            'pgsql' => "FLOOR(EXTRACT(EPOCH FROM ({$column} - CAST(? AS timestamp))) / {$seconds})",
            default => "FLOOR(TIMESTAMPDIFF(SECOND, ?, {$column}) / {$seconds})",
        };
    }

    /** The queue SendOtpSms puts itself on (`sms`; `default` before it had its own). */
    private function smsQueue(): string
    {
        return (string) ((new \App\Jobs\SendOtpSms('', ''))->queue ?: 'default');
    }

    /** @return array<string, int> queue => worker processes of its Horizon pool (empty without Horizon) */
    private function horizonWorkload(): array
    {
        if (! interface_exists(\Laravel\Horizon\Contracts\WorkloadRepository::class) || ! app()->bound(\Laravel\Horizon\Contracts\WorkloadRepository::class)) {
            return [];
        }
        try {
            return collect(app(\Laravel\Horizon\Contracts\WorkloadRepository::class)->get())
                ->mapWithKeys(fn ($w) => [(string) $w['name'] => (int) ($w['processes'] ?? 0)])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** running | paused | inactive, or null when Horizon is not installed. */
    private function horizonStatus(): ?string
    {
        if (! interface_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class) || ! app()->bound(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
            return null;
        }
        try {
            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
            if (! $masters) {
                return 'inactive';
            }

            return collect($masters)->contains(fn ($m) => $m->status === 'paused') ? 'paused' : 'running';
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Jobs of a queue on the default connection (all zero on the sync driver). */
    private function queue(string $name, CarbonInterface $now): array
    {
        try {
            $queue = Queue::connection();
            $oldest = $queue->creationTimeOfOldestPendingJob($name);

            return [
                'queue' => $name,
                'pending' => (int) $queue->pendingSize($name),
                'delayed' => (int) $queue->delayedSize($name),
                'reserved' => (int) $queue->reservedSize($name),
                'oldest_wait' => $oldest ? max(0, $now->getTimestamp() - (int) $oldest) : null,
                'available' => true,
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['queue' => $name, 'pending' => 0, 'delayed' => 0, 'reserved' => 0, 'oldest_wait' => null, 'available' => false];
        }
    }

    private function failedJobs(string $queue, CarbonInterface $now): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))
                ->where('queue', $queue)->where('failed_at', '>=', $now->copy()->subDay())->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** ok | warn | critical, from what waits and whether the worker runs. */
    private function queueStatus(array $q, string $worker, int $busy): string
    {
        if (! $q['available']) {
            return 'critical';
        }
        $wait = (int) ($q['oldest_wait'] ?? 0);
        if (($q['pending'] > 0 && $worker === 'stopped') || $wait > 300) {
            return 'critical';
        }

        return ($wait > 60 || $q['pending'] >= $busy) ? 'warn' : 'ok';
    }

    private function system(CarbonInterface $now): array
    {
        $beat = function (string $process, int $stale) use ($now) {
            $at = OpsSignals::lastBeat($process);
            $ago = $at ? max(0, $now->getTimestamp() - $at) : null;

            return ['ago' => $ago, 'status' => $ago === null ? 'unknown' : ($ago <= $stale ? 'running' : 'stopped')];
        };

        return [
            'queue_driver' => (string) config('queue.default'),
            'worker' => config('queue.default') === 'sync' ? ['ago' => 0, 'status' => 'running'] : $beat('worker', self::WORKER_STALE),
            'scheduler' => $beat('scheduler', self::SCHEDULER_STALE),
            'horizon' => $this->horizonStatus(),
        ];
    }
}
