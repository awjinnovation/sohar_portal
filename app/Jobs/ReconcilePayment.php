<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\ThawaniCheckout;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks one pending (or late-capture candidate) gateway payment with the
 * gateway (payments:reconcile). Unique per payment while queued; a
 * failure is retried, and the next scheduled check runs anyway.
 */
class ReconcilePayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Above the HTTP budget (a few sessions × retries); the queue's
    // retry_after must be larger still (REDIS_QUEUE_RETRY_AFTER).
    public int $timeout = 150;

    public int $uniqueFor = 300;

    public function __construct(public int $paymentId, public bool $final = false, public string $source = 'reconciler')
    {
        $this->onQueue(config('payments.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(ThawaniCheckout $thawani): void
    {
        $payment = Payment::withoutTenantScope()->find($this->paymentId);
        if (! $payment) {
            return;
        }

        $thawani->reconcile($payment, $this->source, 'background', $this->final);
    }
}
