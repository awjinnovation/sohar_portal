<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\Log;

/**
 * Writes the payment timeline (payment_events). Sources: api, redirect,
 * webhook, reconciler, poll, admin, scheduler, system. A failure to record
 * is logged and never breaks the payment itself.
 */
class PaymentRecorder
{
    public function record(Payment $payment, string $type, string $source, array $data = [], ?string $from = null, ?string $to = null): void
    {
        try {
            PaymentEvent::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'type' => $type,
                'from_status' => $from,
                'to_status' => $to,
                'source' => $source,
                'actor_id' => auth()->id(),
                'data' => $data ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Could not record payment event', ['payment_id' => $payment->id, 'type' => $type, 'error' => $e->getMessage()]);
        }
    }

    /** A status change (from → to). */
    public function transition(Payment $payment, string $from, string $to, string $source, array $data = []): void
    {
        if ($from !== $to) {
            $this->record($payment, $to, $source, $data, $from, $to);
        }
    }
}
