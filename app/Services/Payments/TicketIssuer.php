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
        $quantity = max(1, (int) ($meta['quantity'] ?? 1));
        $eventId = (int) ($meta['event_id'] ?? $payment->payable_id);
        $event = Event::withoutTenantScope()->find($eventId);
        $decimals = (int) ($meta['currency_decimals'] ?? 3);

        // Price actually paid for the tickets (excludes refundable deposits).
        $ticketsMinor = (int) ($meta['tickets_minor'] ?? $payment->amount_minor ?? Money::toMinor((string) $payment->amount, $decimals));
        $per = intdiv($ticketsMinor, $quantity);
        $remainder = $ticketsMinor - $per * $quantity;

        $tickets = collect();
        for ($i = 0; $i < $quantity; $i++) {
            $priceMinor = $per + ($i === 0 ? $remainder : 0);
            $tickets->push($this->createWithRetry([
                'user_id' => $payment->user_id,
                'event_id' => $eventId,
                'transaction_id' => $payment->transaction_id,
                'booking_date' => $meta['booking_date'] ?? null,
                'quantity' => 1,
                'ticket_type' => $meta['ticket_type'] ?? 'standard',
                'status' => 'active',
                'price' => Money::format($priceMinor, $decimals),
                'currency' => $payment->currency,
                'holder_name' => $payment->user?->name ?? 'Guest',
                'purchase_date' => now(),
            ], $payment->tenant_id));
        }

        return $tickets;
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
