<?php

namespace App\Providers;

use App\Models\GateDevice;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Gate scanner module: device tokens kept off user routes, rate limiters
 * for /api/v1/gate/* and the EN
 * translations of the gate admin pages (lang/gate/en.json, merged with
 * lang/en.json by the translator).
 */
class GateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request/job, shared by the controller and TicketScanService (hash-map memo).
        $this->app->scoped(\App\Services\Gate\GateManifestService::class);
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(lang_path('gate'));

        // Gate scanner devices own Sanctum tokens too, but those are only
        // valid on /api/v1/gate/* (AuthenticateGateDevice reads them
        // directly): `auth:sanctum` user routes must never accept them.
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $isValid) => $isValid && $token->tokenable_type !== (new GateDevice)->getMorphClass()
        );

        $device = fn (Request $r) => (string) ($r->attributes->get('gate_device')?->id ?: $r->ip());

        // Pairing codes are 6 chars from 31 symbols: 10 tries / minute / IP.
        RateLimiter::for('gate-pair', fn (Request $r) => [
            Limit::perMinute(10)->by('gate-pair:'.$r->ip()),
            Limit::perHour(60)->by('gate-pair-h:'.$r->ip()),
        ]);
        // A busy lane scans ~1 ticket/s; leave headroom for retries.
        RateLimiter::for('gate-scan', fn (Request $r) => Limit::perMinute(240)->by('gate-scan:'.$device($r)));
        RateLimiter::for('gate-api', fn (Request $r) => Limit::perMinute(120)->by('gate-api:'.$device($r)));
        RateLimiter::for('gate-sync', fn (Request $r) => Limit::perMinute(30)->by('gate-sync:'.$device($r)));
        // Box office: reads include polling an order every 3 s and live quotes.
        RateLimiter::for('gate-sales', fn (Request $r) => Limit::perMinute(180)->by('gate-sales:'.$device($r)));
        RateLimiter::for('gate-sales-write', fn (Request $r) => Limit::perMinute(30)->by('gate-sales-w:'.$device($r)));
        // Public payment pages (/pay/{token}): per IP.
        RateLimiter::for('pay-page', fn (Request $r) => Limit::perMinute(60)->by('pay-page:'.$r->ip()));
        RateLimiter::for('pay-checkout', fn (Request $r) => Limit::perMinute(10)->by('pay-checkout:'.$r->ip()));
        RateLimiter::for('gate-staff-login', fn (Request $r) => [
            Limit::perMinutes(10, 10)->by('gate-staff:'.$device($r)),
            Limit::perMinutes(10, 10)->by('gate-staff-phone:'.preg_replace('/\D/', '', (string) $r->input('phone'))),
        ]);
    }
}
