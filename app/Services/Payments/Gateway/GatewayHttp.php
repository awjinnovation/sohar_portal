<?php

namespace App\Services\Payments\Gateway;

use App\Models\PaymentGatewayCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Resilient HTTP for payment gateways (docs/payments_thawani.md §3):
 *
 *  - timeouts on connect and on the whole request;
 *  - retries with exponential backoff + full jitter on network errors,
 *    408 / 425 / 429 and 5xx — `interactive` profile (a visitor is waiting)
 *    or `background` (queued jobs, which the queue retries again);
 *  - non-idempotent requests (create session, refund) are only retried when
 *    they provably never reached the gateway (DNS / connect failure);
 *  - a circuit breaker per gateway + mode;
 *  - every attempt recorded in payment_gateway_calls (secrets redacted).
 */
class GatewayHttp
{
    private const RETRY_STATUSES = [408, 425, 429, 500, 502, 503, 504];

    private const BODY_LIMIT = 65000;

    public function __construct(private CircuitBreaker $circuit) {}

    public function send(GatewayRequest $request, string $profile = 'background'): GatewayResponse
    {
        $p = (array) config("payments.http.$profile", config('payments.http.background'));
        $attempts = max(1, (int) ($p['attempts'] ?? 1));
        $timeout = (float) ($p['timeout'] ?? config('payments.http.timeout', 15));
        $connectTimeout = (float) config('payments.http.connect_timeout', 5);
        $circuit = $request->gateway.':'.($request->mode ?? 'default');
        $correlation = (string) Str::uuid();

        for ($attempt = 1; ; $attempt++) {
            if ($this->circuit->isOpen($circuit)) {
                $this->record($request, $correlation, $attempt, null, null, 'circuit_open', 'Circuit open: not sent', 0);

                throw new GatewayException("{$request->gateway} is unavailable (circuit open)", 'circuit_open');
            }

            $started = hrtime(true);
            try {
                $response = Http::withHeaders($request->headers)
                    ->acceptJson()
                    ->asJson()
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->send($request->method, $request->url, $request->body !== null ? ['json' => $request->body] : []);
            } catch (ConnectionException $e) {
                $ms = $this->elapsed($started);
                $this->record($request, $correlation, $attempt, null, null, 'network_error', $e->getMessage(), $ms);
                $this->circuit->failure($circuit);

                // cURL 6 (resolve) / 7 (connect): nothing was sent.
                $unsent = (bool) preg_match('/cURL error (6|7)\b/', $e->getMessage());
                if (! $request->idempotent && ! $unsent) {
                    throw new GatewayException('No response from the gateway: '.$e->getMessage(), 'network', ambiguous: true);
                }
                if ($attempt >= $attempts) {
                    throw new GatewayException('No response from the gateway: '.$e->getMessage(), 'network');
                }
                $this->backoff($attempt, $p);

                continue;
            }

            $ms = $this->elapsed($started);
            $status = $response->status();
            $body = (string) $response->body();
            $json = json_decode($body, true);
            $json = is_array($json) ? $json : [];
            $envelopeFailed = ($json['success'] ?? null) === false;

            if ($response->successful() && ! $envelopeFailed) {
                $this->record($request, $correlation, $attempt, $status, $body, 'ok', null, $ms);
                $this->circuit->success($circuit);

                return new GatewayResponse($status, $json, $body, $correlation);
            }

            $message = trim((string) ($json['description'] ?? $json['message'] ?? '')) ?: "HTTP $status";
            $retryStatus = in_array($status, self::RETRY_STATUSES, true);
            $this->record($request, $correlation, $attempt, $status, $body, $retryStatus || $status >= 500 ? 'server_error' : 'client_error', $message, $ms);

            if (! $retryStatus && $status < 500) {
                // The gateway answered: it is up; the request itself is wrong.
                $this->circuit->success($circuit);

                throw new GatewayException($message, 'client', $status, $json);
            }

            if ($status >= 500) {
                $this->circuit->failure($circuit);
            }
            // 429 / 503 mean "not processed, try later" (per request); other
            // 5xx on a non-idempotent request may have been processed.
            if (! $request->idempotent && ! in_array($status, $request->unprocessedStatuses, true)) {
                throw new GatewayException($message, 'server', $status, $json, ambiguous: true);
            }
            if ($attempt >= $attempts) {
                throw new GatewayException($message, 'server', $status, $json);
            }
            $this->backoff($attempt, $p, $response->header('Retry-After'));
        }
    }

    private function backoff(int $attempt, array $p, ?string $retryAfter = null): void
    {
        $base = max(0, (int) ($p['base_delay_ms'] ?? 500));
        $max = max($base, (int) ($p['max_delay_ms'] ?? 8000));
        $delay = (int) min($max, $base * (2 ** ($attempt - 1)));
        $delay = random_int((int) ($delay / 2), max((int) ($delay / 2), $delay)); // jitter
        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $delay = (int) min($max, max($delay, (float) $retryAfter * 1000));
        }
        if ($delay > 0) {
            Sleep::usleep($delay * 1000);
        }
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }

    private function record(GatewayRequest $r, string $correlation, int $attempt, ?int $status, ?string $body, string $outcome, ?string $error, int $ms): void
    {
        try {
            PaymentGatewayCall::create([
                'tenant_id' => $r->tenantId,
                'payment_id' => $r->paymentId,
                'gateway' => $r->gateway,
                'mode' => $r->mode,
                'operation' => $r->operation,
                'correlation_id' => $correlation,
                'attempt' => min($attempt, 255),
                'method' => strtoupper($r->method),
                'url' => Str::limit(self::redactUrl($r->url), 495, ''),
                'request_body' => $r->body !== null ? self::redact($r->body) : null,
                'response_status' => $status,
                'response_body' => $body !== null ? Str::limit($body, self::BODY_LIMIT, '…[truncated]') : null,
                'outcome' => $outcome,
                'error' => $error !== null ? Str::limit($error, 2000) : null,
                'duration_ms' => $ms,
            ]);
        } catch (\Throwable $e) {
            // Recording must never break a payment.
            Log::error('Could not record gateway call', ['operation' => $r->operation, 'error' => $e->getMessage()]);
        }
    }

    /** Masks values of secret-looking keys, recursively. */
    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::redact($v);
            } elseif (is_string($k) && preg_match('/secret|api[-_]?key|password|token|authorization|card_?number|cvv/i', $k)) {
                $data[$k] = '[redacted]';
            }
        }

        return $data;
    }

    private static function redactUrl(string $url): string
    {
        return (string) preg_replace('/([?&](?:api_?key|secret|token)=)[^&]+/i', '$1[redacted]', $url);
    }
}
