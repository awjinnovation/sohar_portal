<?php

namespace App\Services\Gate;

use App\Models\EventTicketType;
use App\Models\Gate;
use App\Models\GateScan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offline manifest (contract §B2 `GET /gate/manifest`).
 *
 * The device never receives ticket codes: each entry carries
 * `h = sha256(salt . qr_code)` where `salt` is a secret derived per tenant
 * and operating day (HMAC with APP_KEY). The same salted hash is stored in
 * gate_scans.code_hash for every scan (online or offline), so the server
 * can resolve synced offline scans and group repeated invalid codes.
 */
class GateManifestService
{
    /** Ticket statuses exposed in the manifest (`expired` is sent as `cancelled`). */
    public const STATUSES = ['active', 'used', 'cancelled', 'expired'];

    /** @var array<string, array<string, int>> memoized hash → ticket id maps */
    private array $maps = [];

    public function salt(Tenant $tenant, string $date): string
    {
        return substr(hash_hmac('sha256', "gate-manifest|{$tenant->id}|{$date}", (string) config('app.key')), 0, 32);
    }

    public static function hash(string $salt, string $code): string
    {
        return hash('sha256', $salt.$code);
    }

    public function hashFor(Tenant $tenant, string $date, string $code): string
    {
        return self::hash($this->salt($tenant, $date), $code);
    }

    /**
     * Tickets valid on an operating day: booked for that date, or without a
     * booking date (any_day / all_days, docs/ticket_types_contract.md §7) for
     * an event running that day. Optionally limited to the gate's event.
     */
    public function ticketsFor(string $date, ?Gate $gate = null): Builder
    {
        return Ticket::query()
            ->whereIn('tickets.status', self::STATUSES)
            ->when($gate?->event_id, fn (Builder $q) => $q->where('tickets.event_id', $gate->event_id))
            ->where(function (Builder $q) use ($date) {
                $q->whereDate('tickets.booking_date', $date)
                    ->orWhere(fn (Builder $w) => $w->whereNull('tickets.booking_date')
                        ->whereHas('event', fn (Builder $e) => $e->whereDate('start_time', '<=', $date)->whereDate('end_time', '>=', $date)));
            });
    }

    /**
     * Cheap version string: changes whenever a ticket in scope is added /
     * used / cancelled, or one of their ticket types changes (name, colour…).
     */
    public function version(Tenant $tenant, string $date, ?Gate $gate = null): string
    {
        $agg = $this->ticketsFor($date, $gate)
            ->toBase()
            ->selectRaw('count(*) as c, max(tickets.updated_at) as u, coalesce(sum(tickets.check_in_count), 0) as k, sum(case when tickets.status = ? then 1 else 0 end) as a', ['active'])
            ->first();
        $types = $this->typesQuery($date, $gate)->toBase()
            ->selectRaw('count(*) as c, max(event_ticket_types.updated_at) as u')
            ->first();

        return substr(sha1(implode('|', [
            'v1', $tenant->id, $date, $gate?->id, $gate?->event_id,
            $agg->c ?? 0, $agg->u ?? '', $agg->k ?? 0, $agg->a ?? 0, $this->salt($tenant, $date),
            $types->c ?? 0, $types->u ?? '',
        ])), 0, 16);
    }

    /** The ticket types of the tickets in scope (manifest `types`). */
    private function typesQuery(string $date, ?Gate $gate): Builder
    {
        return EventTicketType::query()
            ->whereIn('event_ticket_types.id', $this->ticketsFor($date, $gate)->whereNotNull('tickets.ticket_type_id')->select('tickets.ticket_type_id'));
    }

