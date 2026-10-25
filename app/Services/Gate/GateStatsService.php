<?php

namespace App\Services\Gate;

use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GateScan;
use App\Models\Tenant;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gate counters for the scanner app (/gate/stats, scan counts) and the
 * admin live dashboard. All figures are per operating day (GateDay).
 *
 * in      = direction in  and result valid|reentry_ok
 * out     = direction out and result valid
 * rejects = every other result
 */
class GateStatsService
{
    public function scansOn(Tenant $tenant, string $date): Builder
    {
        [$from, $to] = GateDay::window($date, $tenant);

        return GateScan::query()->where('scanned_at', '>=', $from)->where('scanned_at', '<', $to);
    }

    /** @return array{in_today: int, out_today: int} */
    public function counts(Tenant $tenant, ?Gate $gate, ?GateDevice $device, ?string $date = null): array
    {
        $date ??= GateDay::today($tenant);
        $row = $this->scoped($this->scansOn($tenant, $date), $gate, $device)
            ->toBase()
            ->selectRaw($this->sumIn().' as i, '.$this->sumOut().' as o')
            ->first();

        return ['in_today' => (int) ($row->i ?? 0), 'out_today' => (int) ($row->o ?? 0)];
    }

    /** GET /gate/stats — the device's gate (or the device itself when it has no gate). */
    public function deviceStats(Tenant $tenant, GateDevice $device, string $date): array
    {
        $gate = $device->gate;
        $base = fn () => $this->scoped($this->scansOn($tenant, $date), $gate, $device);
        $totals = $base()->toBase()->selectRaw($this->sumIn().' as i, '.$this->sumOut().' as o, '.$this->sumRejects().' as r')->first();

        $rollover = GateDay::rolloverHour($tenant);
        $byHour = collect($this->buckets($base(), $tenant, $date, 60))
            ->map(fn ($b, $idx) => [
                'hour' => ($rollover + $idx) % 24,
                'in' => $b['in'], 'out' => $b['out'], 'rejects' => $b['rejects'],
            ])->values()->all();

        $recent = $base()->with('ticket:id,ticket_number,ticket_type')
            ->orderByDesc('scanned_at')->orderByDesc('id')->limit(20)->get()
            ->map(fn (GateScan $s) => $this->scanRow($s, $tenant))->all();

        return [
            'date' => $date,
            'scope' => $gate ? 'gate' : 'device',
            'gate_id' => $gate?->id,
            'in_today' => (int) ($totals->i ?? 0),
            'out_today' => (int) ($totals->o ?? 0),
            'rejects_today' => (int) ($totals->r ?? 0),
            'by_hour' => $byHour,
            'recent' => $recent,
        ];
    }

