<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Gateway\AmwalGateway;
use App\Services\Payments\Gateway\ThawaniGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     * The visitor is back from the gateway without a signed result (Thawani's
     * return never carries one; an Amwal return whose hash fails): ask the
     * gateway now, once, even if the app polled a moment ago. When a check is
     * already asking it (the webhook's, a scheduled one), its answer is
     * awaited instead of asking twice, up to $waitSeconds. Never throws.
     */
    public static function checkOnReturn(Payment $payment, int $waitSeconds): Payment
    {
        $checkout = self::for($payment);
        if (! $checkout || ! $payment->isPending() || ! $payment->gateway_session_id) {
            return $payment;
        }

        try {
            // Same lock as ThawaniCheckout / AmwalCheckout::reconcile().
            Cache::lock("payments:reconcile:{$payment->id}", 120)->block(max(1, $waitSeconds), fn () => null);
            $payment = $payment->fresh() ?? $payment;

            return $payment->isPending() ? $checkout->reconcile($payment, 'redirect', 'interactive') : $payment;
        } catch (\Throwable $e) {
            Log::info('Return page check failed', ['transaction_id' => $payment->transaction_id, 'error' => $e->getMessage()]);

            return $payment->fresh() ?? $payment;
        }
    }
}