    /**
     * Manifest `types` (v1.2): what an offline device needs to show the
     * same type badge, colour and ID note as an online scan, and to count
     * entries per type. Entries point at them with `y`.
     *
     * @return list<array{id: int, key: string, event_id: int, name: array{en: string, ar: string}, category: string, color: string, admits: int, requires_id: bool, min_age: ?int, max_age: ?int, validity: string}>
     */
    private function types(string $date, ?Gate $gate): array
    {
        return $this->typesQuery($date, $gate)->orderBy('event_ticket_types.event_id')->orderBy('event_ticket_types.sort')->orderBy('event_ticket_types.id')->get()
            ->map(fn (EventTicketType $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'event_id' => (int) $t->event_id,
                'name' => ['en' => (string) ($t->name['en'] ?? '') ?: $t->key, 'ar' => (string) ($t->name['ar'] ?? '') ?: $t->key],
                'category' => $t->category,
                'color' => $t->colorValue(),
                'admits' => max(1, (int) $t->admits),
                'requires_id' => (bool) $t->requires_id,
                'min_age' => $t->min_age,
                'max_age' => $t->max_age,
                'validity' => $t->validityValue(),
            ])->values()->all();
    }

    /** Columns an entry is built from. */
    private const ENTRY_COLUMNS = ['tickets.id', 'tickets.qr_code', 'tickets.status', 'tickets.ticket_type', 'tickets.ticket_type_id', 'tickets.validity', 'tickets.event_id', 'tickets.booking_date', 'tickets.check_in_count', 'tickets.max_check_ins', 'tickets.used_at'];

    /** Status codes of the v2 status list (docs/gate_signed_tickets.md §3). */
    public const STATUS_CODES = ['active' => 0, 'used' => 1, 'cancelled' => 2, 'refunded' => 3];

    /**
     * Entries `u` are over the ticket's life, except for season passes
     * (all_days): today's admitted entries, so they are `active` again every
     * day. Undated tickets add `v` (any_day | all_days).
     *
     * Entries add `y` = the ticket type id (absent for tickets without one),
     * described once in the top-level `types`.
     *
     * Only tickets that can still enter are listed (docs/gate_signed_tickets.md
     * §3): used tickets are left out, except those used today at a gate that
     * allows re-entry or lets visitors out (direction exit / both). v1 keeps cancelled / refunded entries (their offline
     * message); v2 (`$v >= 2`) moves them, and the used ones, into `status`
     * (2 bits per ticket id), adds `i` (ticket id) to every entry and a
     * `cursor` for GET /gate/manifest/changes.
     *
     * @return array{version: string, generated_at: string, date: string, salt: string, gate_id: ?int, count: int, types: list<array>, tickets: list<array{h: string, s: string, t: ?string, y?: int, e: int, d: string, a: int, u: int, v?: string, i?: int}>, status?: array{from: int, count: int, bits: string}, cursor?: string}
     */
    public function build(Tenant $tenant, string $date, ?Gate $gate = null, int $v = 1): array
    {
        $now = Carbon::now();
        $salt = $this->salt($tenant, $date);
        $today = $this->entriesToday($tenant, $date, $gate);
        $refunded = $this->refundedIds($date, $gate);
        // Re-entry and exit gates keep tickets used today (a re-entry after an exit, a visitor leaving).
        $usedToday = $gate && ($gate->allow_reentry || $gate->direction !== 'entry') ? GateDay::window($date, $tenant) : null;
        $tickets = [];
        $codes = [];

        $this->ticketsFor($date, $gate)
            ->toBase()
            ->select(self::ENTRY_COLUMNS)
            ->orderBy('tickets.id')
            ->chunk(2000, function ($rows) use (&$tickets, &$codes, $salt, $date, $today, $refunded, $usedToday, $v) {
                foreach ($rows as $row) {
                    if (! $row->qr_code) {
                        continue;
                    }
                    $entry = $this->entry($row, $salt, $date, $today, $refunded);
                    if ($v >= 2) {
                        $codes[(int) $row->id] = self::STATUS_CODES[$entry['s']];
                    }
                    if ($this->listed($entry, $row, $usedToday, $v)) {
                        if ($v < 2) {
                            unset($entry['i']);
                        }
                        $tickets[] = $entry;
                    }
                }
            });

        $out = [
            'version' => $this->version($tenant, $date, $gate),
            'generated_at' => TenantTime::iso($now, $tenant),
            'date' => $date,
            'salt' => $salt,
            'gate_id' => $gate?->id,
            'count' => count($tickets),
            'types' => $this->types($date, $gate),
            'tickets' => $tickets,
        ];
        if ($v >= 2) {
            $out['status'] = self::statusBits($codes);
            $out['cursor'] = TenantTime::iso($now, $tenant);
        }

        return $out;
    }

