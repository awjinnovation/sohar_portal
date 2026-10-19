<?php

namespace App\Services\Ai;

use App\Exceptions\ApiException;

/**
 * AI error in the contract envelope (docs/ai_and_gate_contract.md §A3):
 * AI_DISABLED (403), AI_RATE_LIMITED (429), AI_BUDGET_EXCEEDED (429),
 * AI_UNAVAILABLE (503). `$internalCode` is what ai_requests.error_code
 * records (e.g. openai_401, timeout) — never shown to clients.
 */
class AiException extends ApiException
{
    public ?string $internalCode = null;

    public ?int $httpStatus = null;

    public static function disabled(): static
    {
        return new static('AI_DISABLED', __('ai.errors.disabled'), 403);
    }

    public static function rateLimited(int $retryAfter = 60): static
    {
        return new static('AI_RATE_LIMITED', __('ai.errors.rate_limited'), 429, ['retry_after' => $retryAfter]);
    }

    public static function budgetExceeded(): static
    {
        return new static('AI_BUDGET_EXCEEDED', __('ai.errors.budget_exceeded'), 429);
    }

    public static function unavailable(?string $internalCode = null, ?int $httpStatus = null): static
    {
        $e = new static('AI_UNAVAILABLE', __('ai.errors.unavailable'), 503);
        $e->internalCode = $internalCode;
        $e->httpStatus = $httpStatus;

        return $e;
    }

    /** Payload of an SSE `event: error`. */
    public function ssePayload(): array
    {
        return array_merge(['code' => $this->errorCode, 'message' => $this->getMessage()], $this->extra);
    }
}
