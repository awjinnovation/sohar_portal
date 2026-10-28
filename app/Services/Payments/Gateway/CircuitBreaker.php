<?php

namespace App\Services\Payments\Gateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Consecutive-failure circuit breaker in the cache (shared by every worker
 * when the cache is Redis). Open: calls fail fast for cooldown_seconds.
 * After the cooldown a single trial call goes through (half-open) while the
 * others still fail fast; its failure re-opens the circuit at once, its
 * success closes it.
 */
class CircuitBreaker
{
    private function key(string $name, string $part): string
    {
        return "payments:circuit:$name:$part";
    }

    /** True = do not send. In half-open state, lets exactly one trial through. */
    public function isOpen(string $name): bool
    {
        if ((int) Cache::get($this->key($name, 'open_until'), 0) > now()->timestamp) {
            return true;
        }
        if (Cache::get($this->key($name, 'half_open'), false)) {
            // One trial per cooldown window; the others wait for its result.
            $cooldown = max(1, (int) config('payments.circuit.cooldown_seconds', 30));

            return ! Cache::add($this->key($name, 'trial'), 1, now()->addSeconds($cooldown));
        }

        return false;
    }

    public function failure(string $name): void
    {
        $threshold = max(1, (int) config('payments.circuit.threshold', 8));
        $cooldown = max(1, (int) config('payments.circuit.cooldown_seconds', 30));

        Cache::add($this->key($name, 'fails'), 0, now()->addMinutes(10));
        $fails = (int) Cache::increment($this->key($name, 'fails'));
        $halfOpen = (bool) Cache::get($this->key($name, 'half_open'), false);

        if ($fails >= $threshold || $halfOpen) {
            Cache::put($this->key($name, 'open_until'), now()->addSeconds($cooldown)->timestamp, now()->addSeconds($cooldown + 60));
            Cache::put($this->key($name, 'half_open'), true, now()->addSeconds($cooldown + 3600));
            Cache::forget($this->key($name, 'trial'));
            Cache::forget($this->key($name, 'fails'));
            Log::warning('Payment gateway circuit opened', ['circuit' => $name, 'failures' => $fails, 'cooldown_seconds' => $cooldown]);
        }
    }

    public function success(string $name): void
    {
        Cache::forget($this->key($name, 'fails'));
        Cache::forget($this->key($name, 'half_open'));
        Cache::forget($this->key($name, 'trial'));
    }
}
