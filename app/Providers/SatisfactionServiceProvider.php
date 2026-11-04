<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Visitor satisfaction survey «راضي» (docs/radi_satisfaction_contract.md):
 * the rate limiter of POST /api/v1/satisfaction/responses and the EN
 * translations of the admin report (lang/satisfaction/en.json, merged with
 * lang/en.json by the translator).
 */
class SatisfactionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(lang_path('satisfaction'));

        // Answers: 10 a minute per account or device, 60 an hour per IP
        // (a family on one festival Wi-Fi shares an IP).
        RateLimiter::for('satisfaction', function (Request $r) {
            $who = $r->user('sanctum')?->id ? 'u'.$r->user('sanctum')->id : 'd'.substr(sha1((string) $r->input('device_id')), 0, 16).$r->ip();

            return [
                Limit::perMinute(10)->by('satisfaction:'.$who),
                Limit::perHour(60)->by('satisfaction-ip:'.$r->ip()),
            ];
        });
    }
}