    /** Live admin dashboard payload (polled every 15 s). */
    public function dashboard(Tenant $tenant, string $date): array
    {
        $zone = TenantTime::zone($tenant);
        $locale = app()->getLocale();
        $gates = Gate::with('event:id,title,title_ar')->orderBy('sort_order')->orderBy('id')->get();
        $devices = GateDevice::where('status', GateDevice::STATUS_ACTIVE)->get(['id', 'gate_id', 'last_seen_at', 'status', 'tenant_id']);

        $perGate = $this->scansOn($tenant, $date)->toBase()
            ->selectRaw('gate_id, '.$this->sumIn().' as i, '.$this->sumOut().' as o, '.$this->sumRejects().' as r, max(scanned_at) as last_at')
            ->groupBy('gate_id')->get()->keyBy(fn ($r) => (string) ($r->gate_id ?? 'none'));

        $gateRows = $gates->map(function (Gate $g) use ($perGate, $devices, $tenant, $locale) {
            $r = $perGate->get((string) $g->id);
            $gd = $devices->where('gate_id', $g->id);

            return [
                'id' => $g->id,
                'code' => $g->code,
                'name' => $g->localizedName($locale),
                'direction' => $g->direction,
                'active' => (bool) $g->active,
                'allow_reentry' => (bool) $g->allow_reentry,
                'event' => $g->event ? ($locale === 'ar' ? ($g->event->title_ar ?: $g->event->title) : $g->event->title) : null,
                'in' => (int) ($r->i ?? 0), 'out' => (int) ($r->o ?? 0), 'rejects' => (int) ($r->r ?? 0),
                'devices' => $gd->count(),
                'devices_online' => $gd->filter(fn (GateDevice $d) => $d->connectivity() === 'online')->count(),
                'last_scan_at' => $r?->last_at ? TenantTime::iso(Carbon::parse($r->last_at, 'UTC'), $tenant) : null,
            ];
        })->values();

        if ($legacy = $perGate->get('none')) {
            $gateRows->push([
                'id' => null, 'code' => '—', 'name' => __('تطبيق الموظفين (بلا بوابة)'), 'direction' => 'entry', 'active' => true,
                'allow_reentry' => false, 'event' => null,
                'in' => (int) $legacy->i, 'out' => (int) $legacy->o, 'rejects' => (int) $legacy->r,
                'devices' => 0, 'devices_online' => 0,
                'last_scan_at' => $legacy->last_at ? TenantTime::iso(Carbon::parse($legacy->last_at, 'UTC'), $tenant) : null,
            ]);
        }

        $reasons = $this->scansOn($tenant, $date)->whereIn('result', ScanResult::REJECTS)->toBase()
            ->selectRaw('result, count(*) as c')->groupBy('result')->orderByDesc('c')->get()
            ->map(fn ($r) => ['result' => $r->result, 'label' => GateLabels::result($r->result), 'count' => (int) $r->c])->values();

        $conflicts = $this->scansOn($tenant, $date)->where('conflict', true)
            ->with(['gate', 'device:id,name', 'ticket:id,ticket_number,used_at,validation_location'])
            ->orderByDesc('scanned_at')->limit(25)->get()
            ->map(function (GateScan $s) use ($tenant, $locale) {
                $first = $s->ticket_id ? GateScan::with('gate')->where('ticket_id', $s->ticket_id)->where('id', '!=', $s->id)
                    ->where('direction', 'in')->whereIn('result', ScanResult::ADMITTED)->orderBy('scanned_at')->first() : null;

                return [
                    'id' => $s->id,
                    'ticket_number' => $s->ticket?->ticket_number,
                    'result' => $s->result,
                    'gate' => $s->gate?->localizedName($locale),
                    'device' => $s->device?->name,
                    'scanned_at' => TenantTime::iso($s->scanned_at, $tenant),
                    'first_gate' => $first?->gate?->localizedName($locale) ?? $s->ticket?->validation_location,
                    'first_at' => TenantTime::iso($first?->scanned_at ?? $s->ticket?->used_at, $tenant),
                ];
            })->values();

        $recent = $this->scansOn($tenant, $date)->with(['gate', 'device:id,name', 'ticket:id,ticket_number,ticket_type'])
            ->orderByDesc('scanned_at')->orderByDesc('id')->limit(15)->get()
            ->map(fn (GateScan $s) => $this->scanRow($s, $tenant) + [
                'gate' => $s->gate?->localizedName($locale),
                'device' => $s->device?->name,
                'label' => GateLabels::result($s->result, $s->direction),
            ])->values();

        $online = $devices->filter(fn (GateDevice $d) => $d->connectivity() === 'online')->count();

        return [
            'date' => $date,
            'is_today' => $date === GateDay::today($tenant),
            'generated_at' => TenantTime::iso(Carbon::now(), $tenant),
            'totals' => [
                'in' => $gateRows->sum('in'),
                'out' => $gateRows->sum('out'),
                'rejects' => $gateRows->sum('rejects'),
                'inside' => max(0, $gateRows->sum('in') - $gateRows->sum('out')),
                'conflicts' => $this->scansOn($tenant, $date)->where('conflict', true)->count(),
                'devices_online' => $online,
                'devices_total' => $devices->count(),
            ],
            'gates' => $gateRows->all(),
            'series' => $this->series($tenant, $date, $gateRows, $zone),
            'reasons' => $reasons->all(),
            'conflicts' => $conflicts->all(),
            'recent' => $recent->all(),
        ];
    }

