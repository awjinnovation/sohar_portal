<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Gateway\AmwalGateway;
use App\Services\Payments\Gateway\ThawaniGateway;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * The checkout flow of the gateways that are checked through their API
 * (reconcile, cancel, webhooks, refunds): Thawani and Amwal. Bank Muscat
 * and the fake driver have none (null).
 */
class GatewayCheckouts
{
    /** Gateways handled by payments:reconcile. */
    public const RECONCILED = [ThawaniGateway::NAME, AmwalGateway::NAME];

    public static function for(Payment|string|null $gateway): ThawaniCheckout|AmwalCheckout|null
    {
        $name = $gateway instanceof Payment ? $gateway->gateway : $gateway;

        return match ($name) {
            ThawaniGateway::NAME => app(ThawaniCheckout::class),
            AmwalGateway::NAME => app(AmwalCheckout::class),
            default => null,
        };
    }

    /** Poll helper: a pending API-checked payment is checked now (throttled); others are returned as they are. */
    public static function checkIfStale(Payment $payment, string $source = 'poll'): Payment
    {
        return self::for($payment)?->checkIfStale($payment, $source) ?? $payment;
    }

    /**
     * Return page: when a check is settling the payment right now (the
     * gateway's notification, a scheduled check), wait for it to finish, up
     * to $seconds, and return the payment as it then stands. Interactive
     * checks skip a payment whose lock is taken, so without this wait the
     * visitor saw "processing" a second before the payment completed.
     */
    public static function afterRunningCheck(Payment $payment, int $seconds): Payment
    {
        if (! self::for($payment) || $payment->fulfilled_at || $payment->status === 'completed') {
            return $payment;
        }

        try {
            // Same lock as ThawaniCheckout / AmwalCheckout::reconcile().
            Cache::lock("payments:reconcile:{$payment->id}", 120)->block(max(1, $seconds), fn () => null);
        } catch (LockTimeoutException) {
            return $payment; // still running: the page shows what is known now
        }

        return $payment->fresh() ?? $payment;
    }
}
