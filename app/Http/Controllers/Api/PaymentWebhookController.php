<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentWebhookEvent;
use App\Models\Tenant;
use App\Services\Payments\Gateway\ThawaniConfig;
use App\Services\Payments\Gateway\ThawaniGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gateway webhooks (docs/payments_thawani.md §5). Every delivery is stored
 * first, then applied by a queued job with retries; the response is
 * immediate. The payload is never trusted on its own: the job asks the
 * gateway API for the session state.
 *
 * A delivery is only accepted when it is signed with the webhook secret of
 * the tenant that owns the payment (the URL's tenant must match). Rejected
 * deliveries are kept with the first 2 KB of the body and the reason;
 * identical deliveries are one row (deliveries counted).
 */
class PaymentWebhookController extends Controller
{
    private const HEADERS = ['thawani-signature', 'thawani-timestamp', 'content-type', 'user-agent', 'x-forwarded-for'];

    private const MAX_BODY = 64 * 1024;

    private const REJECTED_BODY = 2048;

    /** POST /api/v1/payments/webhooks/thawani/{tenant?} */
    public function thawani(Request $request, ?string $tenant = null)
    {
        $raw = (string) $request->getContent();
        if ($raw === '' || strlen($raw) > self::MAX_BODY) {
            return response()->json(['success' => false, 'message' => 'Invalid body'], $raw === '' ? 400 : 413);
        }
        $body = json_decode($raw, true);
        $data = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : [];
        $sessionId = isset($data['session_id']) ? mb_substr((string) $data['session_id'], 0, 120) : null;

        [$owner, $reason] = $this->owner($tenant, $sessionId, (string) ($data['client_reference_id'] ?? ''));
        if (! is_array($body)) {
            $reason = 'invalid_json';
        }
        if ($reason === null) {
            $config = ThawaniConfig::forTenant($owner);
            $reason = match (true) {
                $config->webhookSecret === '' => 'no_webhook_secret',
                ! ThawaniGateway::verifySignature($config, $raw, (string) $request->header('thawani-timestamp', ''), (string) $request->header('thawani-signature', '')) => 'bad_signature',
                ! $this->freshTimestamp((string) $request->header('thawani-timestamp', '')) => 'stale_timestamp',
                default => null,
            };
        }

        $event = $this->store($request, $raw, $body, $sessionId, $owner, $reason);

        if ($reason !== null) {
            Log::warning('Thawani webhook rejected', ['reason' => $reason, 'event_id' => $event->id, 'ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Rejected'], $reason === 'invalid_json' ? 400 : 401);
        }

        if (in_array($event->status, [PaymentWebhookEvent::RECEIVED, PaymentWebhookEvent::FAILED], true)) {
            try {
                ProcessPaymentWebhook::dispatch($event->id);
            } catch (\Throwable $e) {
                // Stays `received`: payments:reconcile sends it again.
                Log::error('Could not queue payment webhook', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * The tenant whose secret must have signed this delivery: the payment's
     * tenant when the session / reference is known (the URL's must match),
     * else the URL's.
     *
     * @return array{0: ?Tenant, 1: ?string} [tenant, rejection reason]
     */
    private function owner(?string $slug, ?string $sessionId, string $reference): array
    {
        $urlTenant = $slug ? Tenant::where('slug', $slug)->first() : null;

        $paymentTenantId = $sessionId
            ? PaymentGatewaySession::where('gateway', ThawaniGateway::NAME)->where('session_id', $sessionId)->value('tenant_id')
            : null;
        if (! $paymentTenantId && $reference !== '') {
            $paymentTenantId = Payment::withoutTenantScope()->where('transaction_id', preg_replace('/-\d+$/', '', $reference))->value('tenant_id');
        }

        if ($paymentTenantId) {
            if ($slug && (! $urlTenant || $urlTenant->id !== (int) $paymentTenantId)) {
                return [$urlTenant, 'tenant_mismatch'];
            }

            return [Tenant::find($paymentTenantId), null];
        }

        return $urlTenant ? [$urlTenant, null] : [null, 'unknown_tenant'];
    }

    /** Replay guard: a signed timestamp must be recent (seconds or milliseconds). */
    private function freshTimestamp(string $timestamp): bool
    {
        $tolerance = (int) config('payments.thawani.webhook_tolerance_seconds', 600);
        if ($tolerance <= 0 || ! is_numeric($timestamp)) {
            return true;
        }
        $ts = (float) $timestamp;
        if ($ts > 1e12) {
            $ts /= 1000;
        }

        return abs(now()->timestamp - $ts) <= $tolerance;
    }

    private function store(Request $request, string $raw, mixed $body, ?string $sessionId, ?Tenant $owner, ?string $reason): PaymentWebhookEvent
    {
        $hash = hash('sha256', $raw);
        $existing = PaymentWebhookEvent::where('gateway', ThawaniGateway::NAME)->where('payload_hash', $hash)->first();
        if ($existing) {
            $existing->increment('deliveries');
            // An earlier copy was rejected (e.g. stale timestamp); this one verifies.
            if ($existing->status === PaymentWebhookEvent::REJECTED && $reason === null) {
                $existing->forceFill(['status' => PaymentWebhookEvent::RECEIVED, 'signature_valid' => true, 'raw_body' => $raw, 'last_error' => null, 'tenant_id' => $owner?->id])->save();
            }

            return $existing;
        }

        $headers = collect(self::HEADERS)
            ->mapWithKeys(fn ($h) => [$h => $request->header($h)])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        try {
            return PaymentWebhookEvent::create([
                'tenant_id' => $owner?->id,
                'gateway' => ThawaniGateway::NAME,
                'event_type' => is_array($body) ? (mb_substr((string) ($body['event_type'] ?? ''), 0, 80) ?: null) : null,
                'session_id' => $sessionId,
                'payload_hash' => $hash,
                // null = could not be checked (no tenant / no secret).
                'signature_valid' => match ($reason) {
                    null => true,
                    'bad_signature', 'stale_timestamp' => false,
                    default => null,
                },
                'headers' => $headers,
                'raw_body' => $reason === null ? $raw : mb_strcut($raw, 0, self::REJECTED_BODY),
                'status' => $reason === null ? PaymentWebhookEvent::RECEIVED : PaymentWebhookEvent::REJECTED,
                'last_error' => $reason,
                'ip' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same delivery arrived twice at once.
            $event = PaymentWebhookEvent::where('gateway', ThawaniGateway::NAME)->where('payload_hash', $hash)->firstOrFail();
            $event->increment('deliveries');

            return $event;
        }
    }
}
