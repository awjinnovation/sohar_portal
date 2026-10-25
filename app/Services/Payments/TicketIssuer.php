<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place tickets are created for a paid order (gateway webhook,
 * gateway redirect, fake driver and wallet checkout all end up here via
 * PaymentFulfillmentService). Idempotent per payment: callers hold a row
 * lock on the payment and tickets already issued for the transaction are
 * returned instead of duplicated.
 *
 * qr_code === ticket_number (TKT-YYYY-NNNNNN) — the format the gate scanner
 * (/tickets/validate) looks up.
 */
class TicketIssuer
{
    public function issue(Payment $payment): Collection
    {
        $existing = Ticket::withoutTenantScope()->where('transaction_id', $payment->transaction_id)->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $meta = $payment->metadata ?? [];
        $eventId = (int) ($meta['event_id'] ?? $payment->payable_id);
        $event = Event::withoutTenantScope()->find($eventId);
        $decimals = (int) ($meta['currency_decimals'] ?? 3);
        $lines = $this->lines($meta, $event);
        $quantity = max(1, array_sum(array_column($lines, 'quantity')));

        // Price actually paid for the tickets (excludes refundable deposits),
        // spread over the tickets in proportion to their type's price.
        $ticketsMinor = (int) ($meta['tickets_minor'] ?? $payment->amount_minor ?? Money::toMinor((string) $payment->amount, $decimals));
        $subtotal = array_sum(array_map(fn ($l) => $l['unit_price_minor'] * $l['quantity'], $lines));
        $prices = [];
        foreach ($lines as $l) {
            for ($i = 0; $i < $l['quantity']; $i++) {
                $prices[] = $subtotal > 0 ? intdiv($l['unit_price_minor'] * $ticketsMinor, $subtotal) : intdiv($ticketsMinor, $quantity);
            }
        }
        if ($prices) {
            $prices[0] += $ticketsMinor - array_sum($prices);
        }

        $tickets = collect();
        $n = 0;
        foreach ($lines as $l) {
            for ($i = 0; $i < $l['quantity']; $i++) {
                $tickets->push($this->createWithRetry([
                    'user_id' => $payment->user_id,
                    'event_id' => $eventId,
                    'transaction_id' => $payment->transaction_id,
                    'booking_date' => $meta['booking_date'] ?? null,
                    'quantity' => 1,
                    'ticket_type' => $l['key'],
                    'ticket_type_id' => $l['ticket_type_id'],
                    // A family ticket admitting 4 is scanned up to 4 times.
                    'max_check_ins' => max(1, (int) $l['admits']),
                    'status' => 'active',
                    'price' => Money::format($prices[$n++] ?? 0, $decimals),
                    'currency' => $payment->currency,
                    'holder_name' => $payment->user?->name ?? 'Guest',
                    'purchase_date' => now(),
                ], $payment->tenant_id));
            }
        }

        return $tickets;
    }

    /**
     * Lines of the order (metadata.lines). Payments made before ticket types
     * carry only quantity + ticket_type: they map to the type of that key.
     *
     * @return list<array{ticket_type_id: ?int, key: string, quantity: int, unit_price_minor: int, admits: int}>
     */
    private function lines(array $meta, ?Event $event): array
    {
        if (! empty($meta['lines'])) {
            return array_values(array_map(fn ($l) => [
                'ticket_type_id' => isset($l['ticket_type_id']) ? (int) $l['ticket_type_id'] : null,
                'key' => (string) ($l['key'] ?? 'general'),
                'quantity' => max(0, (int) ($l['quantity'] ?? 0)),
                'unit_price_minor' => (int) ($l['unit_price_minor'] ?? 0),
                'admits' => max(1, (int) ($l['admits'] ?? 1)),
            ], $meta['lines']));
        }
        $type = $event?->ticketTypeByKey($meta['ticket_type'] ?? null, \App\Models\EventTicketType::CHANNEL_BOX_OFFICE);

        return [[
            'ticket_type_id' => $type?->id,
            'key' => $type?->key ?? (string) ($meta['ticket_type'] ?? 'general'),
            'quantity' => max(1, (int) ($meta['quantity'] ?? 1)),
            'unit_price_minor' => (int) ($meta['unit_price_minor'] ?? 0),
            'admits' => max(1, (int) ($type?->admits ?? 1)),
        ]];
    }

    private function createWithRetry(array $attributes, int $tenantId, int $attempts = 5): Ticket
    {
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction(function () use ($attributes, $tenantId) {
                    $ticket = new Ticket($attributes);
                    $ticket->tenant_id = $tenantId;
                    $ticket->save();

                    return $ticket;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Concurrent issuance grabbed the same sequential number.
                if ($try >= $attempts) {
                    throw $e;
                }
            }
        }
    }
}
