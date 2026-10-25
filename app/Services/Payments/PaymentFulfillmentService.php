<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single, idempotent completion path for every payment. Safe under the
 * gateway webhook and the browser redirect arriving concurrently: the
 * payment row is locked and `fulfilled_at` guards side effects (tickets,
 * wallet credit, deposits, points) so they run exactly once.
 */
class PaymentFulfillmentService
{
    public function __construct(
        private TenantContext $tenants,
        private TicketIssuer $issuer,
        private WalletService $wallets,
        private PointsService $points,
        private DepositService $deposits,
    ) {}

    public function complete(Payment $payment, array $gatewayResponse = [], ?string $paymentMode = null): Payment
    {
        $tenant = Tenant::findOrFail($payment->tenant_id);

        return $this->tenants->run($tenant, fn () => DB::transaction(function () use ($payment, $tenant, $gatewayResponse, $paymentMode) {
            /** @var Payment $locked */
            $locked = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->fulfilled_at) {
                return $locked;
            }

            if (in_array($locked->status, ['failed', 'cancelled'], true)) {
                // Money was captured after we gave up on it (late webhook).
                Log::warning('Completing a payment previously marked '.$locked->status, ['transaction_id' => $locked->transaction_id]);
            }

            $locked->status = 'completed';
            $locked->paid_at ??= now();
            if ($gatewayResponse) {
                $locked->bank_muscat_response = $gatewayResponse;
            }
            $locked->payment_method = $paymentMode ?? $locked->payment_method ?? ($locked->method === Payment::METHOD_WALLET ? 'wallet' : 'card');
            $locked->save();

            match ($locked->payment_type) {
                Payment::TYPE_TOPUP => $this->fulfillTopup($locked, $tenant),
                default => $this->fulfillTicket($locked, $tenant),
            };

            $locked->fulfilled_at = now();
            $locked->save();

            return $locked;
        }));
    }

    /**
     * Mark a pending payment failed/cancelled (verified gateway response or
     * our own expiry job). Gives back points reserved for the checkout.
     */
    public function fail(Payment $payment, string $status = 'failed', array $gatewayResponse = []): Payment
    {
        $tenant = Tenant::findOrFail($payment->tenant_id);

        return $this->tenants->run($tenant, fn () => DB::transaction(function () use ($payment, $tenant, $status, $gatewayResponse) {
            $locked = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->fulfilled_at || ! in_array($locked->status, ['pending', 'processing'], true)) {
                return $locked;
            }

            $locked->status = $status;
            if ($gatewayResponse) {
                $locked->bank_muscat_response = $gatewayResponse;
            }
            $locked->save();

            $meta = $locked->metadata ?? [];
            if (($meta['points_used'] ?? 0) > 0 && $locked->user) {
                $this->points->restore($locked->user, $tenant, (int) $meta['points_used'], $locked, "pay:{$locked->id}:points_restore");
            }

            if ($locked->payment_type === Payment::TYPE_TOPUP) {
                WalletTopup::withoutTenantScope()->whereKey($locked->payable_id)->where('status', 'pending')
                    ->update(['status' => $status === 'cancelled' ? 'cancelled' : 'failed']);
            }

            return $locked;
        }));
    }

    protected function fulfillTicket(Payment $payment, Tenant $tenant): void
    {
        $meta = $payment->metadata ?? [];
        $user = User::findOrFail($payment->user_id);

        // Gate box office order paid through its public link. A capture that
        // arrives after the order expired / was cancelled only gets tickets
        // when seats are still free (checked here, under locks); otherwise
        // the order becomes paid_unfulfilled and the payment is refunded.
        $boxOffice = ! empty($meta['box_office_order_id']) ? app(\App\Services\Gate\BoxOfficeService::class) : null;
        if ($boxOffice && ! $boxOffice->reserveForPayment($payment)) {
            return;
        }

        $tickets = $this->issuer->issue($payment);

        // Same transaction, so it happens exactly once with the tickets.
        $boxOffice?->markPaid($payment, $tickets);

        // Refundable per-ticket deposits are kept in the wallet as holds.
        $depositPer = (int) ($meta['deposit_per_ticket_minor'] ?? 0);
        if ($depositPer > 0) {
            $wallet = $this->wallets->walletFor($user, $tenant);
            $event = Event::withoutTenantScope()->find($meta['event_id'] ?? $payment->payable_id);
            $total = $depositPer * $tickets->count();

            if ($payment->method === Payment::METHOD_GATEWAY) {
                // The deposit was paid by card: fund the wallet, then hold it.
                $this->wallets->credit($wallet, $total, 'topup', [
                    'idempotency_key' => "pay:{$payment->id}:deposit_funding",
                    'reference' => $payment,
                    'allow_frozen' => true,
                    'meta' => ['kind' => 'deposit_funding'],
                ]);
            }

            foreach ($tickets->values() as $i => $ticket) {
                $this->deposits->hold($wallet, $meta['deposit_type'] ?? 'wristband', $depositPer, "pay:{$payment->id}:deposit:$i", [
                    'event_id' => $event?->id,
                    'payment_id' => $payment->id,
                    'label' => trim(($event?->title ?? '').' — '.$ticket->ticket_number, ' —'),
                ]);
            }
        }

        $spent = (int) ($meta['tickets_minor'] ?? 0);
        if ($spent > 0) {
            $this->points->earnForSpend($user, $tenant, $spent, $payment, "earn:payment:{$payment->id}");
        }
    }

    protected function fulfillTopup(Payment $payment, Tenant $tenant): void
    {
        $topup = WalletTopup::withoutTenantScope()->lockForUpdate()->findOrFail($payment->payable_id);
        if ($topup->status === 'completed') {
            return;
        }

        $wallet = \App\Models\Wallet::withoutTenantScope()->findOrFail($topup->wallet_id);

        // Money is already captured: never reject the credit here (limits
        // are enforced when the top-up is initiated).
        $tx = $this->wallets->credit($wallet, $topup->amount_minor, 'topup', [
            'idempotency_key' => "topup:{$topup->id}",
            'reference' => $topup,
            'allow_frozen' => true,
            'meta' => ['payment_id' => $payment->id, 'transaction_id' => $payment->transaction_id],
        ]);

        $bonus = $this->topupBonus($tenant, $topup->amount_minor);
        if ($bonus > 0) {
            $this->wallets->credit($wallet, $bonus, 'topup', [
                'bucket' => 'promo',
                'idempotency_key' => "topup:{$topup->id}:bonus",
                'reference' => $topup,
                'allow_frozen' => true,
                'title_key' => 'platform.tx.topup_bonus.title',
                'description_key' => 'platform.tx.topup_bonus.description',
                'meta' => ['kind' => 'bonus'],
            ]);
        }

        $topup->update([
            'status' => 'completed',
            'wallet_transaction_id' => $tx->id,
            'bonus_minor' => $bonus,
            'completed_at' => now(),
        ]);
    }

    /** wallet.topup_bonus = [{min_minor, bonus_percent}] — highest matching tier wins. */
    public function topupBonus(Tenant $tenant, int $amountMinor): int
    {
        $best = collect($tenant->setting('wallet.topup_bonus', []))
            ->filter(fn ($t) => $amountMinor >= (int) ($t['min_minor'] ?? PHP_INT_MAX))
            ->sortByDesc(fn ($t) => (int) $t['min_minor'])
            ->first();

        return $best ? intdiv($amountMinor * (int) round(((float) $best['bonus_percent']) * 100), 10000) : 0;
    }
}
