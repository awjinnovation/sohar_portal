<?php

namespace App\Services\Gate;

use App\Models\EventTicketType;
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
 *
 * `by_type` (v1.2) splits the same figures per ticket type (people: a
 * family ticket admitting 4 gives 4 entries), each type with its colour.
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
            'by_type' => $this->byType($base()),
            'recent' => $recent,
        ];
    }

    /**
     * In / out / rejects per ticket type over [$scans] (scans without a
     * ticket, e.g. unknown codes, are left out). Types are the tickets'
     * own; a ticket without one is grouped under its legacy key.
     *
     * @return list<array{type_id: ?int, key: string, name: array{en: string, ar: string}, name_text: string, category: string, color: string, in: int, out: int, rejects: int, event?: ?string}>
     */
    public function byType(Builder $scans, bool $withEvent = false): array
    {
        $rows = $this->typeRows($scans, ['tickets.event_id']);
        if ($rows->isEmpty()) {
            return [];
        }
        $locale = app()->getLocale();
        $types = EventTicketType::with('event:id,title,title_ar')
            ->whereIn('id', $rows->pluck('type_id')->filter()->unique()->values())
            ->get()->keyBy('id');
        $events = $withEvent
            ? \App\Models\Event::whereIn('id', $rows->whereNull('type_id')->pluck('event_id')->filter()->unique()->values())->get(['id', 'title', 'title_ar'])->keyBy('id')
            : collect();
        $title = fn ($e) => $e ? ($locale === 'ar' ? ($e->title_ar ?: $e->title) : $e->title) : null;

        $out = [];
        foreach ($rows as $r) {
            $type = $r->type_id ? $types->get((int) $r->type_id) : null;
            $key = $type ? 'id:'.$type->id : 'key:'.$r->type_key.($withEvent ? '|'.$r->event_id : '');
            $out[$key] ??= ($type ? [
                'type_id' => $type->id,
                'key' => $type->key,
                'name' => ['en' => (string) ($type->name['en'] ?? '') ?: $type->key, 'ar' => (string) ($type->name['ar'] ?? '') ?: $type->key],
                'name_text' => $type->nameText($locale),
                'category' => $type->category,
                'color' => $type->colorValue(),
            ] : [
                'type_id' => null,
                'key' => (string) $r->type_key,
                'name' => ['en' => (string) $r->type_key, 'ar' => (string) $r->type_key],
                'name_text' => (string) $r->type_key,
                'category' => 'general',
                'color' => EventTicketType::CATEGORY_COLORS['general'],
            ]) + ['in' => 0, 'out' => 0, 'rejects' => 0] + ($withEvent ? ['event' => $title($type ? $type->event : $events->get((int) $r->event_id))] : []);
            $out[$key]['in'] += (int) $r->i;
            $out[$key]['out'] += (int) $r->o;
            $out[$key]['rejects'] += (int) $r->r;
        }

        return collect($out)->sort(fn ($a, $b) => [$b['in'], $a['name_text']] <=> [$a['in'], $b['name_text']])->values()->all();
    }

    /** Scans joined to their tickets, grouped by ticket type (+ [$extra] columns). */
    private function typeRows(Builder $scans, array $extra = [])
    {
        $cols = array_merge(['tickets.ticket_type_id', 'tickets.ticket_type'], $extra);
        $select = 'tickets.ticket_type_id as type_id, tickets.ticket_type as type_key';
        foreach ($extra as $c) {
            $select .= ", $c as ".str_replace(['tickets.', 'gate_scans.'], '', $c);
        }

        return $scans->toBase()
            ->join('tickets', 'tickets.id', '=', 'gate_scans.ticket_id')
            ->selectRaw($select.', '.$this->sumIn('gate_scans.').' as i, '.$this->sumOut('gate_scans.').' as o, '.$this->sumRejects('gate_scans.').' as r')
            ->groupBy($cols)
            ->get();
    }

    /** @return array<string, list<array{type_id: ?int, key: string, in: int}>> gate id ('none' = no gate) → entries per type */
    private function gateTypes(Tenant $tenant, string $date): array
    {
        $out = [];
        foreach ($this->typeRows($this->scansOn($tenant, $date), ['gate_scans.gate_id']) as $r) {
            if ((int) $r->i <= 0) {
                continue;
            }
            $gate = (string) ($r->gate_id ?? 'none');
            $key = $r->type_id ? 'id:'.$r->type_id : 'key:'.$r->type_key;
            $out[$gate][$key] ??= ['type_id' => $r->type_id ? (int) $r->type_id : null, 'key' => (string) $r->type_key, 'in' => 0];
            $out[$gate][$key]['in'] += (int) $r->i;
        }

        return array_map(fn ($rows) => collect($rows)->sortByDesc('in')->values()->all(), $out);
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
        $gateTypes = $this->gateTypes($tenant, $date);

        $gateRows = $gates->map(function (Gate $g) use ($perGate, $gateTypes, $devices, $tenant, $locale) {
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
                'types' => $gateTypes[(string) $g->id] ?? [],
            ];
        })->values();

        if ($legacy = $perGate->get('none')) {
            $gateRows->push([
                'id' => null, 'code' => '—', 'name' => __('تطبيق الموظفين (بلا بوابة)'), 'direction' => 'entry', 'active' => true,
                'allow_reentry' => false, 'event' => null,
                'in' => (int) $legacy->i, 'out' => (int) $legacy->o, 'rejects' => (int) $legacy->r,
                'devices' => 0, 'devices_online' => 0,
                'last_scan_at' => $legacy->last_at ? TenantTime::iso(Carbon::parse($legacy->last_at, 'UTC'), $tenant) : null,
                'types' => $gateTypes['none'] ?? [],
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
            'by_type' => $this->byType($this->scansOn($tenant, $date), true),
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

    /** [$p] = column prefix (e.g. `gate_scans.`) for joined queries. */
    private function sumIn(string $p = ''): string
    {
        return "coalesce(sum(case when {$p}direction = 'in' and {$p}result in ('valid','reentry_ok') then 1 else 0 end), 0)";
    }

    private function sumOut(string $p = ''): string
    {
        return "coalesce(sum(case when {$p}direction = 'out' and {$p}result = 'valid' then 1 else 0 end), 0)";
    }

    private function sumRejects(string $p = ''): string
    {
        return "coalesce(sum(case when {$p}result not in ('valid','reentry_ok') then 1 else 0 end), 0)";
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
