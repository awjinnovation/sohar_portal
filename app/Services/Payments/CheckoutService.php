<?php

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ticket checkout (contract §8). The server computes every amount:
 *   subtotal = unit price (event / ticket type) × quantity
 *   points_discount = points_to_redeem × redeem_value_minor_per_point (≤ subtotal)
 *   deposit = event.deposit_minor × quantity (if deposits + wallet modules on)
 *   total = subtotal − points_discount + deposit
 * Client-sent amounts are ignored.
 */
class CheckoutService
{
    public function __construct(
        private WalletService $wallets,
        private PointsService $points,
        private PaymentFulfillmentService $fulfillment,
        private PaymentGatewayManager $gateways,
    ) {}

    /**
     * @return array{event: Event, quantity:int, ticket_type:string, unit_price_minor:int, subtotal_minor:int,
     *               points_to_redeem:int, points_discount_minor:int, deposit_per_ticket_minor:int,
     *               deposit_minor:int, tickets_minor:int, total_minor:int}
     */
    public function quote(Tenant $tenant, int $eventId, string $bookingDate, int $quantity, ?string $ticketType, int $pointsToRedeem = 0, ?User $user = null): array
    {
        $event = Event::where('is_active', true)->find($eventId);
        if (! $event) {
            throw new ApiException('EVENT_NOT_FOUND', 'Event not found or not available', 404);
        }
        if (! $event->canBookForDate($bookingDate, $quantity)) {
            throw new ApiException('SOLD_OUT', 'Not enough tickets available for the selected date', 422, [
                'available' => max(0, $event->getAvailableTicketsForDate($bookingDate)),
            ]);
        }

        $ticketType = $ticketType ?: 'standard';
        $unit = $event->unitPriceMinor($ticketType, $tenant->currencyDecimals());
        $subtotal = $unit * $quantity;

        $pointsUsed = 0;
        $discount = 0;
        if ($pointsToRedeem > 0) {
            if (! $tenant->moduleEnabled('points')) {
                throw ApiException::moduleDisabled('points');
            }
            $min = (int) $tenant->setting('points.min_redeem_points', 0);
            if ($pointsToRedeem < $min) {
                throw ApiException::limitExceeded("Minimum $min points");
            }
            if ($user) {
                $balance = $this->points->accountFor($user, $tenant)->balance;
                if ($pointsToRedeem > $balance) {
                    throw ApiException::insufficientPoints($balance);
                }
            }
            $value = max(1, (int) $tenant->setting('points.redeem_value_minor_per_point', 0));
            // Never discount more than the subtotal; don't burn excess points.
            $pointsUsed = min($pointsToRedeem, intdiv($subtotal + $value - 1, $value));
            $discount = min($subtotal, $pointsUsed * $value);
        }

        $depositPer = ($tenant->moduleEnabled('deposits') && $tenant->moduleEnabled('wallet')) ? (int) $event->deposit_minor : 0;
        $deposit = $depositPer * $quantity;
        $tickets = $subtotal - $discount;

        return [
            'event' => $event,
            'quantity' => $quantity,
            'ticket_type' => $ticketType,
            'unit_price_minor' => $unit,
            'subtotal_minor' => $subtotal,
            'points_to_redeem' => $pointsUsed,
            'points_discount_minor' => $discount,
            'deposit_per_ticket_minor' => $depositPer,
            'deposit_minor' => $deposit,
            'tickets_minor' => $tickets,
            'total_minor' => $tickets + $deposit,
        ];
    }

