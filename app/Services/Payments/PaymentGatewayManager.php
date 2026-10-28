<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Chooses the gateway driver and builds the payment_url handed to the app.
 * The driver is the tenant's `payments.driver` setting (Admin → Tenants →
 * payment gateway), else config('payments.driver'); a payment keeps the
 * driver it was created with. The URL is a temporary *signed* link to our
 * own redirect page, which opens a Thawani session and redirects there,
 * auto-posts to Bank Muscat, or — with the fake driver — shows "Simulate
 * success / failure" buttons.
 */
class PaymentGatewayManager
{
    public const DRIVERS = ['thawani', 'bankmuscat', 'fake'];

    public function driver(?Tenant $tenant = null): string
    {
        $tenant ??= app(TenantContext::class)->get();
        $driver = (string) ($tenant?->setting('payments.driver') ?: config('payments.driver', 'thawani'));

        if (! in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException("Unknown payment driver [$driver]");
        }
        if ($driver === 'fake' && app()->environment('production')) {
            throw new RuntimeException('The fake payment driver is not allowed in production');
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
