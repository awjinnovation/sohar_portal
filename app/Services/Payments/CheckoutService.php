<?php

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Event;
use App\Models\EventTicketType;
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
     * Legacy single-type quote: `quantity` of `ticket_type` (a type key;
     * empty / `standard` = the event's default type).
     */
    public function quote(Tenant $tenant, int $eventId, string $bookingDate, int $quantity, ?string $ticketType, int $pointsToRedeem = 0, ?User $user = null): array
    {
        return $this->quoteItems($tenant, $eventId, $bookingDate, [['ticket_type' => $ticketType, 'quantity' => $quantity]], $pointsToRedeem, $user);
    }

    /**
     * Server-side price of an order of several ticket types
     * (docs/ticket_types_contract.md §2). Items: [{ticket_type_id, quantity}]
     * or legacy [{ticket_type: key|null, quantity}]; zero quantities are
     * skipped, repeated types merged.
     *
     * Errors: EVENT_NOT_FOUND, TYPE_NOT_AVAILABLE (unknown / inactive /
     * other channel), SALES_CLOSED, PER_ORDER_LIMIT (per type or the whole
     * order: `ticket_type_id` null), SOLD_OUT (type capacity: with
     * `ticket_type_id`; event capacity or a date outside the event: null).
     *
     * @return array{event: Event, quantity:int, ticket_type:?string, unit_price_minor:?int, lines: list<array>,
     *               subtotal_minor:int, points_to_redeem:int, points_discount_minor:int, deposit_per_ticket_minor:int,
     *               deposit_minor:int, tickets_minor:int, total_minor:int}
     */
    public function quoteItems(Tenant $tenant, int $eventId, string $bookingDate, array $items, int $pointsToRedeem = 0, ?User $user = null,
        string $channel = EventTicketType::CHANNEL_APP, ?int $maxQuantity = null): array
    {
        $event = Event::where('is_active', true)->find($eventId);
        if (! $event) {
            throw new ApiException('EVENT_NOT_FOUND', 'Event not found or not available', 404);
        }

        $lines = $this->resolveLines($event, $items, $channel);
        $quantity = array_sum(array_column($lines, 'quantity'));
        $maxQuantity ??= (int) config('payments.max_tickets_per_order', 10);
        if ($quantity > $maxQuantity) {
            throw new ApiException('PER_ORDER_LIMIT', __('platform.errors.per_order_limit_total', ['max' => $maxQuantity]), 422,
                ['ticket_type_id' => null, 'per_order_max' => $maxQuantity]);
        }
        if (! $event->canBookForDate($bookingDate, $quantity)) {
            throw new ApiException('SOLD_OUT', 'Not enough tickets available for the selected date', 422, [
                'ticket_type_id' => null,
                'available' => max(0, $event->getAvailableTicketsForDate($bookingDate)),
            ]);
        }
        $eventAvailable = $event->getAvailableTicketsForDate($bookingDate);
        foreach ($lines as $line) {
            $left = $line['type']->availableOn($bookingDate, $eventAvailable);
            if ($left !== null && $line['quantity'] > $left) {
                throw new ApiException('SOLD_OUT', __('platform.errors.type_sold_out', ['type' => $line['name_text']]), 422, [
                    'ticket_type_id' => $line['ticket_type_id'],
                    'available' => max(0, $left),
                ]);
            }
        }

        $subtotal = array_sum(array_column($lines, 'line_total_minor'));
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
        $prices = array_unique(array_column($lines, 'unit_price_minor'));

        return [
            'event' => $event,
            'quantity' => $quantity,
            // Legacy single-type fields: the key / unit price when the order has one type (one price).
            'ticket_type' => count($lines) === 1 ? $lines[0]['key'] : null,
            'unit_price_minor' => count($prices) === 1 ? (int) reset($prices) : null,
            'lines' => array_map(fn ($l) => array_diff_key($l, ['type' => true]), $lines),
            'subtotal_minor' => $subtotal,
            'points_to_redeem' => $pointsUsed,
            'points_discount_minor' => $discount,
            'deposit_per_ticket_minor' => $depositPer,
            'deposit_minor' => $deposit,
            'tickets_minor' => $tickets,
            'total_minor' => $tickets + $deposit,
        ];
    }

    /** @return list<array{type: EventTicketType, ticket_type_id:int, key:string, name: array, name_text:string, quantity:int, unit_price_minor:int, line_total_minor:int, admits:int}> */
    private function resolveLines(Event $event, array $items, string $channel): array
    {
        $types = $event->sellableTicketTypes($channel)->keyBy('id');
        $merged = [];
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if (array_key_exists('ticket_type_id', $item) && $item['ticket_type_id'] !== null) {
                $type = $types->get((int) $item['ticket_type_id']);
                $requested = (int) $item['ticket_type_id'];
            } else {
                $type = $event->ticketTypeByKey($item['ticket_type'] ?? null, $channel);
                $requested = $type?->id;
            }
            if (! $type) {
                throw new ApiException('TYPE_NOT_AVAILABLE', __('platform.errors.type_not_available'), 422, ['ticket_type_id' => $requested]);
            }
            $merged[$type->id] = ($merged[$type->id] ?? 0) + $qty;
        }
        if (! $merged) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => [__('platform.errors.items_required')]]);
        }

        $lines = [];
        foreach ($merged as $id => $qty) {
            $type = $types->get($id);
            if (! $type->salesOpen()) {
                throw new ApiException('SALES_CLOSED', __('platform.errors.sales_closed', ['type' => $type->nameText()]), 422, [
                    'ticket_type_id' => $type->id,
                    'sales_start' => \App\Support\TenantTime::iso($type->sales_start),
                    'sales_end' => \App\Support\TenantTime::iso($type->sales_end),
                ]);
            }
            if ($qty > max(1, (int) $type->per_order_max)) {
                throw new ApiException('PER_ORDER_LIMIT', __('platform.errors.per_order_limit', ['type' => $type->nameText(), 'max' => $type->per_order_max]), 422, [
                    'ticket_type_id' => $type->id,
                    'per_order_max' => (int) $type->per_order_max,
                ]);
            }
            $lines[] = [
                'type' => $type,
                'ticket_type_id' => $type->id,
                'key' => $type->key,
                'name' => ['en' => (string) ($type->name['en'] ?? ''), 'ar' => (string) ($type->name['ar'] ?? '')],
                'name_text' => $type->nameText(),
                'quantity' => $qty,
                'unit_price_minor' => (int) $type->price_minor,
                'line_total_minor' => (int) $type->price_minor * $qty,
                'admits' => max(1, (int) $type->admits),
            ];
        }

        return $lines;
    }

    /**
     * @param  array{event_id:int, booking_date:string, quantity?:int, ticket_type?:string|null, items?: list<array{ticket_type_id:int, quantity:int}>,
     *               method:string, points_to_redeem?:int|null, idempotency_key?:string|null}  $input
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

                $items = ! empty($input['items'])
                    ? $input['items']
                    : [['ticket_type' => $input['ticket_type'] ?? null, 'quantity' => (int) ($input['quantity'] ?? 0)]];
                $quote = $this->quoteItems($tenant, (int) $input['event_id'], $input['booking_date'], $items,
                    (int) ($input['points_to_redeem'] ?? 0), $user);

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

    /**
     * A pending gateway payment for an already priced order (gate box
     * office: the order's stored quote, its seats already held). No
     * capacity check, no points; completion goes through
     * PaymentFulfillmentService like any checkout.
     */
    public function createGatewayPayment(User $user, Tenant $tenant, array $quote, string $bookingDate, ?string $key, array $extraMeta = []): Payment
    {
        return $this->createPayment($user, $tenant, $quote, $bookingDate, Payment::METHOD_GATEWAY, $key, $extraMeta);
    }

    public function paymentUrl(Payment $payment): ?string
    {
        return $payment->status === 'pending' && $payment->method === Payment::METHOD_GATEWAY
            ? $this->gateways->paymentUrl($payment)
            : null;
    }

    private function createPayment(User $user, Tenant $tenant, array $quote, string $bookingDate, string $method, ?string $key, array $extraMeta = []): Payment
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
                'ticket_type' => $quote['ticket_type'] ?? ($quote['lines'][0]['key'] ?? null),
                // One entry per ticket type; TicketIssuer creates the tickets from these.
                'lines' => array_values(array_map(fn ($l) => [
                    'ticket_type_id' => $l['ticket_type_id'], 'key' => $l['key'], 'name' => $l['name'],
                    'quantity' => $l['quantity'], 'unit_price_minor' => $l['unit_price_minor'],
                    'line_total_minor' => $l['line_total_minor'], 'admits' => $l['admits'],
                ], $quote['lines'] ?? [])),
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
            ] + $extraMeta,
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
