<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\Tenant;
use App\Services\Payments\Gateway\GatewayException;
use App\Services\Payments\Gateway\ThawaniConfig;
use App\Services\Payments\Gateway\ThawaniGateway;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Thawani Pay checkout flow (docs/payments_thawani.md):
 *
 *  1. checkoutUrl(): the signed payment link opens a Thawani session (an
 *     open one is reused) and the visitor is redirected to Thawani.
 *  2. Thawani is the source of truth: the return pages, webhooks, the app's
 *     status polls and the reconciler all call reconcile(), which asks
 *     Thawani for the session status and applies it through
 *     PaymentFulfillmentService (idempotent, row-locked).
 *     One check at a time per payment (cache lock).
 *  3. Unpaid payments are cancelled after payments.reconcile.give_up_after_minutes,
 *     after one last check; a visitor's cancel cancels the Thawani sessions
 *     too, so they cannot be paid later. A late capture still completes.
 *  4. A second paid session of an already paid payment (double charge) is
 *     flagged for review (duplicate_capture).
 *  5. refund(): card refunds through POST /refunds (ProcessGatewayRefund).
 */
class ThawaniCheckout
{
    public function __construct(
        private ThawaniGateway $api,
        private PaymentFulfillmentService $fulfillment,
        private PaymentRecorder $recorder,
    ) {}

    /** The tenant's Thawani config; $mode = a session's own mode (it keeps the host it was created on). */
    public function config(Payment $payment, ?string $mode = null): ThawaniConfig
    {
        return ThawaniConfig::forTenant(Tenant::findOrFail($payment->tenant_id))->withMode($mode);
    }

    private function ctx(Payment $payment): array
    {
        return ['tenant_id' => $payment->tenant_id, 'payment_id' => $payment->id];
    }

    // ---------------------------------------------------------------- 1. checkout

