<?php

namespace App\Services\Gate;

use App\Models\Gate;
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
     * booking date for an event running that day. Optionally limited to the
     * gate's event.
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

    /** Cheap version string: changes whenever a ticket in scope is added / used / cancelled. */
    public function version(Tenant $tenant, string $date, ?Gate $gate = null): string
    {
        $agg = $this->ticketsFor($date, $gate)
            ->toBase()
            ->selectRaw('count(*) as c, max(tickets.updated_at) as u, coalesce(sum(tickets.check_in_count), 0) as k, sum(case when tickets.status = ? then 1 else 0 end) as a', ['active'])
            ->first();

        return substr(sha1(implode('|', [
            'v1', $tenant->id, $date, $gate?->id, $gate?->event_id,
            $agg->c ?? 0, $agg->u ?? '', $agg->k ?? 0, $agg->a ?? 0, $this->salt($tenant, $date),
        ])), 0, 16);
    }

    /**
     * @return array{version: string, generated_at: string, date: string, salt: string, gate_id: ?int, count: int, tickets: list<array{h: string, s: string, t: ?string, e: int, d: string}>}
     */
    public function build(Tenant $tenant, string $date, ?Gate $gate = null): array
    {
        $salt = $this->salt($tenant, $date);
        $tickets = [];

        $this->ticketsFor($date, $gate)
            ->toBase()
            ->select(['tickets.id', 'tickets.qr_code', 'tickets.status', 'tickets.ticket_type', 'tickets.event_id', 'tickets.booking_date', 'tickets.check_in_count', 'tickets.max_check_ins'])
            ->orderBy('tickets.id')
            ->chunk(2000, function ($rows) use (&$tickets, $salt, $date) {
                foreach ($rows as $row) {
                    if (! $row->qr_code) {
                        continue;
                    }
                    $status = match (true) {
                        $row->status === 'expired', $row->status === 'cancelled' => 'cancelled',
                        $row->status === 'used', (int) $row->check_in_count >= max(1, (int) $row->max_check_ins) => 'used',
                        default => 'active',
                    };
                    $tickets[] = [
                        'h' => self::hash($salt, $row->qr_code),
                        's' => $status,
                        't' => $row->ticket_type,
                        'e' => (int) $row->event_id,
                        'd' => $row->booking_date ? substr((string) $row->booking_date, 0, 10) : $date,
                    ];
                }
            });

        $this->markRefunds($tickets, $date, $gate, $salt);

        return [
            'version' => $this->version($tenant, $date, $gate),
            'generated_at' => TenantTime::iso(Carbon::now(), $tenant),
            'date' => $date,
            'salt' => $salt,
            'gate_id' => $gate?->id,
            'count' => count($tickets),
            'tickets' => $tickets,
        ];
    }

    /** Cancelled tickets of refunded payments are sent as `refunded`. */
    private function markRefunds(array &$tickets, string $date, ?Gate $gate, string $salt): void
    {
        if (! array_filter($tickets, fn ($t) => $t['s'] === 'cancelled')) {
            return;
        }
        $refunded = $this->ticketsFor($date, $gate)
            ->where('tickets.status', 'cancelled')
            ->whereHas('payment', fn (Builder $p) => $p->where('status', 'refunded'))
            ->pluck('qr_code')->all();
        if (! $refunded) {
            return;
        }
        $set = array_flip(array_map(fn ($c) => self::hash($salt, $c), $refunded));
        foreach ($tickets as &$t) {
            if (isset($set[$t['h']])) {
                $t['s'] = 'refunded';
            }
        }
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