    /**
     * @param  array{event_id:int, booking_date:string, quantity:int, ticket_type?:string|null, method:string,
     *               points_to_redeem?:int|null, idempotency_key?:string|null}  $input
     */
    public function initialize(User $user, Tenant $tenant, array $input): Payment
    {
        $key = $input['idempotency_key'] ?? null;
        if ($key && ($existing = $this->findByKey($user, $key))) {
            return $existing;
        }

        $method = $input['method'] ?? Payment::METHOD_GATEWAY;
        if ($method === Payment::METHOD_WALLET && ! $tenant->moduleEnabled('wallet')) {
            throw ApiException::moduleDisabled('wallet');
        }
        if (! $tenant->moduleEnabled('tickets')) {
            throw ApiException::moduleDisabled('tickets');
        }

        try {
            return DB::transaction(function () use ($user, $tenant, $input, $method, $key) {
                // Serialise capacity checks for this event.
                Event::whereKey($input['event_id'])->lockForUpdate()->first();

                $quote = $this->quote($tenant, (int) $input['event_id'], $input['booking_date'], (int) $input['quantity'],
                    $input['ticket_type'] ?? null, (int) ($input['points_to_redeem'] ?? 0), $user);

                $payment = $this->createPayment($user, $tenant, $quote, $input['booking_date'], $method, $key);

                if ($quote['points_to_redeem'] > 0) {
                    $this->points->spend($user, $tenant, $quote['points_to_redeem'], $payment, "pay:{$payment->id}:points");
                }

                if ($method === Payment::METHOD_WALLET) {
                    $wallet = $this->wallets->walletFor($user, $tenant);
                    if ($wallet->isFrozen()) {
                        throw ApiException::walletFrozen();
                    }
                    if ($wallet->availableMinor() < $quote['total_minor']) {
                        throw ApiException::insufficientBalance($wallet->availableMinor());
                    }
                    if ($quote['tickets_minor'] > 0) {
                        $this->wallets->debit($wallet, $quote['tickets_minor'], 'ticket_purchase', [
                            'idempotency_key' => "pay:{$payment->id}:purchase",
                            'reference' => $payment,
                            'meta' => [
                                'event_id' => $quote['event']->id,
                                'event_title' => $quote['event']->title,
                                'event_title_ar' => $quote['event']->title_ar,
                                'quantity' => $quote['quantity'],
                                'transaction_id' => $payment->transaction_id,
                            ],
                        ]);
                    }

                    return tap($this->fulfillment->complete($payment), fn ($p) => $p->wasRecentlyCreated = true);
                }

                if ($quote['total_minor'] === 0) {
                    // Free (or fully discounted) order: nothing to collect.
                    return tap($this->fulfillment->complete($payment, [], 'free'), fn ($p) => $p->wasRecentlyCreated = true);
                }

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($key && ($existing = $this->findByKey($user, $key))) {
                return $existing;
            }
            throw $e;
        }
    }

    public function paymentUrl(Payment $payment): ?string
    {
        return $payment->status === 'pending' && $payment->method === Payment::METHOD_GATEWAY
            ? $this->gateways->paymentUrl($payment)
            : null;
    }

    private function createPayment(User $user, Tenant $tenant, array $quote, string $bookingDate, string $method, ?string $key): Payment
    {
        $decimals = $tenant->currencyDecimals();
        $payment = new Payment([
            'user_id' => $user->id,
            'transaction_id' => 'TXN'.strtoupper(Str::random(12)),
            'payment_type' => Payment::TYPE_TICKET,
            'method' => $method,
            'gateway' => $method === Payment::METHOD_GATEWAY ? $this->gateways->driver() : null,
            'payable_id' => $quote['event']->id,
            'payable_type' => Event::class,
            'amount' => \App\Support\Money::format($quote['total_minor'], $decimals),
            'amount_minor' => $quote['total_minor'],
            'currency' => $tenant->currency(),
            'status' => 'pending',
            'idempotency_key' => $key,
            'metadata' => [
                'event_id' => $quote['event']->id,
                'quantity' => $quote['quantity'],
                'ticket_type' => $quote['ticket_type'],
                'booking_date' => $bookingDate,
                'user_phone' => $user->phone_number,
                'currency_decimals' => $decimals,
                'unit_price_minor' => $quote['unit_price_minor'],
                'subtotal_minor' => $quote['subtotal_minor'],
                'points_used' => $quote['points_to_redeem'],
                'points_discount_minor' => $quote['points_discount_minor'],
                'tickets_minor' => $quote['tickets_minor'],
                'deposit_per_ticket_minor' => $quote['deposit_per_ticket_minor'],
                'deposit_minor' => $quote['deposit_minor'],
                'deposit_type' => $quote['event']->deposit_type ?: 'wristband',
            ],
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();

        return $payment;
    }

    private function findByKey(User $user, string $key): ?Payment
    {
        return Payment::where('user_id', $user->id)->where('idempotency_key', $key)->first();
    }
}
