<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\Tenant;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Cost & abuse controls (contract §A1): module switches, monthly token
 * budget per tenant, rate limits, and the ai_requests usage log.
 */
class AiUsage
{
    /** AI_DISABLED unless the tenant module is on (and a key is configured for visitors). */
    public function ensureModule(Tenant $tenant, string $module): void
    {
        if (! $tenant->moduleEnabled($module)) {
            throw AiException::disabled();
        }
    }

    /** Admin copilot / briefing / gate insights switch (tenant setting ai.copilot_enabled). */
    public function ensureCopilot(Tenant $tenant): void
    {
        if (! (bool) $tenant->setting('ai.copilot_enabled', true)) {
            throw AiException::disabled();
        }
    }

    /** Show the admin copilot UI: key configured, ai.copilot_enabled and a budget > 0. */
    public static function copilotAvailable(?Tenant $tenant): bool
    {
        return $tenant !== null
            && filled(config('services.openai.key'))
            && (bool) $tenant->setting('ai.copilot_enabled', true)
            && (int) $tenant->setting('ai.monthly_token_budget', 0) > 0;
    }

    public function monthlyBudget(Tenant $tenant): int
    {
        return max(0, (int) $tenant->setting('ai.monthly_token_budget', 2000000));
    }

    /** Tokens (input + output) used this calendar month in the tenant time zone. */
    public function monthTokens(Tenant $tenant): int
    {
        $from = Carbon::now(TenantTime::zone($tenant))->startOfMonth()->setTimezone(config('app.timezone'));

        return (int) AiRequest::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as total')
            ->value('total');
    }

    public function ensureBudget(Tenant $tenant): void
    {
        $budget = $this->monthlyBudget($tenant);
        if ($budget === 0 || $this->monthTokens($tenant) >= $budget) {
            throw AiException::budgetExceeded();
        }
    }

    /**
     * Count one hit against a fixed-window limit; AI_RATE_LIMITED when full.
     *
     * @param  array{max:int, minutes:int}  $limit
     */
    public function throttle(string $key, array $limit): void
    {
        $max = max(1, (int) ($limit['max'] ?? 10));
        $decay = max(1, (int) ($limit['minutes'] ?? 10)) * 60;
        $key = 'ai:'.$key;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw AiException::rateLimited(max(1, RateLimiter::availableIn($key)));
        }
        RateLimiter::hit($key, $decay);
    }

    /** One ai_requests row per OpenAI call (or blocked attempt). Never throws. */
    public function record(AiCall $call, ?string $model, ?OpenAiResponse $response, int $latencyMs, string $status, ?string $errorCode = null, ?int $httpStatus = null): void
    {
        try {
            AiRequest::withoutTenantScope()->create([
                'tenant_id' => $call->tenant->id,
                'user_id' => $call->user?->id,
                'feature' => substr($call->feature, 0, 40),
                'model' => $response?->model() ?? $model,
                'input_tokens' => $response?->inputTokens() ?? 0,
                'output_tokens' => $response?->outputTokens() ?? 0,
                'cached_tokens' => $response?->cachedTokens() ?? 0,
                'reasoning_tokens' => $response?->reasoningTokens() ?? 0,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error_code' => $errorCode ? substr($errorCode, 0, 60) : null,
                'http_status' => $httpStatus,
            ]);
        } catch (Throwable $e) {
            // Usage logging must never break a visitor request.
            report(new \RuntimeException('ai_requests insert failed'));
        }
    }

    /** Log a request refused before reaching OpenAI (rate limit / budget). */
    public function recordBlocked(AiCall $call, AiException $e): void
    {
        $status = match ($e->errorCode) {
            'AI_RATE_LIMITED' => 'rate_limited',
            'AI_BUDGET_EXCEEDED' => 'budget_exceeded',
            'AI_DISABLED' => 'disabled',
            default => 'error',
        };
        $this->record($call, null, null, 0, $status, strtolower($e->errorCode));
    }

    /** Usage summary for the admin (tokens this month, budget, estimated USD). */
    public function summary(Tenant $tenant): array
    {
        $from = Carbon::now(TenantTime::zone($tenant))->startOfMonth()->setTimezone(config('app.timezone'));
        $rows = AiRequest::withoutTenantScope()
            ->where('tenant_id', $tenant->id)->where('created_at', '>=', $from)
            ->selectRaw('model, COALESCE(SUM(input_tokens),0) as i, COALESCE(SUM(output_tokens),0) as o, COUNT(*) as n')
            ->groupBy('model')->get();
        $usd = 0.0;
        foreach ($rows as $row) {
            [$pi, $po] = [0, 0];
            foreach ((array) config('ai.prices', []) as $prefix => $price) {
                if (str_starts_with((string) $row->model, (string) $prefix)) {
                    [$pi, $po] = $price;
                }
            }
            $usd += ($row->i * $pi + $row->o * $po) / 1_000_000;
        }
        $used = (int) $rows->sum(fn ($r) => $r->i + $r->o);

        return [
            'tokens_used' => $used,
            'budget' => $this->monthlyBudget($tenant),
            'requests' => (int) $rows->sum('n'),
            'estimated_usd' => round($usd, 2),
        ];
    }
}
