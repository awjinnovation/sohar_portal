<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\Tenant;
use App\Services\Payments\Gateway\AmwalConfig;
use App\Services\Payments\Gateway\AmwalGateway;
use App\Services\Payments\Gateway\GatewayException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Amwal Pay SmartBox flow (docs/payments_amwal.md):
 *
 *  1. start(): the signed payment link renders our page, which loads
 *     SmartBox.js with a server-signed configuration. Every page open is a
 *     new attempt: a merchant reference `{transaction_id}_{n}`, stored as a
 *     payment_gateway_sessions row (gateway amwal, session_id = reference).
 *  2. Evidence that a reference was paid: the Amwal API (POST
 *     Transaction/GetByMerchantReference — used by return pages, app polls,
 *     the reconciler and webhooks), or a payload signed with the merchant's
 *     secure key (the SmartBox completeCallback relayed by the browser, the
 *     cloud notification). Either is applied through PaymentFulfillmentService
 *     (idempotent, row-locked) after the amount check. One check at a time
 *     per payment (cache lock).
 *  3. Amwal cannot cancel or expire a SmartBox attempt, so attempts stay
 *     `open` (and are watched for a late capture) until
 *     payments.amwal.session_ttl_minutes after they were opened.
 *  4. refund(): full void (same day) or refund through Execute/Void |
 *     Execute/Refund, chosen from the transaction's transactionActions.
 */
class AmwalCheckout
{
    public function __construct(
        private AmwalGateway $api,
        private PaymentFulfillmentService $fulfillment,
        private PaymentRecorder $recorder,
    ) {}

    public function config(Payment $payment, ?string $mode = null): AmwalConfig
    {
        return AmwalConfig::forTenant(Tenant::findOrFail($payment->tenant_id))->withMode($mode);
    }

    private function ctx(Payment $payment): array
    {
        return ['tenant_id' => $payment->tenant_id, 'payment_id' => $payment->id];
    }

    // ---------------------------------------------------------------- 1. checkout

    /**
     * A new SmartBox attempt for the payment page.
     *
     * @return array{config: array, script: string, reference: string}
     */
    public function start(Payment $payment, string $locale, string $source = 'redirect'): array
    {
        $c = $this->startable($payment);
        $config = null;
        $row = $this->openAttempt($payment, $c, $source, $c->smartBoxUrl(), function (string $reference) use ($c, $payment, $locale, &$config) {
            $config = AmwalGateway::smartBoxConfig($c, $reference, (int) $payment->amount_minor, $locale);

            return Arr::except($config, ['SecureHash']);
        });

        return ['config' => $config, 'script' => $c->smartBoxUrl(), 'reference' => $row->session_id];
    }

    /**
     * A new attempt paid in the app through Amwal's Flutter SDK (tenant
     * setting payments.amwal.app_flow = sdk; POST /payments/amwal/sdk-session):
     * an SDK session token from Amwal, then an attempt exactly like a
     * SmartBox page open (merchant reference {tx}_{n}, same TTL, the same
     * reconcile / late- and double-capture watch, notifications, cancel and
     * refunds). The SDK's own result goes to the app only and is never
     * trusted: completion still comes from the Amwal API or a signed
     * notification.
     *
     * @return array{environment: string, session_token: string, merchant_id: string, terminal_id: string, currency: string,
     *               amount: string, merchant_reference: string, transaction_id: string, locale: string}
     *
     * @throws GatewayException not configured, or Amwal gave no token
     */
    public function startSdk(Payment $payment, string $locale, string $source = 'api'): array
    {
        $c = $this->startable($payment);
        // Token first: no attempt is opened when Amwal refuses.
        $token = $this->api->sdkSessionToken($c, $this->ctx($payment), 'interactive');
        $sdkTransactionId = (string) Str::uuid();
        $amount = AmwalGateway::amount((int) $payment->amount_minor);
        // checkout_url (not null): where the attempt came from — the token endpoint, not a page.
        $row = $this->openAttempt($payment, $c, $source, $c->apiUrl('Membership/GetSDKSessionToken'), fn () => [
            'flow' => 'sdk', 'sdk_transaction_id' => $sdkTransactionId, 'amount' => $amount, 'locale' => $locale,
        ], ['flow' => 'sdk']);

        return [
            'environment' => $c->mode === 'production' ? 'PROD' : 'UAT',
            'session_token' => $token,
            'merchant_id' => $c->merchantId,
            'terminal_id' => $c->terminalId,
            'currency' => 'OMR',
            'amount' => $amount,
            'merchant_reference' => $row->session_id,
            'transaction_id' => $sdkTransactionId,
            'locale' => $locale,
        ];
    }