    /** The Thawani page to send the visitor to (open session reused, else a new one). */
    public function checkoutUrl(Payment $payment, string $source = 'redirect'): string
    {
        $c = $this->config($payment);
        if (! $c->isConfigured()) {
            throw GatewayException::notConfigured('Thawani keys are not set for this organizer');
        }
        if (strtoupper((string) $payment->currency) !== 'OMR') {
            throw GatewayException::notConfigured('Thawani accepts OMR only');
        }

        // One visitor double-tapping the link must not open two sessions.
        return Cache::lock("payments:session:{$payment->id}", 30)->block(15, function () use ($payment, $c, $source) {
            $open = PaymentGatewaySession::where('payment_id', $payment->id)
                ->where('gateway', ThawaniGateway::NAME)
                ->where('mode', $c->mode)
                ->where('status', PaymentGatewaySession::OPEN)
                ->latest('id')
                ->first();

            if ($open && $open->isReusable() && $open->amount_minor === (int) $payment->amount_minor) {
                $this->recorder->record($payment, 'checkout_opened', $source, ['session_id' => $open->session_id, 'reused' => true]);

                return $open->checkout_url;
            }

            // An older session (expiring, other mode or amount) is cancelled
            // first so it cannot be paid as well; if that fails it stays
            // `open` and is still checked (a second capture is flagged).
            $this->cancelOpenSessions($payment, profile: 'interactive');

            $seq = PaymentGatewaySession::where('payment_id', $payment->id)->count() + 1;
            $payload = $this->sessionPayload($payment, $seq);
            $session = $this->api->createSession($c, $payload, $this->ctx($payment), 'interactive');

            $row = PaymentGatewaySession::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'gateway' => ThawaniGateway::NAME,
                'mode' => $c->mode,
                'session_id' => (string) $session['session_id'],
                'client_reference_id' => $payload['client_reference_id'],
                'invoice' => isset($session['invoice']) ? (string) $session['invoice'] : null,
                'status' => ThawaniGateway::sessionStatus($session),
                'amount_minor' => (int) $payment->amount_minor,
                'currency' => strtoupper((string) $payment->currency),
                'checkout_url' => $c->checkoutUrl((string) $session['session_id']),
                'expires_at' => $this->time($session['expire_at'] ?? null),
                'data' => $session,
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
            ]);

            return $row->checkout_url;
        });
    }

    private function sessionPayload(Payment $payment, int $seq): array
    {
        $tx = $payment->transaction_id;

        return [
            'client_reference_id' => "$tx-$seq",
            'mode' => 'payment',
            // One line for the exact amount (points discounts and deposits
            // cannot be expressed as Thawani product lines).
            'products' => [[
                'name' => $this->productName($payment),
                'quantity' => 1,
                'unit_amount' => (int) $payment->amount_minor,
            ]],
            'success_url' => route('payment.success', ['transaction_id' => $tx, 't' => self::returnToken($tx)]),
            'cancel_url' => route('payment.cancel', ['transaction_id' => $tx, 't' => self::returnToken($tx)]),
            'metadata' => [
                'transaction_id' => $tx,
                'payment_id' => (string) $payment->id,
                'payment_type' => (string) $payment->payment_type,
            ],
        ];
    }

    /**
     * Token on the success / cancel URLs: only Thawani's redirect (or the
     * visitor who holds it) may cancel a payment from the cancel page. A
     * query parameter, so parameters the gateway may append don't break it
     * the way a full-URL signature would.
     */
    public static function returnToken(string $transactionId): string
    {
        return substr(hash_hmac('sha256', 'thawani-return|'.$transactionId, (string) config('app.key')), 0, 32);
    }

    private function productName(Payment $payment): string
    {
        if ($payment->payment_type === Payment::TYPE_TOPUP) {
            $name = 'Wallet top-up';
        } else {
            $event = Event::withoutTenantScope()->find($payment->metadata['event_id'] ?? $payment->payable_id);
            $qty = (int) ($payment->metadata['quantity'] ?? 1);
            $title = trim((string) ($event?->title ?: $event?->title_ar ?: 'Tickets'));
            $suffix = $qty > 1 ? " x$qty" : '';
            $max = (int) config('payments.thawani.product_name_max', 40);
            $name = mb_substr($title, 0, max(1, $max - mb_strlen($suffix))).$suffix;
        }

        return mb_substr($name, 0, (int) config('payments.thawani.product_name_max', 40));
    }

    // ---------------------------------------------------------------- 2. reconcile

    /**
     * Ask Thawani about the payment's sessions and apply the answer. One
     * check at a time per payment: an interactive caller that finds another
     * check running gets the current state; a background caller waits (or
     * the job is retried).
     *
     * @param  string  $profile  interactive (someone is waiting) | background
     * @param  bool  $final  give up: an unpaid payment is cancelled
     * @param  string|null  $sessionId  also check this session whatever its local status (webhook)
     *
     * @throws GatewayException when Thawani cannot be reached (callers retry)
     * @throws LockTimeoutException background caller while another check holds the lock
     */
    public function reconcile(Payment $payment, string $source, string $profile = 'background', bool $final = false, ?string $sessionId = null): Payment
    {
        $lock = Cache::lock("payments:reconcile:{$payment->id}", 120);
        if (! $lock->get()) {
            if ($profile === 'interactive') {
                return Payment::withoutTenantScope()->findOrFail($payment->id);
            }
            $lock->block(30);
        }

        try {
            return $this->reconcileLocked($payment, $source, $profile, $final, $sessionId);
        } finally {
            $lock->release();
        }
    }

    private function reconcileLocked(Payment $payment, string $source, string $profile, bool $final, ?string $sessionId): Payment
    {
        $payment = Payment::withoutTenantScope()->findOrFail($payment->id);
        $fulfilled = $payment->fulfilled_at || in_array($payment->status, ['completed', 'refunded'], true);
        // Polls and return pages check out of turn; they don't move the schedule.
        $scheduled = ! in_array($source, ['poll', 'redirect'], true);

        $sessions = PaymentGatewaySession::where('payment_id', $payment->id)
            ->where('gateway', ThawaniGateway::NAME)
            ->where(function ($q) use ($sessionId) {
                $q->whereIn('status', [PaymentGatewaySession::OPEN, PaymentGatewaySession::PAID]);
                if ($sessionId) {
                    $q->orWhere('session_id', $sessionId);
                }
            })
            ->latest('id')
            ->get();

        if ($fulfilled) {
            // Paid already: only another open session matters (a double charge).
            $sessions = $sessions->filter(fn (PaymentGatewaySession $r) => $r->session_id !== $payment->gateway_session_id && $r->status === PaymentGatewaySession::OPEN);
            if ($sessions->isEmpty()) {
                return $payment;
            }
        } elseif ($payment->isPending() && $scheduled) {
            // Moved before calling Thawani: a check that keeps failing goes
            // to the back of the queue instead of blocking the others.
            $payment->forceFill(['next_check_at' => $this->nextCheckAt($payment), 'check_count' => $payment->check_count + 1])->save();
        }

        $seen = [];
        $unverifiable = [];
        foreach ($sessions as $row) {
            $c = $this->config($payment, $row->mode);
            try {
                $data = $this->api->getSession($c, $row->session_id, $this->ctx($payment), $profile);
            } catch (GatewayException $e) {
                if ($e->kind !== 'client') {
                    throw $e;
                }
                // Rejected (unknown session, keys changed since): recorded, and
                // a production one makes the payment need a person at give-up.
                $unverifiable[$row->session_id] = $row->mode;
                $seen[$row->session_id] = 'unverifiable';
                $row->forceFill(['last_checked_at' => now(), 'data' => array_merge($row->data ?? [], ['check_error' => $e->getMessage(), 'check_status' => $e->status])])->save();

                continue;
            }

            $status = $row->status === PaymentGatewaySession::PAID ? PaymentGatewaySession::PAID : ThawaniGateway::sessionStatus($data);
            $expiresAt = $this->time($data['expire_at'] ?? null) ?? $row->expires_at;
            if ($status === PaymentGatewaySession::OPEN && $expiresAt?->isPast()) {
                $status = PaymentGatewaySession::EXPIRED;
            }
            $row->fill([
                'status' => $status,
                'invoice' => isset($data['invoice']) ? (string) $data['invoice'] : $row->invoice,
                'expires_at' => $expiresAt,
                'last_checked_at' => now(),
                'data' => $data,
            ])->save();
            $seen[$row->session_id] = $status;

            if ($status === PaymentGatewaySession::PAID) {
                if ($fulfilled) {
                    $this->duplicateCapture($payment, $row, $source);

                    continue;
                }

                return $this->applyPaid($payment, $row, $data, $c, $source, $profile);
            }
        }

        if ($fulfilled) {
            return $payment->fresh();
        }

        $payment->last_checked_at = now();
        if ($source !== 'poll') { // polls are in payment_gateway_calls already
            $this->recorder->record($payment, 'gateway_checked', $source, array_filter(['sessions' => $seen ?: null, 'final' => $final ?: null]));
        }

        $allClosed = $seen && collect($seen)->every(fn ($s) => in_array($s, [PaymentGatewaySession::CANCELLED, PaymentGatewaySession::EXPIRED], true));
        if ($payment->isPending() && ($final || $allClosed)) {
            if ($final) {
                $this->cancelOpenSessions($payment, profile: $profile);
            }
            if (in_array('production', $unverifiable, true)) {
                $payment->forceFill(['needs_review' => true, 'review_reason' => 'session_unverifiable']);
            }
            $payment->save();

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

    private function applyPaid(Payment $payment, PaymentGatewaySession $row, array $data, ThawaniConfig $c, string $source, string $profile): Payment
    {
        $total = isset($data['total_amount']) ? (int) $data['total_amount'] : null;
        $currency = strtoupper((string) ($data['currency'] ?? $row->currency));
        if ($total !== (int) $payment->amount_minor || $currency !== strtoupper((string) $payment->currency)) {
            // Never fulfil an amount we did not ask for; a person decides.
            $payment->forceFill(['needs_review' => true, 'review_reason' => 'amount_mismatch', 'next_check_at' => null])->save();
            $this->recorder->record($payment, 'amount_mismatch', $source, [
                'session_id' => $row->session_id, 'gateway_total' => $total, 'gateway_currency' => $currency,
                'expected_minor' => (int) $payment->amount_minor,
            ]);
            Log::critical('Thawani paid amount does not match the payment', ['transaction_id' => $payment->transaction_id, 'session_id' => $row->session_id]);

            return $payment;
        }

        if (! $row->paid_at) {
            $row->forceFill(['paid_at' => now()])->save();
        }

        // Thawani's payment id is needed for refunds; looked up again at refund time if this fails.
        $info = $row->invoice ? $this->successfulPayment($c, $row->invoice, $payment, $profile) : [];

        $payment->forceFill([
            'gateway_session_id' => $row->session_id,
            'gateway_payment_id' => isset($info['payment_id']) ? (string) $info['payment_id'] : $payment->gateway_payment_id,
        ])->save();

        $payment = $this->fulfillment->complete($payment, [
            'gateway' => ThawaniGateway::NAME,
            'mode' => $c->mode,
            'session' => Arr::only($data, ['session_id', 'client_reference_id', 'invoice', 'payment_status', 'total_amount', 'currency', 'created_at']),
            'payment' => Arr::only($info, ['payment_id', 'status', 'amount', 'masked_card', 'card_type', 'payment_method', 'created_at']),
        ], 'card', $source);

        // Other open sessions of this payment must not be paid as well.
        $this->cancelOpenSessions($payment, except: $row->session_id, profile: $profile);

        return $payment;
    }

    /** The successful payment of an invoice ([] if none / unreachable — never a failed attempt). */
    private function successfulPayment(ThawaniConfig $c, string $invoice, Payment $payment, string $profile): array
    {
        try {
            $list = $this->api->paymentsForInvoice($c, $invoice, $this->ctx($payment), $profile);
        } catch (GatewayException $e) {
            Log::info('Thawani payment lookup failed; will retry at refund time', ['invoice' => $invoice, 'error' => $e->getMessage()]);

            return [];
        }

        return collect($list)->first(fn ($p) => is_array($p) && in_array(strtolower((string) ($p['status'] ?? '')), ['successful', 'success', 'paid', 'captured'], true)) ?? [];
    }

    private function duplicateCapture(Payment $payment, PaymentGatewaySession $row, string $source): void
    {
        $payment->forceFill(['needs_review' => true, 'review_reason' => 'duplicate_capture'])->save();
        $row->forceFill(['paid_at' => $row->paid_at ?? now()])->save();
        $this->recorder->record($payment, 'duplicate_capture', $source, [
            'session_id' => $row->session_id, 'amount_minor' => $row->amount_minor, 'paid_session_id' => $payment->gateway_session_id,
        ]);
        Log::critical('Thawani: a second session of an already paid payment was paid', ['transaction_id' => $payment->transaction_id, 'session_id' => $row->session_id]);
    }

    /** Best effort; a session that cannot be cancelled stays `open` and keeps being checked. */
    private function cancelOpenSessions(Payment $payment, ?string $except = null, string $profile = 'background'): void
    {
        PaymentGatewaySession::where('payment_id', $payment->id)
            ->where('status', PaymentGatewaySession::OPEN)
            ->when($except, fn ($q) => $q->where('session_id', '!=', $except))
            ->get()
            ->each(function (PaymentGatewaySession $row) use ($payment, $profile) {
                try {
                    $this->api->cancelSession($this->config($payment, $row->mode), $row->session_id, $this->ctx($payment), $profile);
                    $row->forceFill(['status' => PaymentGatewaySession::CANCELLED])->save();
                } catch (GatewayException $e) {
                    Log::info('Thawani session cancel failed', ['session_id' => $row->session_id, 'error' => $e->getMessage()]);
                }
            });
    }

    /**
     * The visitor cancels (in the app, or on Thawani's cancel page): Thawani
     * is asked first (paid after all → completed), its open sessions are
     * cancelled so they cannot be paid later, then the payment is cancelled.
     */
    public function cancel(Payment $payment, string $source, string $reason): Payment
    {
        return Cache::lock("payments:reconcile:{$payment->id}", 120)->block(15, function () use ($payment, $source, $reason) {
            try {
                $payment = $this->reconcileLocked($payment, $source, 'interactive', false, null);
            } catch (GatewayException $e) {
                // Unreachable: cancelled here; an open session is still watched for a late capture.
                Log::info('Thawani check before cancel failed', ['transaction_id' => $payment->transaction_id, 'error' => $e->getMessage()]);
                $payment = Payment::withoutTenantScope()->findOrFail($payment->id);
            }
            if (! $payment->isPending()) {
                return $payment;
            }
            $this->cancelOpenSessions($payment, profile: 'interactive');

            return $this->fulfillment->fail($payment, 'cancelled', [], $source, ['reason' => $reason]);
        });
    }

    public function nextCheckAt(Payment $payment): Carbon
    {
        $schedule = array_values((array) config('payments.reconcile.schedule_minutes', [1, 2, 5, 10, 30, 60]));
        $minutes = (int) ($schedule[min((int) $payment->check_count, count($schedule) - 1)] ?? 5);

        return now()->addMinutes(max(1, $minutes));
    }

    /**
     * App status polls and return pages: check Thawani right away (at most
     * every poll_check_seconds per payment), so the visitor sees the result
     * even before the webhook. Never throws.
     */
    public function checkIfStale(Payment $payment, string $source = 'poll'): Payment
    {
        if ($payment->gateway !== ThawaniGateway::NAME || ! $payment->isPending() || ! $payment->gateway_session_id) {
            return $payment;
        }
        if (! Cache::add("payments:poll:{$payment->id}", 1, (int) config('payments.reconcile.poll_check_seconds', 8))) {
            return $payment;
        }

        try {
            return $this->reconcile($payment, $source, 'interactive');
        } catch (\Throwable $e) {
            Log::info('Thawani status check failed', ['transaction_id' => $payment->transaction_id, 'source' => $source, 'error' => $e->getMessage()]);

            return $payment;
        }
    }

    // ---------------------------------------------------------------- webhooks

    /** Applies a stored webhook (ProcessPaymentWebhook). Throws to be retried. */
    public function handleWebhook(PaymentWebhookEvent $event): void
    {
        $body = $event->payload();
        $type = (string) ($body['event_type'] ?? '');
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $sessionId = (string) ($data['session_id'] ?? '');
        $reference = (string) ($data['client_reference_id'] ?? '');

        $payment = $this->paymentForWebhook($sessionId, $reference);
        if (! $payment) {
            $event->forceFill(['status' => PaymentWebhookEvent::IGNORED, 'last_error' => 'No matching payment', 'processed_at' => now()])->save();

            return;
        }

        $event->forceFill(['payment_id' => $payment->id, 'tenant_id' => $payment->tenant_id])->save();
        $this->recorder->record($payment, 'webhook_received', 'webhook', [
            'webhook_event_id' => $event->id,
            'event_type' => $type,
            'signature_valid' => $event->signature_valid,
        ]);

        if (str_starts_with($type, 'refund')) {
            // Refund confirmations are recorded; the refund itself was settled by the API call.
            $event->forceFill(['status' => PaymentWebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null])->save();

            return;
        }

        // The webhook only says "look": the state comes from the API.
        $this->reconcile($payment, 'webhook', 'background', false, $sessionId ?: null);

        $event->forceFill(['status' => PaymentWebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null])->save();
    }

    private function paymentForWebhook(string $sessionId, string $reference): ?Payment
    {
        if ($sessionId !== '') {
            $row = PaymentGatewaySession::where('gateway', ThawaniGateway::NAME)->where('session_id', $sessionId)->first();
            if ($row) {
                return Payment::withoutTenantScope()->find($row->payment_id);
            }
        }
        if ($reference !== '') {
            // client_reference_id = {transaction_id}-{n}
            $tx = preg_replace('/-\d+$/', '', $reference);

            return Payment::withoutTenantScope()->where('transaction_id', $tx)->where('gateway', ThawaniGateway::NAME)->first();
        }

        return null;
    }

    // ---------------------------------------------------------------- refunds

    /**
     * Sends a refund to Thawani (ProcessGatewayRefund). Throws a retryable
     * GatewayException to be retried by the queue. Anything that may have
     * reached Thawani (an unclear answer, or an attempt that died while the
     * request was out) goes to needs_review and is never resent on its own.
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
                $this->refundNeedsReview($locked, 'A previous attempt stopped while the request was out; check the Thawani portal before retrying');

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
            $gatewayPaymentId = $refund->gateway_payment_id ?: $this->gatewayPaymentId($payment);
            if (! $gatewayPaymentId) {
                throw new GatewayException('Thawani payment id not found for this payment', 'server');
            }
            $c = $this->config($payment, $this->paidSession($payment)?->mode);

            // Marked just before the request: if the worker dies now, the
            // next attempt cannot know whether Thawani received it.
            $refund->forceFill([
                'gateway_payment_id' => $gatewayPaymentId,
                'data' => array_merge($refund->data ?? [], ['in_flight' => true]),
            ])->save();

            $data = $this->api->createRefund($c, $gatewayPaymentId, $refund->reason ?: 'Refund', [
                'refund_id' => (string) $refund->id,
                'transaction_id' => $payment->transaction_id,
            ], $this->ctx($payment));
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
            'gateway_refund_id' => isset($data['refund_id']) ? (string) $data['refund_id'] : null,
            'last_error' => null,
            'completed_at' => now(),
            'data' => array_merge($refund->data ?? [], ['in_flight' => false, 'response' => $data]),
        ])->save();
        $this->recorder->record($payment, 'gateway_refund_succeeded', 'system', [
            'refund_id' => $refund->id, 'gateway_refund_id' => $refund->gateway_refund_id, 'amount_minor' => $refund->amount_minor,
        ]);

        return $refund;
    }

    /** A refund whose outcome is unknown: a person checks the Thawani portal. */
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
            ->when($payment->gateway_session_id, fn ($q) => $q->where('session_id', $payment->gateway_session_id), fn ($q) => $q->where('status', PaymentGatewaySession::PAID))
            ->latest('id')
            ->first();
    }

    private function gatewayPaymentId(Payment $payment): ?string
    {
        if ($payment->gateway_payment_id) {
            return $payment->gateway_payment_id;
        }
        $row = $this->paidSession($payment);
        if (! $row?->invoice) {
            return null;
        }
        $info = $this->successfulPayment($this->config($payment, $row->mode), $row->invoice, $payment, 'background');
        $id = $info['payment_id'] ?? null;
        if ($id) {
            $payment->forceFill(['gateway_payment_id' => (string) $id])->save();
        }

        return $id ? (string) $id : null;
    }

    private function time(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
