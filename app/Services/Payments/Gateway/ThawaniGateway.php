<?php

namespace App\Services\Payments\Gateway;

/**
 * Thawani Pay checkout API (docs/payments_thawani.md §2). Amounts are in
 * baisa (OMR minor units). Every call goes through GatewayHttp (retries,
 * circuit breaker, recording).
 *
 *   POST checkout/session            create a session
 *   GET  checkout/session/{id}       payment_status: unpaid | paid | cancelled
 *   POST checkout/{id}/cancel        (not checkout/session/{id}/cancel: 404, checked in UAT)
 *   GET  payments?checkout_invoice=  the payment(s) of a paid session
 *   POST refunds                     {payment_id, reason, metadata}
 *
 * Webhooks: header thawani-signature = hex HMAC-SHA256(raw_body . "-" .
 * thawani-timestamp, webhook secret).
 */
class ThawaniGateway
{
    public const NAME = 'thawani';

    public function __construct(private GatewayHttp $http) {}

    private function request(ThawaniConfig $c, string $operation, string $method, string $path, ?array $body, array $ctx, bool $idempotent = true, array $unprocessed = [425, 429, 503]): GatewayRequest
    {
        if (! $c->isConfigured()) {
            throw GatewayException::notConfigured('Thawani keys are not set for this organizer');
        }

        return new GatewayRequest(
            gateway: self::NAME,
            mode: $c->mode,
            operation: $operation,
            method: $method,
            url: $c->apiUrl($path),
            headers: ['thawani-api-key' => $c->secretKey],
            body: $body,
            tenantId: $ctx['tenant_id'] ?? null,
            paymentId: $ctx['payment_id'] ?? null,
            idempotent: $idempotent,
            unprocessedStatuses: $unprocessed,
        );
    }

    /** @return array session object (session_id, payment_status, invoice, total_amount, expire_at, ...) */
    public function createSession(ThawaniConfig $c, array $payload, array $ctx = [], string $profile = 'interactive'): array
    {
        $data = $this->http->send($this->request($c, 'create_session', 'POST', 'checkout/session', $payload, $ctx, idempotent: false), $profile)->data();
        if (empty($data['session_id'])) {
            throw new GatewayException('Thawani returned no session_id', 'client', json: $data);
        }

        return $data;
    }

    public function getSession(ThawaniConfig $c, string $sessionId, array $ctx = [], string $profile = 'background'): array
    {
        $data = $this->http->send($this->request($c, 'get_session', 'GET', 'checkout/session/'.rawurlencode($sessionId), null, $ctx), $profile)->data();

        // Some responses wrap the session in a list.
        return isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
    }

    public function cancelSession(ThawaniConfig $c, string $sessionId, array $ctx = [], string $profile = 'background'): array
    {
        return $this->http->send($this->request($c, 'cancel_session', 'POST', 'checkout/'.rawurlencode($sessionId).'/cancel', null, $ctx), $profile)->data();
    }

    /** @return array<int, array> payments of a session's invoice (payment_id, status, amount, masked_card, ...) */
    public function paymentsForInvoice(ThawaniConfig $c, string $invoice, array $ctx = [], string $profile = 'background'): array
    {
        $data = $this->http->send($this->request($c, 'get_payments', 'GET', 'payments?checkout_invoice='.rawurlencode($invoice), null, $ctx), $profile)->data();

        return array_is_list($data) ? $data : [$data];
    }

    public function createRefund(ThawaniConfig $c, string $paymentId, string $reason, array $metadata, array $ctx = []): array
    {
        return $this->http->send($this->request($c, 'create_refund', 'POST', 'refunds', [
            'payment_id' => $paymentId,
            'reason' => $reason,
            'metadata' => $metadata,
        // Money out: only 429 is taken as "not processed" (503 is unconfirmed for Thawani).
        ], $ctx, idempotent: false, unprocessed: [425, 429]), 'background')->data();
    }

    /** Read-only key check for the admin panel: any answer other than 401/403 means the key was accepted. */
    public function ping(ThawaniConfig $c, array $ctx = []): array
    {
        try {
            $this->getSession($c, 'connection-check', $ctx, 'interactive');

            return ['ok' => true, 'status' => 200, 'message' => 'OK'];
        } catch (GatewayException $e) {
            $authFailed = in_array($e->status, [401, 403], true);

            return [
                'ok' => $e->kind === 'client' && ! $authFailed,
                'status' => $e->status,
                'message' => $e->getMessage(),
                'kind' => $e->kind,
            ];
        }
    }

    /**
     * true / false = signature checked; null = no webhook secret configured.
     */
    public static function verifySignature(ThawaniConfig $c, string $rawBody, string $timestamp, string $signature): ?bool
    {
        if ($c->webhookSecret === '') {
            return null;
        }
        if ($timestamp === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody.'-'.$timestamp, $c->webhookSecret);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    /** Thawani payment_status → our session status. */
    public static function sessionStatus(array $session): string
    {
        return match (strtolower((string) ($session['payment_status'] ?? ''))) {
            'paid' => 'paid',
            'cancelled', 'canceled' => 'cancelled',
            'expired' => 'expired',
            default => 'open',
        };
    }
}
