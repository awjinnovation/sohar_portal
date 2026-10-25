<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Thin client over the OpenAI Responses API (POST /v1/responses).
 *
 * - Stateless (`store: false`): the full input (history + tool calls +
 *   tool outputs + reasoning items) is sent on every round.
 * - Retries with exponential backoff (honouring Retry-After) on 429 / 5xx /
 *   connection errors; streaming calls only retry before the first byte.
 * - Every call is logged to ai_requests via AiUsage (tokens, latency,
 *   status, error code) — never the prompt, the answer or the key.
 * - Errors never carry the upstream message (OpenAI echoes part of the key
 *   on 401s); they surface as AiException AI_UNAVAILABLE.
 */
class OpenAiClient
{
    public const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504];

    public function __construct(private AiUsage $usage) {}

    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /** Model id for a tier: fast | smart. */
    public function model(string $tier): string
    {
        return (string) config($tier === 'smart' ? 'services.openai.model_smart' : 'services.openai.model_fast');
    }

    /**
     * Base request body for a tier (model + reasoning effort + stateless).
     */
    public function payload(string $tier, array $body): array
    {
        $effort = (string) config($tier === 'smart' ? 'services.openai.reasoning_smart' : 'services.openai.reasoning_fast');

        return array_filter(array_merge([
            'model' => $this->model($tier),
            'store' => false,
            'reasoning' => $effort !== '' ? ['effort' => $effort] : null,
        ], $body), fn ($v) => $v !== null);
    }

    /** Non-streaming call. */
    public function create(array $payload, AiCall $call): OpenAiResponse
    {
        $this->assertConfigured($call);
        $payload['stream'] = false;
        $started = microtime(true);

        try {
            $response = $this->send($payload, false);
            $data = $response->json();
            if (! is_array($data)) {
                throw AiException::unavailable('invalid_json', $response->status());
            }
            $result = new OpenAiResponse($data);
            if ($result->status() === 'failed') {
                throw AiException::unavailable('response_failed', $response->status());
            }
            $this->usage->record($call, $payload['model'] ?? null, $result, $this->ms($started), 'ok');

            return $result;
        } catch (AiException $e) {
            $this->usage->record($call, $payload['model'] ?? null, null, $this->ms($started), 'error', $e->internalCode, $e->httpStatus);
            throw $e;
        }
    }

    /**
     * Streaming call. $onEvent(string $type, array $data) receives every
     * Responses API event (response.output_text.delta, …). Returns the final
     * response (from response.completed / response.incomplete).
     */
    public function stream(array $payload, AiCall $call, callable $onEvent): OpenAiResponse
    {
        $this->assertConfigured($call);
        $payload['stream'] = true;
        $started = microtime(true);

        try {
            $response = $this->send($payload, true);
            $body = $response->toPsrResponse()->getBody();
            $parser = new SseParser;
            $final = null;
            $items = [];

            $handle = function (array $event) use (&$final, &$items, $onEvent) {
                if ($event['data'] === '[DONE]') {
                    return;
                }
                $data = json_decode($event['data'], true);
                if (! is_array($data)) {
                    return;
                }
                $type = (string) ($data['type'] ?? $event['event'] ?? '');
                if ($type === 'response.output_item.done' && is_array($data['item'] ?? null)) {
                    $items[(int) ($data['output_index'] ?? count($items))] = $data['item'];
                }
                if (in_array($type, ['response.completed', 'response.incomplete'], true) && is_array($data['response'] ?? null)) {
                    $final = $data['response'];
                }
                if ($type === 'response.failed' || $type === 'error') {
                    throw AiException::unavailable('stream_'.($type === 'error' ? 'error' : 'failed'), 200);
                }
                $onEvent($type, $data);
            };

            while (! $body->eof()) {
                $chunk = $body->read(2048);
                if ($chunk === '') {
                    continue;
                }
                foreach ($parser->feed($chunk) as $event) {
                    $handle($event);
                }
            }
            foreach ($parser->finish() as $event) {
                $handle($event);
            }

            if ($final === null) {
                if (! $items) {
                    throw AiException::unavailable('stream_incomplete', 200);
                }
                ksort($items);
                $final = ['status' => 'incomplete', 'output' => array_values($items)];
            } elseif (empty($final['output']) && $items) {
                ksort($items);
                $final['output'] = array_values($items);
            }

            $result = new OpenAiResponse($final);
            $this->usage->record($call, $payload['model'] ?? null, $result, $this->ms($started), 'ok');

            return $result;
        } catch (AiException $e) {
            $this->usage->record($call, $payload['model'] ?? null, null, $this->ms($started), 'error', $e->internalCode, $e->httpStatus);
            throw $e;
        } catch (Throwable $e) {
            // Never let a raw exception (which could carry request details) escape.
            $this->usage->record($call, $payload['model'] ?? null, null, $this->ms($started), 'error', 'stream_exception');
            throw AiException::unavailable('stream_exception');
        }
    }

    /**
     * POST /responses with retries. Returns a 2xx response or throws
     * AiException (without the upstream message).
     */
    private function send(array $payload, bool $stream): Response
    {
        $attempts = 1 + max(0, (int) config('services.openai.retries', 2));
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->http($stream)->post('/responses', $payload);
            } catch (ConnectionException $e) {
                $timeout = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout');
                if ($attempt < $attempts) {
                    $this->backoff($attempt);

                    continue;
                }
                throw AiException::unavailable($timeout ? 'timeout' : 'connection');
            } catch (Throwable $e) {
                throw AiException::unavailable('transport');
            }

            if ($response->successful()) {
                return $response;
            }

            $lastStatus = $response->status();
            if (in_array($lastStatus, self::RETRY_STATUSES, true) && $attempt < $attempts && ! $this->isQuotaError($response)) {
                $this->backoff($attempt, $response->header('Retry-After'));

                continue;
            }

            throw AiException::unavailable($this->errorCode($response), $lastStatus);
        }

        throw AiException::unavailable('retries_exhausted', $lastStatus);
    }

    private function http(bool $stream): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1'))
            ->withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->asJson()
            ->timeout(max(5, (int) config('services.openai.timeout', 45)))
            ->connectTimeout(max(2, (int) config('services.openai.connect_timeout', 8)));

        if ($org = config('services.openai.organization')) {
            $request = $request->withHeaders(['OpenAI-Organization' => $org]);
        }

        return $stream ? $request->withOptions(['stream' => true])->withHeaders(['Accept' => 'text/event-stream']) : $request;
    }

    private function backoff(int $attempt, ?string $retryAfter = null): void
    {
        $ms = is_numeric($retryAfter) && (float) $retryAfter <= 10
            ? (int) ((float) $retryAfter * 1000)
            : (int) (400 * (2 ** ($attempt - 1)) + random_int(0, 250));

        Sleep::for(min($ms, 10000))->milliseconds();
    }

    private function isQuotaError(Response $response): bool
    {
        return $response->status() === 429 && data_get($response->json(), 'error.code') === 'insufficient_quota';
    }

    /** "openai_<status>[_<type|code>]" — upstream messages are dropped on purpose. */
    private function errorCode(Response $response): string
    {
        $code = data_get($response->json(), 'error.code') ?: data_get($response->json(), 'error.type');
        $code = is_string($code) ? preg_replace('/[^a-z0-9_]/', '', strtolower($code)) : '';

        return substr('openai_'.$response->status().($code ? '_'.$code : ''), 0, 60);
    }

    private function assertConfigured(AiCall $call): void
    {
        if (! $this->configured()) {
            $this->usage->record($call, null, null, 0, 'error', 'not_configured');
            throw AiException::unavailable('not_configured');
        }
    }

    private function ms(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
