<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\GatewayCheckouts;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Cancels the pending Thawani / Amwal payment of a gate box office order
 * that expired or was cancelled (BoxOfficeService::close), through the
 * gateway's own cancel: the gateway is asked first (a payment made just
 * before the close completes the order, or flags it for a refund when the
 * seats are gone), Thawani's open sessions are cancelled there, Amwal's
 * attempts stay watched by payments:reconcile for a late capture.
 *
 * Queued so a gate poll or a visitor's page never waits on the gateway. If
 * the job is lost the payment stays pending and payments:reconcile checks
 * and cancels it at its give-up time, as for any payment.
 */
class CancelGatewayPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public int $uniqueFor = 300;

    public function __construct(public int $paymentId, public string $reason = 'box_office_closed')
    {
        $this->onQueue(config('payments.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(PaymentFulfillmentService $fulfillment): void
    {
        $payment = Payment::withoutTenantScope()->find($this->paymentId);
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $flow = GatewayCheckouts::for($payment);
        $flow
            ? $flow->cancel($payment, 'system', $this->reason)
            : $fulfillment->fail($payment, 'cancelled', [], 'system', ['reason' => $this->reason]);
    }
}