    private function startable(Payment $payment): AmwalConfig
    {
        $c = $this->config($payment);
        if (! $c->isConfigured()) {
            throw GatewayException::notConfigured('Amwal merchant ID, terminal ID and secure key are not set for this organizer');
        }
        if (strtoupper((string) $payment->currency) !== 'OMR') {
            throw GatewayException::notConfigured('Amwal SmartBox accepts OMR only');
        }

        return $c;
    }

    /**
     * Opens attempt n+1 of the payment (one at a time per payment):
     * merchant reference {transaction_id}_{n}, watched until
     * payments.amwal.session_ttl_minutes. $data(reference) = what the
     * attempt row keeps.
     */
    private function openAttempt(Payment $payment, AmwalConfig $c, string $source, string $checkoutUrl, \Closure $data, array $event = []): PaymentGatewaySession
    {
        return Cache::lock("payments:session:{$payment->id}", 30)->block(15, function () use ($payment, $c, $source, $checkoutUrl, $data, $event) {
            $seq = PaymentGatewaySession::where('payment_id', $payment->id)->count() + 1;
            $reference = $payment->transaction_id.'_'.$seq;

            $row = PaymentGatewaySession::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'gateway' => AmwalGateway::NAME,
                'mode' => $c->mode,
                'session_id' => $reference,
                'client_reference_id' => $reference,
                'status' => PaymentGatewaySession::OPEN,
                'amount_minor' => (int) $payment->amount_minor,
                'currency' => 'OMR',
                'checkout_url' => $checkoutUrl,
                'expires_at' => now()->addMinutes(max(5, (int) config('payments.amwal.session_ttl_minutes', 120))),
                'data' => $data($reference),
            ]);

            $payment->forceFill([
                'gateway_session_id' => $row->session_id,
                'next_check_at' => $payment->next_check_at ?? $this->nextCheckAt($payment),
            ])->save();

            $this->recorder->record($payment, 'session_created', $source, [
                'session_id' => $row->session_id,
                'mode' => $c->mode,
                'amount_minor' => $row->amount_minor,
                'expires_at' => $row->expires_at?->toIso8601String(),
            ] + $event);

            return $row;
        });
    }

    /**
     * Token on our return / cancel URLs (the SmartBox callbacks navigate
     * there), so only the page we rendered can cancel a payment.
     */
    public static function returnToken(string $transactionId): string
    {
        return substr(hash_hmac('sha256', 'amwal-return|'.$transactionId, (string) config('app.key')), 0, 32);
    }

    // ---------------------------------------------------------------- 2. reconcile

    /**
     * Ask Amwal about the payment's attempts and apply the answer (same
     * contract as ThawaniCheckout::reconcile()).
     *
     * @throws GatewayException when Amwal cannot be reached (callers retry)
     * @throws LockTimeoutException background caller while another check holds the lock
     */
    public function reconcile(Payment $payment, string $source, string $profile = 'background', bool $final = false, ?string $sessionId = null): Payment
    {
        return $this->locked($payment, $profile, fn () => $this->reconcileLocked($payment, $source, $profile, $final, $sessionId));
    }

    private function locked(Payment $payment, string $profile, \Closure $fn): Payment
    {
        $lock = Cache::lock("payments:reconcile:{$payment->id}", 120);
        if (! $lock->get()) {
            if ($profile === 'interactive') {
                return Payment::withoutTenantScope()->findOrFail($payment->id);
            }
            $lock->block(30);
        }

        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }

    private function reconcileLocked(Payment $payment, string $source, string $profile, bool $final, ?string $sessionId): Payment
    {
        $payment = Payment::withoutTenantScope()->findOrFail($payment->id);
        $fulfilled = $payment->fulfilled_at || in_array($payment->status, ['completed', 'refunded'], true);
        $scheduled = ! in_array($source, ['poll', 'redirect'], true);

        $sessions = PaymentGatewaySession::where('payment_id', $payment->id)
            ->where('gateway', AmwalGateway::NAME)
            ->where(function ($q) use ($sessionId) {
                $q->whereIn('status', [PaymentGatewaySession::OPEN, PaymentGatewaySession::PAID]);
                if ($sessionId) {
                    $q->orWhere('session_id', $sessionId);
                }
            })
            ->latest('id')
            ->get();

        if ($fulfilled) {
            $sessions = $sessions->filter(fn (PaymentGatewaySession $r) => $r->session_id !== $payment->gateway_session_id && $r->status === PaymentGatewaySession::OPEN);
            if ($sessions->isEmpty()) {
                return $payment;
            }
        } elseif ($payment->isPending() && $scheduled) {
            $payment->forceFill(['next_check_at' => $this->nextCheckAt($payment), 'check_count' => $payment->check_count + 1])->save();
        }

        $seen = [];
        $unverifiable = [];
        foreach ($sessions as $row) {
            $c = $this->config($payment, $row->mode);
            try {
                $transactions = $this->api->transactionsByReference($c, $row->session_id, $this->ctx($payment), $profile);
            } catch (GatewayException $e) {
                if ($e->kind !== 'client') {
                    throw $e;
                }
                $unverifiable[$row->session_id] = $row->mode;
                $seen[$row->session_id] = 'unverifiable';
                $row->forceFill(['last_checked_at' => now(), 'data' => array_merge($row->data ?? [], ['check_error' => $e->getMessage(), 'check_status' => $e->status])])->save();

                continue;
            }

            $approved = AmwalGateway::approved($transactions);
            $status = match (true) {
                $row->status === PaymentGatewaySession::PAID, $approved !== null => PaymentGatewaySession::PAID,
                $row->expires_at?->isPast() ?? false => PaymentGatewaySession::EXPIRED,
                default => PaymentGatewaySession::OPEN,
            };
            $row->fill([
                'status' => $status,
                'last_checked_at' => now(),
                'data' => array_merge($row->data ?? [], ['transactions' => array_map(fn ($t) => $this->summary($t), array_slice($transactions, 0, 10))]),
            ])->save();
            $seen[$row->session_id] = $status;

            if ($status === PaymentGatewaySession::PAID && $approved) {
                if ($fulfilled) {
                    $this->duplicateCapture($payment, $row, $source);

                    continue;
                }

                return $this->applyPaid($payment, $row, [
                    'amount_minor' => AmwalGateway::minor($approved['amount'] ?? null),
                    'currency_id' => (int) ($approved['currencyId'] ?? AmwalGateway::CURRENCY_OMR),
                    'transaction_id' => (string) ($approved['id'] ?? ''),
                    'data' => $this->summary($approved),
                    'via' => 'api',
                ], $c, $source);
            }
        }

        if ($fulfilled) {
            return $payment->fresh();
        }

        $payment->last_checked_at = now();
        if ($source !== 'poll') {
            $this->recorder->record($payment, 'gateway_checked', $source, array_filter(['sessions' => $seen ?: null, 'final' => $final ?: null]));
        }

        $allClosed = $seen && collect($seen)->every(fn ($s) => in_array($s, [PaymentGatewaySession::CANCELLED, PaymentGatewaySession::EXPIRED], true));
        if ($payment->isPending() && ($final || $allClosed)) {
            if (in_array('production', $unverifiable, true)) {
                $payment->forceFill(['needs_review' => true, 'review_reason' => 'session_unverifiable']);
            }
            $payment->save();

            // Attempts stay `open` (Amwal cannot cancel them): the late-capture sweep keeps watching them until they expire.
            return $this->fulfillment->fail($payment, 'cancelled', [], $source, array_filter([
                'reason' => $allClosed ? 'gateway_session_closed' : 'not_paid_in_time',
                'sessions' => $seen ?: null,
            ]));
        }

        if (! $payment->isPending()) {
            $payment->next_check_at = null;
        }
        $payment->save();

        return $payment;
    }

    /**
     * A payload signed with the merchant's secure key says a reference was
     * approved (completeCallback or cloud notification). Applied like an API
     * answer; anything not approved falls back to asking the API.
     *
     * Applied at once, without the check lock: a signed approval needs no
     * gateway call, and fulfilment is row-locked and idempotent, so the
     * visitor's return never waits behind the notification (or a scheduled
     * check) asking Amwal about the same payment.
     *
     * @param  array{reference: string, approved: bool, amount: mixed, currency_id: mixed, transaction_id: ?string, data: array}  $evidence
     */
    public function applySigned(Payment $payment, array $evidence, string $source, string $profile): Payment
    {
        $row = PaymentGatewaySession::where('payment_id', $payment->id)
            ->where('gateway', AmwalGateway::NAME)
            ->where('session_id', $evidence['reference'])
            ->first();

        if (! $row || ! $evidence['approved']) {
            return $this->reconcile($payment, $source, $profile, false, $row?->session_id);
        }

        $payment = Payment::withoutTenantScope()->findOrFail($payment->id);
        $row->forceFill(['status' => PaymentGatewaySession::PAID, 'last_checked_at' => now()])->save();

        if ($payment->fulfilled_at || in_array($payment->status, ['completed', 'refunded'], true)) {
            if ($row->session_id !== $payment->gateway_session_id) {
                $this->duplicateCapture($payment, $row, $source);
            }

            return $payment;
        }

        return $this->applyPaid($payment, $row, [
            // "0.5" (OMR) and "500" (baisa) are the same amount; any other value is flagged by applyPaid().
            'amount_minor' => AmwalGateway::amountMatches($evidence['amount'], (int) $payment->amount_minor)
                ? (int) $payment->amount_minor
                : AmwalGateway::minor($evidence['amount']),
            'currency_id' => (int) ($evidence['currency_id'] ?: AmwalGateway::CURRENCY_OMR),
            'transaction_id' => $evidence['transaction_id'] ?: null,
            'data' => $evidence['data'],
            'via' => $source,
        ], $this->config($payment, $row->mode), $source);
    }

    private function applyPaid(Payment $payment, PaymentGatewaySession $row, array $paid, AmwalConfig $c, string $source): Payment
    {
        if ($paid['amount_minor'] !== (int) $payment->amount_minor || $paid['currency_id'] !== AmwalGateway::CURRENCY_OMR) {
            $payment->forceFill(['needs_review' => true, 'review_reason' => 'amount_mismatch', 'next_check_at' => null])->save();
            $this->recorder->record($payment, 'amount_mismatch', $source, [
                'session_id' => $row->session_id, 'gateway_amount_minor' => $paid['amount_minor'], 'gateway_currency_id' => $paid['currency_id'],
                'expected_minor' => (int) $payment->amount_minor,
            ]);
            Log::critical('Amwal paid amount does not match the payment', ['transaction_id' => $payment->transaction_id, 'reference' => $row->session_id]);

            return $payment;
        }

        if (! $row->paid_at) {
            $row->forceFill(['paid_at' => now(), 'status' => PaymentGatewaySession::PAID])->save();
        }

        $payment->forceFill([
            'gateway_session_id' => $row->session_id,
            // The transaction GUID (refunds); looked up again by reference at refund time if missing.
            'gateway_payment_id' => $paid['transaction_id'] ?: $payment->gateway_payment_id,
        ])->save();

        return $this->fulfillment->complete($payment, [
            'gateway' => AmwalGateway::NAME,
            'mode' => $c->mode,
            'merchant_reference' => $row->session_id,
            'confirmed_by' => $paid['via'],
            'transaction' => $paid['data'],
        ], 'card', $source);
    }

    /** What we keep of an Amwal transaction (no card or customer data). */
    private function summary(array $t): array
    {
        return Arr::only($t, ['id', 'idN', 'amount', 'currencyId', 'currency', 'responseCode', 'responseCodeName', 'transactionType',
            'merchantReference', 'gatewayTransactionReference', 'isRefunded', 'isCaptured', 'transactionActions', 'transactionTime',
            'transactionId', 'SystemReference', 'PaidThrough', 'Message', 'AuthorizationDateTime', 'ResponseCode', 'Amount']);
    }

    private function duplicateCapture(Payment $payment, PaymentGatewaySession $row, string $source): void
    {
        $payment->forceFill(['needs_review' => true, 'review_reason' => 'duplicate_capture'])->save();
        $row->forceFill(['paid_at' => $row->paid_at ?? now(), 'status' => PaymentGatewaySession::PAID])->save();
        $this->recorder->record($payment, 'duplicate_capture', $source, [
            'session_id' => $row->session_id, 'amount_minor' => $row->amount_minor, 'paid_session_id' => $payment->gateway_session_id,
        ]);
        Log::critical('Amwal: a second attempt of an already paid payment was paid', ['transaction_id' => $payment->transaction_id, 'reference' => $row->session_id]);
    }

    /**
     * The visitor cancels (in the app, or SmartBox's cancel): Amwal is asked
     * first (paid after all → completed), then the payment is cancelled. The
     * attempts stay watched for a late capture.
     */
    public function cancel(Payment $payment, string $source, string $reason): Payment
    {
        return Cache::lock("payments:reconcile:{$payment->id}", 120)->block(15, function () use ($payment, $source, $reason) {
            try {
                $payment = $this->reconcileLocked($payment, $source, 'interactive', false, null);
            } catch (GatewayException $e) {
                Log::info('Amwal check before cancel failed', ['transaction_id' => $payment->transaction_id, 'error' => $e->getMessage()]);
                $payment = Payment::withoutTenantScope()->findOrFail($payment->id);
            }
            if (! $payment->isPending()) {
                return $payment;
            }

            return $this->fulfillment->fail($payment, 'cancelled', [], $source, ['reason' => $reason]);
        });
    }

    public function nextCheckAt(Payment $payment): Carbon
    {
        $schedule = array_values((array) config('payments.reconcile.schedule_minutes', [1, 2, 5, 10, 30, 60]));
        $minutes = (int) ($schedule[min((int) $payment->check_count, count($schedule) - 1)] ?? 5);

        return now()->addMinutes(max(1, $minutes));
    }

    /** App polls and return pages: check Amwal now (throttled per payment). Never throws. */
    public function checkIfStale(Payment $payment, string $source = 'poll'): Payment
    {
        if ($payment->gateway !== AmwalGateway::NAME || ! $payment->isPending() || ! $payment->gateway_session_id) {
            return $payment;
        }
        if (! Cache::add("payments:poll:{$payment->id}", 1, (int) config('payments.reconcile.poll_check_seconds', 8))) {
            return $payment;
        }

        try {
            return $this->reconcile($payment, $source, 'interactive');
        } catch (\Throwable $e) {
            Log::info('Amwal status check failed', ['transaction_id' => $payment->transaction_id, 'source' => $source, 'error' => $e->getMessage()]);

            return $payment;
        }
    }

    // ---------------------------------------------------------------- return page

    /**
     * SmartBox completeCallback relayed by our page (query parameters). A
     * valid hash with response code 00 completes the payment at once (no
     * gateway call, no waiting); otherwise the API is asked. Never throws.
     */
    public function handleReturn(Payment $payment, array $params): Payment
    {
        $reference = (string) ($params['merchantReference'] ?? '');
        $c = $this->config($payment);
        $valid = AmwalGateway::verifyCallback($c, $params);
        $this->recorder->record($payment, 'callback_received', 'redirect', array_filter([
            'reference' => $reference ?: null,
            'response_code' => (string) ($params['responseCode'] ?? '') ?: null,
            'signature_valid' => $valid,
        ], fn ($v) => $v !== null));

        try {
            if ($valid && $reference !== '' && $this->belongsTo($payment, $reference)) {
                return $this->applySigned($payment, [
                    'reference' => $reference,
                    'approved' => (string) ($params['responseCode'] ?? '') === AmwalGateway::APPROVED,
                    'amount' => $params['amount'] ?? null,
                    'currency_id' => $params['currencyId'] ?? null,
                    'transaction_id' => isset($params['transactionId']) ? mb_substr((string) $params['transactionId'], 0, 120) : null,
                    'data' => Arr::only($params, ['amount', 'currencyId', 'merchantReference', 'responseCode', 'transactionId', 'transactionTime']),
                ], 'redirect', 'interactive');
            }

            // No signed result: ask Amwal now (GatewayCheckouts::checkOnReturn()).
            return GatewayCheckouts::checkOnReturn($payment, (int) config('payments.reconcile.return_wait_seconds', 10));
        } catch (\Throwable $e) {
            Log::info('Amwal return check failed', ['transaction_id' => $payment->transaction_id, 'error' => $e->getMessage()]);

            return Payment::withoutTenantScope()->findOrFail($payment->id);
        }
    }

    private function belongsTo(Payment $payment, string $reference): bool
    {
        return PaymentGatewaySession::where('payment_id', $payment->id)->where('gateway', AmwalGateway::NAME)->where('session_id', $reference)->exists();
    }

    // ---------------------------------------------------------------- webhooks

    /** Applies a stored cloud notification (ProcessPaymentWebhook). Throws to be retried. */
    public function handleWebhook(PaymentWebhookEvent $event): void
    {
        $body = $event->payload();
        $reference = (string) ($body['MerchantReference'] ?? '');
        $row = $reference !== '' ? PaymentGatewaySession::where('gateway', AmwalGateway::NAME)->where('session_id', $reference)->first() : null;
        $payment = $row ? Payment::withoutTenantScope()->find($row->payment_id) : null;
        if (! $payment) {
            $event->forceFill(['status' => PaymentWebhookEvent::IGNORED, 'last_error' => 'No matching payment', 'processed_at' => now()])->save();

            return;
        }

        $event->forceFill(['payment_id' => $payment->id, 'tenant_id' => $payment->tenant_id])->save();
        $this->recorder->record($payment, 'webhook_received', 'webhook', [
            'webhook_event_id' => $event->id,
            'event_type' => $event->event_type,
            'response_code' => (string) ($body['ResponseCode'] ?? ''),
            'signature_valid' => $event->signature_valid,
        ]);

        $type = strtolower((string) ($body['TxnType'] ?? 'purchase'));
        if ($type !== '' && $type !== 'purchase' && $type !== 'sale') {
            // Refund / void confirmations are recorded; the refund was settled by the API call.
            $event->forceFill(['status' => PaymentWebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null])->save();

            return;
        }

        // Signature checked when it was stored (PaymentWebhookController::amwal).
        $approved = $event->signature_valid === true && (string) ($body['ResponseCode'] ?? '') === AmwalGateway::APPROVED;
        if ($approved && ! AmwalGateway::amountMatches($body['Amount'] ?? null, (int) $payment->amount_minor)) {
            // A real difference (not OMR vs baisa): the API decides first; only when it
            // does not settle the payment is the notification's amount flagged as before.
            $this->recorder->record($payment, 'notification_amount_differs', 'webhook', [
                'reference' => $reference, 'amount' => is_scalar($body['Amount'] ?? null) ? (string) $body['Amount'] : null,
                'expected_minor' => (int) $payment->amount_minor,
            ]);
            $checked = $this->reconcile($payment, 'webhook', 'background', false, $reference);
            if (! $checked->isPending() || $checked->needs_review) {
                $event->forceFill(['status' => PaymentWebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null])->save();

                return;
            }
        }
        $this->applySigned($payment, [
            'reference' => $reference,
            'approved' => $approved,
            'amount' => $body['Amount'] ?? null,
            'currency_id' => $body['CurrencyId'] ?? null,
            // SystemReference is Amwal's transaction id, not necessarily the GUID refunds need (looked up then).
            'transaction_id' => null,
            'data' => $this->summary($body),
        ], 'webhook', 'background');

        $event->forceFill(['status' => PaymentWebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null])->save();
    }

    // ---------------------------------------------------------------- refunds

    /**
     * Full void (same day) or refund at Amwal (ProcessGatewayRefund); same
     * safety rules as ThawaniCheckout::refund(): an unclear answer goes to
     * needs_review and is never resent on its own.
     */
    public function refund(PaymentRefund $refund): PaymentRefund
    {
        $refundId = $refund->id;
        $refund = DB::transaction(function () use ($refundId) {
            $locked = PaymentRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [PaymentRefund::PENDING, PaymentRefund::PROCESSING], true)) {
                return null;
            }
            if ($locked->data['in_flight'] ?? false) {
                $this->refundNeedsReview($locked, 'A previous attempt stopped while the request was out; check the Amwal merchant portal before retrying');

                return null;
            }
            $locked->forceFill(['status' => PaymentRefund::PROCESSING, 'attempts' => $locked->attempts + 1])->save();

            return $locked;
        });
        if (! $refund) {
            return PaymentRefund::findOrFail($refundId);
        }

        $payment = Payment::withoutTenantScope()->findOrFail($refund->payment_id);

        try {
            $row = $this->paidSession($payment);
            if (! $row) {
                throw new GatewayException('No paid Amwal attempt found for this payment', 'client');
            }
            $c = $this->config($payment, $row->mode);
            $tx = AmwalGateway::approved($this->api->transactionsByReference($c, $row->session_id, $this->ctx($payment)));
            if (! $tx || empty($tx['id'])) {
                throw new GatewayException('Amwal has no approved transaction for '.$row->session_id, 'server');
            }
            if ($tx['isRefunded'] ?? false) {
                throw new GatewayException('Amwal shows this transaction as refunded already', 'client');
            }
            $actions = (array) ($tx['transactionActions'] ?? []);
            $operation = ($actions['canVoid'] ?? false) ? 'void' : (($actions['canRefund'] ?? true) ? 'refund' : null);
            if (! $operation) {
                throw new GatewayException('Amwal allows neither a void nor a refund for this transaction now', 'client');
            }

            $refund->forceFill([
                'gateway_payment_id' => (string) $tx['id'],
                'data' => array_merge($refund->data ?? [], ['in_flight' => true, 'operation' => $operation]),
            ])->save();

            $data = $this->api->reverse($c, $operation, (string) $tx['id'], (int) $refund->amount_minor, $this->ctx($payment));
        } catch (GatewayException $e) {
            $status = match (true) {
                $e->ambiguous => PaymentRefund::NEEDS_REVIEW,
                $e->retryable() => PaymentRefund::PENDING,
                default => PaymentRefund::FAILED,
            };
            $refund->forceFill([
                'status' => $status,
                'last_error' => $e->getMessage(),
                'data' => array_merge($refund->data ?? [], ['in_flight' => false, 'last_response' => $e->json]),
            ])->save();
            $this->recorder->record($payment, 'gateway_refund_'.($status === PaymentRefund::PENDING ? 'retry' : $status), 'system', [
                'refund_id' => $refund->id, 'error' => $e->getMessage(), 'attempt' => $refund->attempts,
            ]);
            if ($status !== PaymentRefund::PENDING) {
                $payment->forceFill(['needs_review' => true, 'review_reason' => 'refund_'.$status])->save();
            }
            if ($status === PaymentRefund::PENDING) {
                throw $e;
            }

            return $refund;
        }

        $refund->forceFill([
            'status' => PaymentRefund::SUCCEEDED,
            'gateway_refund_id' => isset($data['transactionId']) ? (string) $data['transactionId'] : null,
            'last_error' => null,
            'completed_at' => now(),
            'data' => array_merge($refund->data ?? [], ['in_flight' => false, 'response' => Arr::except($data, ['customerId', 'customerTokenId'])]),
        ])->save();
        $this->recorder->record($payment, 'gateway_refund_succeeded', 'system', [
            'refund_id' => $refund->id, 'gateway_refund_id' => $refund->gateway_refund_id, 'amount_minor' => $refund->amount_minor,
            'operation' => $refund->data['operation'] ?? null,
        ]);

        return $refund;
    }

    public function refundNeedsReview(PaymentRefund $refund, string $why): void
    {
        $refund->forceFill([
            'status' => PaymentRefund::NEEDS_REVIEW,
            'last_error' => $why,
            'data' => array_merge($refund->data ?? [], ['in_flight' => false]),
        ])->save();
        $payment = Payment::withoutTenantScope()->find($refund->payment_id);
        if ($payment) {
            $payment->forceFill(['needs_review' => true, 'review_reason' => 'refund_needs_review'])->save();
            $this->recorder->record($payment, 'gateway_refund_needs_review', 'system', ['refund_id' => $refund->id, 'error' => $why]);
        }
    }

    private function paidSession(Payment $payment): ?PaymentGatewaySession
    {
        return PaymentGatewaySession::where('payment_id', $payment->id)
            ->where('gateway', AmwalGateway::NAME)
            ->when($payment->gateway_session_id, fn ($q) => $q->where('session_id', $payment->gateway_session_id), fn ($q) => $q->where('status', PaymentGatewaySession::PAID))
            ->latest('id')
            ->first();
    }
}