    /** Entries per 5 minutes per gate (+ rejects) between the first and the last scan of the day. */
    private function series(Tenant $tenant, string $date, $gateRows, string $zone): array
    {
        [$from] = GateDay::window($date, $tenant);
        $bucket = $this->bucketExpr(5);
        $rows = $this->scansOn($tenant, $date)->toBase()
            ->selectRaw("gate_id, $bucket as b, ".$this->sumIn().' as i, '.$this->sumRejects().' as r', [$from->format('Y-m-d H:i:s')])
            ->groupBy('gate_id', 'b')->get();

        $isToday = $date === GateDay::today($tenant);
        $nowBucket = $isToday ? intdiv(max(0, (int) $from->diffInSeconds(Carbon::now(), false)), 300) : null;
        $min = $rows->min('b');
        $max = $rows->max('b');
        if ($min === null) {
            $max = $nowBucket ?? 0;
            $min = max(0, $max - 24);
        } else {
            $max = max((int) $max, (int) ($nowBucket ?? $max));
            $min = (int) $min;
            if ($max - $min < 24) {
                $min = max(0, $max - 24);
            }
        }

        $labels = [];
        for ($b = $min; $b <= $max; $b++) {
            $labels[] = $from->copy()->addMinutes($b * 5)->setTimezone($zone)->format('H:i');
        }

        $datasets = [];
        foreach ($gateRows as $g) {
            $data = array_fill(0, count($labels), 0);
            foreach ($rows->filter(fn ($r) => (string) $r->gate_id === (string) ($g['id'] ?? '')) as $r) {
                $data[(int) $r->b - $min] = (int) $r->i;
            }
            if (array_sum($data) > 0 || $g['id'] !== null) {
                $datasets[] = ['gate_id' => $g['id'], 'label' => trim(($g['code'] !== '—' ? $g['code'].' · ' : '').$g['name']), 'data' => $data];
            }
        }
        $rejects = array_fill(0, count($labels), 0);
        foreach ($rows as $r) {
            $rejects[(int) $r->b - $min] += (int) $r->r;
        }

        return ['labels' => $labels, 'bucket_minutes' => 5, 'datasets' => $datasets, 'rejects' => $rejects];
    }

    /** @return array<int, array{in: int, out: int, rejects: int}> keyed by bucket index (non-empty only) */
    private function buckets(Builder $query, Tenant $tenant, string $date, int $minutes): array
    {
        [$from] = GateDay::window($date, $tenant);
        $rows = $query->toBase()
            ->selectRaw($this->bucketExpr($minutes).' as b, '.$this->sumIn().' as i, '.$this->sumOut().' as o, '.$this->sumRejects().' as r', [$from->format('Y-m-d H:i:s')])
            ->groupBy('b')->orderBy('b')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->b] = ['in' => (int) $r->i, 'out' => (int) $r->o, 'rejects' => (int) $r->r];
        }

        return $out;
    }

    private function scanRow(GateScan $s, Tenant $tenant): array
    {
        return [
            'id' => $s->id,
            'result' => $s->result,
            'direction' => $s->direction,
            'source' => $s->source,
            'offline' => (bool) $s->offline,
            'conflict' => (bool) $s->conflict,
            'ticket_number' => $s->ticket?->ticket_number,
            'ticket_type' => $s->ticket?->ticket_type,
            'scanned_at' => TenantTime::iso($s->scanned_at, $tenant),
        ];
    }

    private function scoped(Builder $q, ?Gate $gate, ?GateDevice $device): Builder
    {
        if ($gate) {
            return $q->where('gate_id', $gate->id);
        }

        return $device ? $q->where('device_id', $device->id) : $q;
    }

    private function sumIn(): string
    {
        return "coalesce(sum(case when direction = 'in' and result in ('valid','reentry_ok') then 1 else 0 end), 0)";
    }

    private function sumOut(): string
    {
        return "coalesce(sum(case when direction = 'out' and result = 'valid' then 1 else 0 end), 0)";
    }

    private function sumRejects(): string
    {
        return "coalesce(sum(case when result not in ('valid','reentry_ok') then 1 else 0 end), 0)";
    }

    /** Bucket index of scanned_at from a bound window start (UTC), per driver. */
    private function bucketExpr(int $minutes): string
    {
        $seconds = $minutes * 60;

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(CAST(((julianday(scanned_at) - julianday(?)) * 86400 + 0.5) AS INTEGER) / $seconds)",
            'pgsql' => "FLOOR(EXTRACT(EPOCH FROM (scanned_at - CAST(? AS timestamp))) / $seconds)",
            default => "FLOOR(TIMESTAMPDIFF(SECOND, ?, scanned_at) / $seconds)",
        };
    }
}
