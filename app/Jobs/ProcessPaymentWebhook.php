<?php

namespace App\Jobs;

use App\Models\PaymentWebhookEvent;
use App\Services\Payments\ThawaniCheckout;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies a stored gateway webhook (payment_webhook_events). Retried with
 * backoff while the gateway API cannot be reached; after the last try the
 * event is marked failed (Admin → Payments → Gateway log can replay it, and
 * the reconciler still checks the payment on its own schedule).
 */
class ProcessPaymentWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 150;

    // One copy per record while queued or waiting to retry.
    public int $uniqueFor = 7200;

    public function __construct(public int $eventId)
    {
        $this->onQueue(config('payments.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    /** Seconds before each retry. */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120, 300, 600];
    }

    public function handle(ThawaniCheckout $thawani): void
    {
        $event = PaymentWebhookEvent::find($this->eventId);
        if (! $event || in_array($event->status, [PaymentWebhookEvent::PROCESSED, PaymentWebhookEvent::IGNORED, PaymentWebhookEvent::REJECTED], true)) {
            return;
        }

        $event->forceFill(['attempts' => $event->attempts + 1])->save();

        try {
            $thawani->handleWebhook($event);
        } catch (\Throwable $e) {
            $event->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 2000)])->save();

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        PaymentWebhookEvent::whereKey($this->eventId)->where('status', PaymentWebhookEvent::RECEIVED)
            ->update(['status' => PaymentWebhookEvent::FAILED, 'last_error' => mb_substr((string) $e?->getMessage(), 0, 2000)]);
    }
}
