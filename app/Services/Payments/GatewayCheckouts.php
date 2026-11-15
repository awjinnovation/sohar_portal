<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Gateway\AmwalGateway;
use App\Services\Payments\Gateway\ThawaniGateway;

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
}
