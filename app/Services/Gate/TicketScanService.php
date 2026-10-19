<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Models\Gate;
use App\Models\GateScan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Wallet\PointsService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single source of truth for ticket validation at the gate (contract
 * §B1): used by /gate/scan, /gate/sync and the legacy /tickets/validate +
 * /tickets/{id}/mark-used of the visitor app's staff screen.
 *
 * Order of checks: unknown code → invalid; exits are always let through;
 * cancelled / refunded; gate restricted to another event → wrong_event;
 * gate restricted to other ticket types → wrong_gate; booking date (or the
 * event's dates) ≠ operating day, expired ticket or ended event →
 * wrong_date; before the event start (minus `gate.early_entry_minutes`,
 * default 60, tenant-local time) → not_yet_open; no entries left →
 * reentry_ok (gate.allow_reentry; with `gate.reentry_requires_exit` the
 * last movement must be an exit) or already_used; otherwise valid.
 *
 * `valid` entries mark the ticket used inside a DB transaction holding a row
 * lock on the ticket, so the same ticket scanned at two gates at once gives
 * one `valid` and one `already_used`. Every scan is logged once per
 * idempotency key (a retried request replays the stored result).
 */
class TicketScanService
{
    /** Online device clocks within this many seconds of the server are trusted for scanned_at. */
    private const CLOCK_TOLERANCE = 600;

    public function __construct(
        private TenantContext $context,
        private GateManifestService $manifest,
        private PointsService $points,
    ) {}

    public function scan(ScanInput $in): ScanOutcome
    {
        $tenant = $this->context->get();
        $now = Carbon::now();
        [$evalAt, $scannedAt] = $this->times($in, $now);
        $direction = $in->gate
            ? $in->gate->resolveDirection($in->direction)
            : (in_array($in->direction, ['out', 'exit'], true) ? 'out' : 'in');

        if ($in->idempotencyKey && ($existing = $this->existing($in, $tenant))) {
            return $this->replay($existing);
        }

        $day = GateDay::dateFor($evalAt, $tenant);
        $ticket = $in->ticket ?? $this->findTicket($in, $tenant, $day);
        $hash = $in->codeHash
            ? strtolower($in->codeHash)
            : $this->manifest->hashFor($tenant, $day, (string) ($this->normalizeCode($in->code) ?? $ticket?->qr_code ?? ''));

        try {
            return DB::transaction(function () use ($in, $tenant, $ticket, $direction, $evalAt, $scannedAt, $now, $hash) {
                // Row lock: concurrent scans of one ticket are serialized here.
                $locked = $ticket && $in->commit
                    ? Ticket::withoutTenantScope()->whereKey($ticket->id)->lockForUpdate()->first()
                    : $ticket;

                $outcome = $this->evaluate($locked, $in->gate, $direction, $evalAt, $tenant);

                if ($in->resultLocal !== null) {
                    $this->reconcileOffline($outcome, $in);
                }

                if ($in->commit && $locked && $outcome->result === ScanResult::VALID && $direction === 'in') {
                    $outcome->firstCheckIn = $this->admit($locked, $in, $evalAt, $tenant);
                }

                $record = match ($in->record) {
                    ScanInput::RECORD_NONE => false,
                    ScanInput::RECORD_REJECTS => ! $outcome->admitted(),
                    default => true,
                };
                if ($record) {
                    $outcome->scan = GateScan::create([
                        'gate_id' => $in->gate?->id,
                        'device_id' => $in->device?->id,
                        'staff_user_id' => $in->staff?->id,
                        'ticket_id' => $locked?->id,
                        'code_hash' => $hash,
                        'result' => $outcome->result,
                        'direction' => $direction,
                        'source' => self::normalizeSource($in->source),
                        'scanned_at' => $scannedAt,
                        'received_at' => $now,
                        'offline' => $in->offline,
                        'idempotency_key' => $in->idempotencyKey ?: 'srv:'.Str::uuid(),
                        'conflict' => $outcome->conflict,
                    ]);
                }

                return $outcome;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race on the same idempotency key: everything above was rolled back.
            if ($in->idempotencyKey && ($existing = $this->existing($in, $tenant))) {
                return $this->replay($existing);
            }
            throw $e;
        }
    }

    /** Decide a result without side effects. */
    public function evaluate(?Ticket $ticket, ?Gate $gate, string $direction, Carbon $at, Tenant $tenant): ScanOutcome
    {
        $out = fn (string $result, ?string $detail = null, array $params = []) => new ScanOutcome($result, $direction, $ticket, $detail, $params);

        if (! $ticket) {
            return $out(ScanResult::INVALID);
        }
        // Leaving is never blocked; the exit is just counted.
        if ($direction === 'out') {
            return $out(ScanResult::VALID);
        }

        $ticket->loadMissing('event');
        $event = $ticket->event;
        $zone = TenantTime::zone($tenant);
        $local = $at->copy()->setTimezone($zone);
        $day = GateDay::dateFor($at, $tenant);

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            $refunded = $ticket->status === 'refunded' || $ticket->payment()->where('status', 'refunded')->exists();

            return $out($refunded ? ScanResult::REFUNDED : ScanResult::CANCELLED);
        }
        if ($gate?->event_id && (int) $ticket->event_id !== (int) $gate->event_id) {
            return $out(ScanResult::WRONG_EVENT);
        }
        if ($gate && ! $gate->acceptsTicketType($ticket->ticket_type)) {
            return $out(ScanResult::WRONG_GATE, 'gate_types', ['types' => implode(', ', (array) $gate->ticket_types)]);
        }
        if ($ticket->status === 'expired') {
            return $out(ScanResult::WRONG_DATE, 'expired');
        }

        // Valid date: the booking date, else the event's running days.
        if ($ticket->booking_date) {
            $valid = $ticket->booking_date->toDateString();
            if ($valid !== $day) {
                return $out(ScanResult::WRONG_DATE, 'valid_on', ['date' => $valid, 'when' => $valid < $day ? 'past' : 'future']);
            }
        } elseif ($event) {
            $first = $event->start_time?->toDateString();
            $last = $event->end_time?->toDateString();
            if ($first && $day < $first) {
                return $out(ScanResult::WRONG_DATE, 'valid_on', ['date' => $first, 'when' => 'future']);
            }
            if ($last && $day > $last) {
                return $out(ScanResult::WRONG_DATE, 'event_ended', ['when' => 'past']);
            }
        }
        if ($event?->end_time && $local->format('Y-m-d H:i:s') > $event->end_time->format('Y-m-d H:i:s')) {
            return $out(ScanResult::WRONG_DATE, 'event_ended', ['when' => 'past']);
        }
        if ($event?->start_time) {
            $opens = $event->start_time->copy()->subMinutes(max(0, (int) $tenant->setting('gate.early_entry_minutes', 60)));
            if ($local->format('Y-m-d H:i:s') < $opens->format('Y-m-d H:i:s')) {
                return $out(ScanResult::NOT_YET_OPEN, 'opens_at', ['time' => $opens->format('H:i'), 'date' => $opens->toDateString()]);
            }
        }

        $max = max(1, (int) ($ticket->max_check_ins ?: 1));
        if ($ticket->status === 'used' || (int) $ticket->check_in_count >= $max) {
            if ($gate?->allow_reentry && (int) $ticket->check_in_count > 0 && $this->mayReenter($ticket, $tenant)) {
                return $out(ScanResult::REENTRY_OK);
            }

            return $out(ScanResult::ALREADY_USED, ...$this->usedDetail($ticket, $zone));
        }

        return $out(ScanResult::VALID);
    }

    /**
     * Offline scans (sync): the device already decided. Rejections are
     * recorded as they happened; admissions the server would not have made
     * are flagged as conflicts (unless it is the same device re-admitting).
     */
    private function reconcileOffline(ScanOutcome $outcome, ScanInput $in): void
    {
        $local = in_array($in->resultLocal, ScanResult::ALL, true) ? $in->resultLocal : ScanResult::INVALID;

        if (! ScanResult::isAdmitted($local)) {
            $outcome->result = $local;
            $outcome->detail = null;

            return;
        }
        if ($outcome->admitted()) {
            return;
        }

        $outcome->conflict = true;
        if ($outcome->result === ScanResult::ALREADY_USED && $outcome->ticket && $in->device) {
            $first = $this->firstAdmission($outcome->ticket);
            if ($first && (int) $first->device_id === (int) $in->device->id) {
                $outcome->conflict = false;
            }
        }
    }

    /** Mark one entry on a locked ticket. Returns true on the ticket's first check-in. */
    private function admit(Ticket $ticket, ScanInput $in, Carbon $at, Tenant $tenant): bool
    {
        $first = (int) $ticket->check_in_count === 0;
        $ticket->check_in_count = (int) $ticket->check_in_count + 1;
        $ticket->validated_by = $in->validatedBy ?? $this->validatorLabel($in);
        $ticket->validation_location = $in->location ?? $in->gate?->localizedName('en');
        $ticket->validation_notes = $in->notes;
        if ($first || ! $ticket->used_at) {
            $ticket->used_at = $at->copy()->min(Carbon::now());
        }
        if ($ticket->check_in_count >= max(1, (int) ($ticket->max_check_ins ?: 1))) {
            $ticket->status = 'used';
        }
        $ticket->save();

        if ($first) {
            $this->awardCheckinPoints($ticket, $tenant);
        }

        return $first;
    }

    /** Optional tenant reward (contract §6a): points on the first gate validation. */
    private function awardCheckinPoints(Ticket $ticket, Tenant $tenant): void
    {
        $points = (int) $tenant->setting('points.checkin_points', 0);
        if (! $tenant->moduleEnabled('points') || ! $tenant->setting('points.earn_on_checkin') || $points <= 0 || ! $ticket->user) {
            return;
        }
        $this->points->award(
            $this->points->accountFor($ticket->user, $tenant), $tenant, $points,
            'platform.points.earn_checkin', $ticket, "checkin:ticket:{$ticket->id}"
        );
    }

    private function mayReenter(Ticket $ticket, Tenant $tenant): bool
    {
        if (! $tenant->setting('gate.reentry_requires_exit', false)) {
            return true;
        }
        $last = GateScan::where('ticket_id', $ticket->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('direction', 'in')->whereIn('result', ScanResult::ADMITTED))
                ->orWhere(fn ($w) => $w->where('direction', 'out')->where('result', ScanResult::VALID)))
            ->orderByDesc('scanned_at')->orderByDesc('id')
            ->first();

        return $last?->direction === 'out';
    }

    private function firstAdmission(Ticket $ticket): ?GateScan
    {
        return GateScan::with('gate')->where('ticket_id', $ticket->id)->where('direction', 'in')
            ->whereIn('result', ScanResult::ADMITTED)->orderBy('scanned_at')->orderBy('id')->first();
    }

    /** @return array{0: string, 1: array} */
    private function usedDetail(Ticket $ticket, string $zone): array
    {
        $time = $ticket->used_at?->copy()->setTimezone($zone)->format('H:i');
        if (! $time) {
            return [null, []];
        }
        $gate = $this->firstAdmission($ticket)?->gate;
        $gateName = $gate ? ['en' => $gate->localizedName('en'), 'ar' => $gate->localizedName('ar')] : $ticket->validation_location;

        return $gateName ? ['used_at', ['time' => $time, 'gate' => $gateName]] : ['used_at_no_gate', ['time' => $time]];
    }

    private function validatorLabel(ScanInput $in): string
    {
        if ($in->staff) {
            return sprintf('%s (#%d)', $in->staff->name, $in->staff->id);
        }

        return $in->device ? sprintf('Gate device: %s (#%d)', $in->device->name, $in->device->id) : 'unknown';
    }

    private function findTicket(ScanInput $in, Tenant $tenant, string $day): ?Ticket
    {
        if ($in->codeHash) {
            $id = $this->manifest->resolveHash($tenant, $in->codeHash, $day);

            return $id ? Ticket::find($id) : null;
        }
        $code = $this->normalizeCode($in->code);
        if ($code === null || $code === '') {
            return null;
        }

        $ticket = Ticket::where('qr_code', $code)->first();
        // Typed by staff from the visitor's screen: the ticket number is accepted.
        if (! $ticket && self::normalizeSource($in->source) === 'manual') {
            $ticket = Ticket::where('ticket_number', strtoupper($code))->first();
        }

        return $ticket;
    }

    /** Trim scanner noise; accept the public ticket page URL (…/ticket/{qr_code}). */
    private function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $code = trim($code);
        if (preg_match('#/ticket/([A-Za-z0-9\-]+)/?(?:\?.*)?$#', $code, $m)) {
            $code = $m[1];
        }

        return $code;
    }

    public static function normalizeSource(?string $source): string
    {
        return in_array($source, ['camera', 'hardware', 'wedge', 'manual'], true) ? $source : 'camera';
    }

    /** @return array{0: Carbon, 1: Carbon} [evaluation time, recorded scanned_at] */
    private function times(ScanInput $in, Carbon $now): array
    {
        $device = $in->scannedAt ? Carbon::instance($in->scannedAt)->utc() : null;

        if ($in->offline) {
            $at = $device ?? $now->copy();
            $at = $at->gt($now) ? $now->copy() : ($at->lt($now->copy()->subDays(7)) ? $now->copy()->subDays(7) : $at);

            return [$at, $at];
        }

        $recorded = $device && abs($device->diffInSeconds($now, false)) <= self::CLOCK_TOLERANCE ? $device : $now->copy();

        return [$now->copy(), $recorded];
    }

    private function existing(ScanInput $in, Tenant $tenant): ?GateScan
    {
        $existing = GateScan::withoutTenantScope()->with('ticket')->where('idempotency_key', $in->idempotencyKey)->first();
        if ($existing && ((int) $existing->tenant_id !== (int) $tenant->id
            || ($in->device && (int) $existing->device_id !== (int) $in->device->id))) {
            throw ApiException::idempotencyConflict();
        }

        return $existing;
    }

    private function replay(GateScan $scan): ScanOutcome
    {
        return new ScanOutcome(
            result: $scan->result,
            direction: $scan->direction,
            ticket: $scan->ticket,
            scan: $scan,
            conflict: (bool) $scan->conflict,
            replay: true,
        );
    }
}
