<?php

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Chooses the gateway of a new payment and builds the payment_url handed to
 * the app (docs/payments_amwal.md §5).
 *
 * - `payments.gateways` (tenant setting): the card gateways switched on for
 *   visitors, a subset of SELECTABLE. Absent = only the default (as before
 *   the setting existed). With two on, the visitor chooses (app, box office
 *   pay page, booking pages); with one, nobody is asked.
 * - `payments.driver` (else config('payments.driver') / PAYMENT_DRIVER): the
 *   default gateway, used when the client names none. Bank Muscat and the
 *   fake driver work alone, as before.
 *
 * A payment keeps the gateway it was created with. The URL is a temporary
 * *signed* link to our own redirect page, which opens a Thawani session and
 * redirects there, renders the Amwal SmartBox page, auto-posts to Bank
 * Muscat, or — with the fake driver — shows "Simulate success / failure".
 */
class PaymentGatewayManager
{
    public const DRIVERS = ['thawani', 'amwal', 'bankmuscat', 'fake'];

    /** Card gateways a tenant switches on and off independently (settings payments.gateways). */
    public const SELECTABLE = ['thawani', 'amwal'];

    /** Names shown to visitors (GET /tenant/config → payments.gateways[].name, payment pages). */
    public const NAMES = [
        'thawani' => ['ar' => 'ثواني', 'en' => 'Thawani'],
        'amwal' => ['ar' => 'أموال باي', 'en' => 'Amwal Pay'],
        'bankmuscat' => ['ar' => 'بنك مسقط', 'en' => 'Bank Muscat'],
        'fake' => ['ar' => 'بوابة الدفع التجريبية', 'en' => 'Test gateway'],
    ];

    public const NOT_AVAILABLE = 'GATEWAY_NOT_AVAILABLE';

    /** The configured default driver (tenant `payments.driver`, else PAYMENT_DRIVER). */
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

    /**
     * Gateways a new payment of this tenant may use, the default first.
     *
     * @return list<string>
     */
    public function enabled(?Tenant $tenant = null): array
    {
        $tenant ??= app(TenantContext::class)->get();
        $default = $this->driver($tenant);
        $list = array_values(array_unique(array_filter(
            (array) ($tenant?->setting('payments.gateways') ?? []),
            fn ($d) => is_string($d) && in_array($d, self::DRIVERS, true) && ($d !== 'fake' || ! app()->environment('production')),
        )));
        if (! $list) {
            return [$default];
        }
        // The admin form keeps the default among the enabled ones; a platform
        // default changed later (tenant without its own) falls back to the first.
        if (! in_array($default, $list, true)) {
            $default = $list[0];
        }

        return array_values(array_unique(array_merge([$default], $list)));
    }

    public function defaultGateway(?Tenant $tenant = null): string
    {
        return $this->enabled($tenant)[0];
    }

    /** Two or more gateways on: the visitor is asked which one. */
    public function offersChoice(?Tenant $tenant = null): bool
    {
        return count($this->enabled($tenant)) > 1;
    }

    /**
     * The gateway of a new payment: the requested one when it is enabled,
     * the default when none is requested.
     *
     * @throws ApiException GATEWAY_NOT_AVAILABLE (422) for a gateway that is not on
     */
    public function resolve(?Tenant $tenant, ?string $requested): string
    {
        $enabled = $this->enabled($tenant);
        $requested = strtolower(trim((string) $requested));
        if ($requested === '') {
            return $enabled[0];
        }
        if (! in_array($requested, $enabled, true)) {
            throw new ApiException(self::NOT_AVAILABLE, __('platform.errors.gateway_not_available'), 422, [
                'gateway' => $requested,
                'available' => $enabled,
            ]);
        }

        return $requested;
    }

    /**
     * `payments` block of GET /tenant/config: the enabled gateways (default
     * first) and the default. Never throws (a misconfigured driver must not
     * take the whole config down; payments then fail as before).
     */
    public function present(Tenant $tenant): array
    {
        try {
            $enabled = $this->enabled($tenant);
        } catch (RuntimeException $e) {
            Log::error('Payment gateways misconfigured', ['tenant' => $tenant->slug, 'error' => $e->getMessage()]);

            return ['gateways' => [], 'default_gateway' => null];
        }

        return [
            'gateways' => array_map(fn (string $id) => ['id' => $id, 'name' => self::NAMES[$id] ?? ['ar' => $id, 'en' => $id]], $enabled),
            'default_gateway' => $enabled[0],
        ];
    }

    /** Visitor-facing name of a gateway in a language. */
    public static function label(string $id, string $locale): string
    {
        return self::NAMES[$id][$locale === 'ar' ? 'ar' : 'en'] ?? $id;
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
