<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Chooses the gateway driver (config('payments.driver')) and builds the
 * payment_url handed to the app. The URL is a temporary *signed* link to
 * our own redirect page, which then auto-posts to Bank Muscat or — with
 * PAYMENT_DRIVER=fake — shows "Simulate success / failure" buttons.
 */
class PaymentGatewayManager
{
    public const DRIVERS = ['bankmuscat', 'fake'];

    public function driver(): string
    {
        $driver = (string) config('payments.driver', 'bankmuscat');

        if (! in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException("Unknown PAYMENT_DRIVER [$driver]");
        }
        if ($driver === 'fake' && app()->environment('production')) {
            throw new RuntimeException('PAYMENT_DRIVER=fake is not allowed in production');
        }

        return $driver;
    }

    /** @param  array  $query  extra signed query parameters (e.g. lang) */
    public function paymentUrl(Payment $payment, array $query = []): string
    {
        return URL::temporarySignedRoute(
            'payment.redirect',
            now()->addMinutes((int) config('payments.link_ttl_minutes', 60)),
            ['transaction_id' => $payment->transaction_id] + $query
        );
    }
}
