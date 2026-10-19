<?php

namespace App\Console\Commands;

use App\Models\Gate;
use App\Models\Tenant;
use App\Services\Gate\GatePairingService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Print a fresh single-use pairing code for a gate (local testing / support).
 *
 *   php artisan gate:pair-code north-batinah A
 *   php artisan gate:pair-code demo H1 --minutes=60 --api=http://192.168.1.20:8002
 */
class GatePairCode extends Command
{
    protected $signature = 'gate:pair-code {tenant : Tenant slug} {gate : Gate code or id} {--minutes=10 : Validity} {--api= : Server root encoded in the deep link (default APP_URL)}';

    protected $description = 'Create a pairing code (and deep link) for a gate scanner device';

    public function handle(TenantContext $context, GatePairingService $pairing): int
    {
        $tenant = Tenant::findBySlug($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Unknown tenant: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        return $context->run($tenant, function () use ($tenant, $pairing) {
            $key = (string) $this->argument('gate');
            $gate = Gate::where('code', strtoupper($key))->first() ?? (ctype_digit($key) ? Gate::find((int) $key) : null);
            if (! $gate) {
                $this->error("Unknown gate \"$key\" in {$tenant->slug}. Gates: ".Gate::pluck('code')->implode(', '));

                return self::FAILURE;
            }

            $minutes = max(1, min(24 * 60, (int) $this->option('minutes')));
            $code = $pairing->createCode($gate, null, $minutes);
            $link = GatePairingService::deepLink($code, $tenant, $this->option('api') ?: config('app.url'));

            $this->line($code->code);
            $this->info("Gate {$gate->code} · {$gate->localizedName('en')} ({$tenant->slug}) — valid until ".$code->expires_at->toIso8601String()." ({$minutes} min, single use)");
            $this->line($link);

            return self::SUCCESS;
        });
    }
}
