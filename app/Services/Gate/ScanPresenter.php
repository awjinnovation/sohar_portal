<?php

namespace App\Services\Gate;

use App\Models\GateDevice;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;

/**
 * API shape of one scan result (contract §B2 `POST /gate/scan`), shared by
 * /gate/scan and the box office admit endpoint (docs/gate_sales_contract.md §3).
 */
class ScanPresenter
{
    public function __construct(private TenantContext $context, private GateStatsService $stats) {}

    public function present(ScanOutcome $o, GateDevice $device, bool $withCounts = true): array
    {
        $tenant = $this->context->get();
        $t = $o->ticket;
        $locale = app()->getLocale();
        $event = $t?->event;
        // Today's entries for a season pass (all_days), else over the ticket's life.
        $used = $t ? $t->entriesUsed() : 0;

        return [
            'result' => $o->result,
            'admit' => $o->admitted(),
            'direction' => $o->direction,
            'message' => $o->message(),
            'ticket' => $t ? [
                'number' => $t->ticket_number,
                'holder_name' => $t->holder_name ?? $t->user?->name,
                'type' => $t->ticket_type,
                'event_id' => $t->event_id,
                'event_title' => $event ? ($locale === 'ar' ? ($event->title_ar ?: $event->title) : $event->title) : null,
                'valid_date' => $t->booking_date?->toDateString(),
                // docs/ticket_types_contract.md §7: validity, validity_text («صالحة في جميع أيام الفعالية»), valid_from, valid_until.
                ...$t->validityApi($locale),
                'used_at' => TenantTime::iso($t->used_at, $tenant),
                'quantity' => (int) ($t->quantity ?: 1),
                'quantity_label' => trans_choice('gate.people', (int) ($t->quantity ?: 1), ['count' => (int) ($t->quantity ?: 1)]),
                'check_in_count' => $used,
                'max_check_ins' => (int) ($t->max_check_ins ?: 1),
            ] : null,
            // docs/ticket_types_contract.md §2: type badge, ID/age note, "2 of 4".
            'ticket_type' => $t ? $this->typePayload($t) : null,
            'entry' => $t ? ['used' => $used, 'admits' => $t->admits()] : null,
            'scan_id' => $o->scan?->id,
            'replay' => $o->replay,
        ] + ($withCounts ? ['counts' => $this->counts($device)] : []);
    }

    private function typePayload(\App\Models\Ticket $t): array
    {
        $type = $t->ticketType;

        // The ticket's own validity (copied at sale), not the type's current one.
        return ($type ? $type->toScanApi() : [
            'id' => null, 'key' => $t->ticket_type, 'name_text' => (string) $t->ticket_type, 'category' => 'general',
            'color' => \App\Models\EventTicketType::CATEGORY_COLORS['general'], 'requires_id' => false, 'min_age' => null, 'max_age' => null,
        ]) + ['validity' => $t->validityValue()];
    }

    /** @return array{in_today: int, out_today: int} */
    public function counts(GateDevice $device): array
    {
        return $this->stats->counts($this->context->get(), $device->gate, $device);
    }
}
