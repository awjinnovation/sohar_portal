<?php

namespace App\Providers;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Abilities reserved to platform super-admins (never granted by a
     * tenant role).
     */
    public const PLATFORM_ABILITIES = ['manage tenants', 'manage demo requests'];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Behind a TLS-terminating proxy (Coolify's Traefik / Caddy) the
        // proxy's X-Forwarded-Proto is trusted (config/trustedproxy.php);
        // this is the safety net so generated URLs, redirects and signed
        // links never fall back to http:// in production.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Use custom Bootstrap 5 pagination view
        Paginator::useBootstrapFive();

        // Platform super-admins can do everything; a tenant `admin` can do
        // everything inside the current tenant except platform abilities.
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            if (in_array($ability, self::PLATFORM_ABILITIES, true)) {
                return false;
            }

            return $user->hasRole('admin') ? true : null;
        });

        // Every view gets the current tenant as $brand (name, logo, colors).
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            if (! array_key_exists('brand', $view->getData())) {
                $view->with('brand', app(TenantContext::class)->get());
            }
        });

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        $phone = fn (Request $r) => preg_replace('/\D/', '', (string) $r->input('phone_number'));

        // send-otp: 1 per 2 minutes and 5 per hour per phone, 20 per hour per IP.
        RateLimiter::for('otp-send', fn (Request $r) => [
            Limit::perMinutes(2, 1)->by('otp-send-min:'.$phone($r)),
            Limit::perHour(5)->by('otp-send-hour:'.$phone($r)),
            Limit::perHour(20)->by('otp-send-ip:'.$r->ip()),
        ]);

        // verify-otp: 10 per 10 minutes per phone, 30 per hour per IP.
        RateLimiter::for('otp-verify', fn (Request $r) => [
            Limit::perMinutes(10, 10)->by('otp-verify:'.$phone($r)),
            Limit::perHour(30)->by('otp-verify-ip:'.$r->ip()),
        ]);

        // Landing-page demo form: 5 per hour and 20 per day per IP.
        RateLimiter::for('demo-requests', fn (Request $r) => [
            Limit::perHour((int) config('landing.rate_per_hour', 5))->by('demo-hour:'.$r->ip())
                ->response(fn (Request $r, array $headers) => \App\Http\Controllers\LandingController::throttled($r, $headers)),
            Limit::perDay((int) config('landing.rate_per_day', 20))->by('demo-day:'.$r->ip())
                ->response(fn (Request $r, array $headers) => \App\Http\Controllers\LandingController::throttled($r, $headers)),
        ]);

        RateLimiter::for('money', fn (Request $r) => Limit::perMinute(30)->by('money:'.($r->user()?->id ?: $r->ip())));
    }
}
