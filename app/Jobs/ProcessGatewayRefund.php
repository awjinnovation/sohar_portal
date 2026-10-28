<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\PaymentRecorder;
use App\Services\Payments\ThawaniCheckout;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a card refund to the gateway (payment_refunds). Retried with
 * backoff only while the request provably did not go through; an
 * ambiguous outcome is left for a person (status needs_review).
 */
class ProcessGatewayRefund implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    // Above the HTTP budget (payment lookup with retries + the refund call);
    // the queue's retry_after must be larger still (REDIS_QUEUE_RETRY_AFTER).
    public int $timeout = 150;

    // One copy per record while queued or waiting to retry.
    public int $uniqueFor = 10800;

    public function __construct(public int $refundId)
    {
        $this->onQueue(config('payments.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return (string) $this->refundId;
    }

    public function backoff(): array
    {
        return [30, 60, 180, 600, 1800];
    }

    public function handle(ThawaniCheckout $thawani): void
    {
        $refund = PaymentRefund::find($this->refundId);
        if ($refund) {
            $thawani->refund($refund);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $refund = PaymentRefund::find($this->refundId);
        if (! $refund || ! in_array($refund->status, [PaymentRefund::PENDING, PaymentRefund::PROCESSING], true)) {
            return;
        }
        if ($refund->data['in_flight'] ?? false) {
            // Killed (timeout / crash) while the request was out: unknown outcome.
            app(ThawaniCheckout::class)->refundNeedsReview($refund, 'Stopped while the request was out ('.($e?->getMessage() ?: 'timeout').'); check the Thawani portal before retrying');

            return;
        }
        $refund->forceFill(['status' => PaymentRefund::FAILED, 'last_error' => mb_substr((string) $e?->getMessage(), 0, 2000)])->save();

        $payment = Payment::withoutTenantScope()->find($refund->payment_id);
        if ($payment) {
            $payment->forceFill(['needs_review' => true, 'review_reason' => 'refund_failed'])->save();
            app(PaymentRecorder::class)->record($payment, 'gateway_refund_failed', 'system', ['refund_id' => $refund->id, 'error' => $e?->getMessage()]);
        }
    }
}