    /**
     * GET /gate/manifest/changes (docs/gate_signed_tickets.md §4): every ticket
     * in scope changed since `since` (15 s overlap), any status, as v2 entries.
     * `full: true` alone when the device must download the whole list again.
     *
     * @return array{full: bool, cursor?: string, version?: string, types?: list<array>, tickets?: list<array>}
     */
    public function changes(Tenant $tenant, string $date, ?Gate $gate, ?string $since): array
    {
        $now = Carbon::now();
        try {
            $from = $since ? Carbon::parse($since) : null;
        } catch (\Throwable) {
            $from = null;
        }
        if (! $from || $from->lt($now->copy()->subHours(self::CHANGES_MAX_AGE_HOURS)) || $from->gt($now->copy()->addMinutes(5))
            || $date !== GateDay::today($tenant)) {
            return ['full' => true];
        }

        $salt = $this->salt($tenant, $date);
        $today = $this->entriesToday($tenant, $date, $gate);
        $changed = $this->ticketsFor($date, $gate)
            ->where('tickets.updated_at', '>=', $from->copy()->subSeconds(self::CHANGES_OVERLAP_SECONDS)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'))
            ->toBase()
            ->select(self::ENTRY_COLUMNS)
            ->orderBy('tickets.id')
            ->limit(self::CHANGES_LIMIT + 1)
            ->get();
        if ($changed->count() > self::CHANGES_LIMIT) {
            return ['full' => true];
        }
        $cancelled = $changed->where('status', 'cancelled')->pluck('id')->all();
        $refunded = $cancelled ? $this->refundedIds($date, $gate, $cancelled) : [];

        return [
            'full' => false,
            'cursor' => TenantTime::iso($now, $tenant),
            'version' => $this->version($tenant, $date, $gate),
            'types' => $this->types($date, $gate),
            'tickets' => $changed->filter(fn ($row) => (bool) $row->qr_code)
                ->map(fn ($row) => $this->entry($row, $salt, $date, $today, $refunded))->values()->all(),
        ];
    }

    /** Re-download the whole list when the device's cursor is older than this. */
    public const CHANGES_MAX_AGE_HOURS = 6;

    /** Changes are sent from this long before the cursor (two changes in one second, slow commits). */
    public const CHANGES_OVERLAP_SECONDS = 15;

    /** More changed tickets than this: the full list is cheaper. */
    public const CHANGES_LIMIT = 5000;

    /**
     * One manifest entry. `i` (ticket id) is always set; v1 drops it.
     *
     * @param  array<int, true>  $refunded  ticket ids of refunded payments
     */
    private function entry(object $row, string $salt, string $date, array $today, array $refunded): array
    {
        $validity = EventTicketType::normalizeValidity($row->validity);
        $pass = $validity === EventTicketType::VALIDITY_ALL_DAYS;
        $used = $pass ? (int) ($today[$row->id] ?? 0) : (int) $row->check_in_count;
        $status = match (true) {
            $row->status === 'cancelled' && isset($refunded[(int) $row->id]) => 'refunded',
            $row->status === 'expired', $row->status === 'cancelled' => 'cancelled',
            ! $pass && $row->status === 'used', $used >= max(1, (int) $row->max_check_ins) => 'used',
            default => 'active',
        };

        return [
            'h' => self::hash($salt, $row->qr_code),
            's' => $status,
            't' => $row->ticket_type,
        ] + ($row->ticket_type_id ? ['y' => (int) $row->ticket_type_id] : []) + [
            'e' => (int) $row->event_id,
            'd' => $row->booking_date ? substr((string) $row->booking_date, 0, 10) : $date,
            // Multi-entry (docs/ticket_types_contract.md): people admitted / entries used (today, for a season pass).
            'a' => max(1, (int) $row->max_check_ins),
            'u' => $used,
        ] + ($validity === EventTicketType::VALIDITY_DATE ? [] : ['v' => $validity]) + ['i' => (int) $row->id];
    }

    /**
     * Whether an entry goes into `tickets`: active ones; used ones only when
     * used today at a re-entry or exit gate; cancelled / refunded only in v1.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $usedToday  re-entry / exit gate: today's UTC window
     */
    private function listed(array $entry, object $row, ?array $usedToday, int $v): bool
    {
        return match ($entry['s']) {
            'active' => true,
            'used' => $usedToday !== null && (($entry['v'] ?? null) === EventTicketType::VALIDITY_ALL_DAYS
                || ($row->used_at && Carbon::parse($row->used_at, config('app.timezone'))->betweenIncluded($usedToday[0], $usedToday[1]))),
            default => $v < 2,
        };
    }

    /**
     * 2 bits per ticket id from the lowest id: byte k >> 2, bits (k & 3) * 2,
     * least significant first (docs/gate_signed_tickets.md §3).
     *
     * @param  array<int, int>  $codes  ticket id => status code
     * @return array{from: int, count: int, bits: string}
     */
    public static function statusBits(array $codes): array
    {
        if (! $codes) {
            return ['from' => 0, 'count' => 0, 'bits' => ''];
        }
        $from = min(array_keys($codes));
        $count = max(array_keys($codes)) - $from + 1;
        $bytes = array_fill(0, intdiv($count + 3, 4), 0);
        foreach ($codes as $id => $code) {
            if ($code) {
                $k = $id - $from;
                $bytes[$k >> 2] |= ($code & 3) << (($k & 3) * 2);
            }
        }

        return ['from' => $from, 'count' => $count, 'bits' => base64_encode(pack('C*', ...$bytes))];
    }

    /** @return array<int, int> ticket id → admitted entries on that gate day, for the season passes in scope */
    private function entriesToday(Tenant $tenant, string $date, ?Gate $gate): array
    {
        [$from, $to] = GateDay::window($date, $tenant);

        return GateScan::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('ticket_id', $this->ticketsFor($date, $gate)->where('tickets.validity', EventTicketType::VALIDITY_ALL_DAYS)->select('tickets.id'))
            ->where('direction', 'in')->where('result', 'valid')
            ->where('scanned_at', '>=', $from)->where('scanned_at', '<', $to)
            ->groupBy('ticket_id')
            ->selectRaw('ticket_id, count(*) as n')
            ->pluck('n', 'ticket_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Ids of cancelled tickets whose payment was refunded (sent as `refunded`).
     *
     * @param  list<int>|null  $among  only these ticket ids
     * @return array<int, true>
     */
    private function refundedIds(string $date, ?Gate $gate, ?array $among = null): array
    {
        return array_fill_keys($this->ticketsFor($date, $gate)
            ->where('tickets.status', 'cancelled')
            ->when($among !== null, fn (Builder $q) => $q->whereIn('tickets.id', $among))
            ->whereHas('payment', fn (Builder $p) => $p->where('status', 'refunded'))
            ->pluck('tickets.id')->map(fn ($id) => (int) $id)->all(), true);
    }

    /** Forget memoized hash maps (call once per sync batch: tickets change between requests). */
    public function resetCache(): void
    {
        $this->maps = [];
    }

    /**
     * Resolve a salted hash (from an offline scan) to a ticket id, looking
     * at the scan's operating day and the days around it (clock skew,
     * manifests fetched just before the rollover).
     */
    public function resolveHash(Tenant $tenant, string $hash, string $date): ?int
    {
        $hash = strtolower($hash);
        foreach ([$date, Carbon::parse($date)->subDay()->toDateString(), Carbon::parse($date)->addDay()->toDateString()] as $day) {
            $map = $this->maps["{$tenant->id}|$day"] ??= $this->hashMap($tenant, $day);
            if (isset($map[$hash])) {
                return $map[$hash];
            }
        }

        return null;
    }

    /** @return array<string, int> salted hash → ticket id for every ticket valid that day */
    private function hashMap(Tenant $tenant, string $date): array
    {
        $salt = $this->salt($tenant, $date);
        $map = [];
        $this->ticketsFor($date)->toBase()->select(['tickets.id', 'tickets.qr_code'])->orderBy('tickets.id')
            ->chunk(5000, function ($rows) use (&$map, $salt) {
                foreach ($rows as $row) {
                    if ($row->qr_code) {
                        $map[self::hash($salt, $row->qr_code)] = (int) $row->id;
                    }
                }
            });

        return $map;
    }
}
