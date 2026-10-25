<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Jobs\SendOtpSms;
use App\Models\BoxOfficeOrder;
use App\Models\BoxOfficeOrderLine;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentFulfillmentService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gate box office (docs/gate_sales_contract.md, ticket types:
 * docs/ticket_types_contract.md §2).
 *
 * Staff sell at the gate → a pending order holding its seats, priced on the
 * server (CheckoutService::quoteItems, box office channel) → the visitor
 * pays on /pay/{public_token} through the normal gateway flow → the single
 * idempotent fulfillment issues the tickets to the buyer (TicketIssuer),
 * earns their points and marks the order paid (markPaid). Orders not paid
 * within `gate_sales.link_ttl_minutes` expire (box-office:expire, every
 * minute, plus lazily on read); staff may cancel pending orders.
 */
class BoxOfficeService
{
    public const PERMISSION = 'sell tickets';

    /** At most this many SMS per order (the first one included), this many seconds apart. */
    public const MAX_SMS = 3;

    public const SMS_INTERVAL = 30;

    public function __construct(
        private TenantContext $context,
        private CheckoutService $checkout,
        private PaymentFulfillmentService $fulfillment,
        private TicketScanService $scanner,
    ) {}

    // ------------------------------------------------------------------
    // Settings & access
    // ------------------------------------------------------------------

    /** Typed `gate_sales` settings (docs/gate_sales_contract.md §1). */
    public static function settings(Tenant $tenant): array
    {
        $s = (array) $tenant->setting('gate_sales', []);

        return [
            'enabled' => (bool) ($s['enabled'] ?? false),
            'link_ttl_minutes' => max(1, min(1440, (int) ($s['link_ttl_minutes'] ?? 30))),
            'send_sms' => (bool) ($s['send_sms'] ?? true),
            'max_quantity' => max(1, min(100, (int) ($s['max_quantity'] ?? 10))),
            'allow_future_dates' => (bool) ($s['allow_future_dates'] ?? false),
        ];
    }

    public static function canSell(User $user): bool
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user->isSuperAdmin() || $user->checkPermissionTo(self::PERMISSION);
    }

    /** The reason this device / staff session may not sell, or null. */
    public function denial(Tenant $tenant, GateDevice $device): ?ApiException
    {
        $gate = $device->gate;
        $staff = $device->staff_user_id ? $device->staff : null;

        return match (true) {
            ! self::settings($tenant)['enabled'] => new ApiException('SALES_DISABLED', __('box_office.errors.sales_disabled'), 403),
            ! $tenant->moduleEnabled('tickets') => ApiException::moduleDisabled('tickets'),
            ! $gate => new ApiException('GATE_CANNOT_SELL', __('box_office.errors.no_gate'), 403),
            ! $gate->can_sell => new ApiException('GATE_CANNOT_SELL', __('box_office.errors.gate_cannot_sell'), 403),
            ! $gate->active => new ApiException('GATE_INACTIVE', __('gate.errors.gate_inactive'), 409),
            ! $staff => new ApiException('STAFF_LOGIN_REQUIRED', __('box_office.errors.staff_login_required'), 403, ['reason' => 'no_staff']),
            ! self::canSell($staff) => new ApiException('STAFF_LOGIN_REQUIRED', __('box_office.errors.staff_cannot_sell'), 403, ['reason' => 'no_permission']),
            default => null,
        };
    }

    /** settings.sales of GET /gate/session (+ whether this device can sell right now). */
    public function sessionSettings(Tenant $tenant, GateDevice $device): array
    {
        $denial = $this->denial($tenant, $device);

        return self::settings($tenant) + [
            'can_sell' => (bool) $device->gate?->can_sell,
            'available' => $denial === null,
            'unavailable_code' => $denial?->errorCode,
            'max_sms' => self::MAX_SMS,
        ];
    }

    // ------------------------------------------------------------------
    // Catalog & quote
    // ------------------------------------------------------------------

    /** The gate day, or a later day when `allow_future_dates` is on. */
    public function resolveDate(Tenant $tenant, ?string $requested, string $field = 'booking_date'): string
    {
        $today = GateDay::today($tenant);
        if ($requested === null || $requested === '' || $requested === $today) {
            return $today;
        }
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) && Carbon::hasFormat($requested, 'Y-m-d');
        if (! $valid || $requested < $today || ! self::settings($tenant)['allow_future_dates']) {
            throw ValidationException::withMessages([$field => [__('box_office.errors.date_not_allowed')]]);
        }

        return $requested;
    }

    /** Events sellable at this gate on $date with their ticket types (public + box_office, gate-accepted). */
    public function catalog(Tenant $tenant, Gate $gate, string $date): array
    {
        $events = Event::active()
            ->with('mapLocation')
            ->when($gate->event_id, fn ($q) => $q->whereKey($gate->event_id))
            ->whereDate('start_time', '<=', $date)->whereDate('end_time', '>=', $date)
            ->orderBy('start_time')->get();

        $out = [];
        foreach ($events as $event) {
            if ($this->ended($event, $date, $tenant)) {
                continue;
            }
            $types = $this->gateTypes($event, $gate);
            if ($types->isEmpty()) {
                continue;
            }
            $available = $event->getAvailableTicketsForDate($date);
            $out[] = $this->eventPayload($event, $tenant) + [
                'ticket_types' => $types->map(fn (EventTicketType $t) => $this->typePayload($t, $date, $tenant, $available))->values()->all(),
                'available' => $available === -1 ? null : max(0, $available),
                'sold_out' => $available === 0,
            ];
        }

        return $out;
    }

    /**
     * Server price of a gate order. $items: [{ticket_type_id, quantity}] or
     * legacy [{ticket_type: key|null, quantity}].
     */
    public function quote(Tenant $tenant, Gate $gate, int $eventId, array $items, string $date): array
    {
        $event = $this->sellableEvent($tenant, $gate, $eventId, $date);
        $allowed = $this->gateTypes($event, $gate)->keyBy('id');

        $resolved = [];
        foreach ($items as $item) {
            if ((int) ($item['quantity'] ?? 0) <= 0) {
                continue;
            }
            if (array_key_exists('ticket_type_id', $item) && $item['ticket_type_id'] !== null) {
                $type = $allowed->get((int) $item['ticket_type_id']);
                if (! $type) {
                    throw new ApiException('TYPE_NOT_AVAILABLE', __('box_office.errors.type_not_sellable'), 422, ['ticket_type_id' => (int) $item['ticket_type_id']]);
                }
            } else {
                $key = $item['ticket_type'] ?? null;
                $type = ($key === null || $key === '' || $key === 'standard')
                    ? ($allowed->firstWhere('key', 'general') ?? $allowed->first())
                    : $allowed->firstWhere('key', $key);
                if (! $type) {
                    throw new ApiException('TYPE_NOT_AVAILABLE', __('box_office.errors.type_not_sellable'), 422, ['ticket_type_id' => null, 'ticket_type' => $key]);
                }
            }
            $resolved[] = ['ticket_type_id' => $type->id, 'quantity' => (int) $item['quantity']];
        }

        try {
            $quote = $this->checkout->quoteItems($tenant, $event->id, $date, $resolved, 0, null,
                EventTicketType::CHANNEL_BOX_OFFICE, self::settings($tenant)['max_quantity']);
        } catch (ApiException $e) {
            if ($e->errorCode === 'SOLD_OUT' && ($e->extra['ticket_type_id'] ?? null) === null) {
                throw new ApiException('SOLD_OUT', __('box_office.errors.sold_out'), 422, $e->extra);
            }
            throw $e;
        }

        $available = $event->getAvailableTicketsForDate($date);

        return $quote + ['booking_date' => $date, 'available' => $available === -1 ? null : max(0, $available)];
    }

    /** API shape of a quote (docs/gate_sales_contract.md §3 + lines). */
    public function quotePayload(array $q, Tenant $tenant): array
    {
        return [
            'event_id' => $q['event']->id,
            'booking_date' => $q['booking_date'],
            'quantity' => $q['quantity'],
            'ticket_type' => $q['ticket_type'],
            'lines' => \App\Http\Controllers\Api\PaymentController::linesPayload($q['lines'], $tenant),
            'unit_price_minor' => $q['unit_price_minor'],
            'subtotal_minor' => $q['subtotal_minor'],
            'deposit_minor' => $q['deposit_minor'],
            'total_minor' => $q['total_minor'],
            'total' => $tenant->formatMoney($q['total_minor']),
            'currency' => $tenant->currency(),
            'available' => $q['available'],
        ];
    }

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    /**
     * Create an order (idempotent per device + key). Returns [order, created].
     *
     * @return array{0: BoxOfficeOrder, 1: bool}
     */
    public function create(Tenant $tenant, GateDevice $device, User $staff, array $input): array
    {
        $key = (string) $input['idempotency_key'];
        $phone = $this->normalizePhone($tenant, (string) $input['buyer_phone']);
        $items = $input['items'];

        if ($existing = $this->findByKey($device, $key)) {
            return [$this->assertSamePayload($existing, (int) $input['event_id'], $phone), false];
        }

        $gate = $device->gate;
        $date = $this->resolveDate($tenant, $input['booking_date'] ?? null);
        $settings = self::settings($tenant);
        $locale = in_array($input['locale'] ?? null, ['ar', 'en'], true) ? $input['locale'] : app()->getLocale();

        try {
            $order = DB::transaction(function () use ($tenant, $device, $staff, $gate, $input, $items, $date, $settings, $locale, $phone, $key) {
                // Serialise capacity checks for this event (holds are counted inside the quote).
                Event::whereKey($input['event_id'])->lockForUpdate()->first();
                $q = $this->quote($tenant, $gate, (int) $input['event_id'], $items, $date);
                $buyer = $this->buyer($phone, $input['buyer_name'] ?? null);
                $single = count($q['lines']) === 1;

                $order = new BoxOfficeOrder([
                    'gate_id' => $gate->id,
                    'device_id' => $device->id,
                    'staff_user_id' => $staff->id,
                    'buyer_user_id' => $buyer->id,
                    'buyer_phone' => $phone,
                    'buyer_name' => filled($input['buyer_name'] ?? null) ? trim($input['buyer_name']) : null,
                    'event_id' => $q['event']->id,
                    'booking_date' => $date,
                    'ticket_type' => $single ? $q['lines'][0]['key'] : 'mixed',
                    'quantity' => $q['quantity'],
                    'unit_price_minor' => (int) ($q['unit_price_minor'] ?? 0),
                    'subtotal_minor' => $q['subtotal_minor'],
                    'deposit_minor' => $q['deposit_minor'],
                    'total_minor' => $q['total_minor'],
                    'currency' => $tenant->currency(),
                    'status' => BoxOfficeOrder::STATUS_PENDING,
                    'public_token' => Str::random(32),
                    'locale' => $locale,
                    'expires_at' => now()->addMinutes($settings['link_ttl_minutes']),
                    'idempotency_key' => $key,
                    'meta' => ['deposit_per_ticket_minor' => $q['deposit_per_ticket_minor'], 'deposit_type' => $q['event']->deposit_type ?: 'wristband'],
                ]);
                $order->tenant_id = $tenant->id;
                $this->saveWithReference($order, $tenant, $device, $key);

                foreach ($q['lines'] as $l) {
                    $order->lines()->create([
                        'ticket_type_id' => $l['ticket_type_id'], 'key' => $l['key'], 'name' => $l['name'], 'quantity' => $l['quantity'],
                        'unit_price_minor' => $l['unit_price_minor'], 'line_total_minor' => $l['line_total_minor'], 'admits' => $l['admits'],
                    ]);
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $this->findByKey($device, $key)) {
                return [$existing, false];
            }
            throw $e;
        }

        if ($order->total_minor === 0) {
            // Free tickets only: nothing to collect, issue them right away.
            $payment = $this->startPayment($order, $locale);
            if ($payment) {
                $this->fulfillment->complete($payment, [], 'free');
            }

            return [$order->fresh(), true];
        }

        $sendSms = array_key_exists('send_sms', $input) && $input['send_sms'] !== null ? (bool) $input['send_sms'] : $settings['send_sms'];
        if ($sendSms) {
            $this->dispatchSms($order, $tenant);
        }

        return [$order->fresh(), true];
    }

    /** Resend the payment link (at most MAX_SMS per order, SMS_INTERVAL seconds apart). */
    public function resendSms(BoxOfficeOrder $order, Tenant $tenant): BoxOfficeOrder
    {
        $order = $this->expireIfDue($order);
        $this->assertPending($order);

        return DB::transaction(function () use ($order, $tenant) {
            $locked = BoxOfficeOrder::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->sms_count >= self::MAX_SMS) {
                throw new ApiException('SMS_LIMIT', __('box_office.errors.sms_limit'), 429, ['sms_count' => $locked->sms_count, 'max' => self::MAX_SMS, 'retry_after' => null]);
            }
            $wait = $this->smsRetryAfter($locked);
            if ($wait > 0) {
                throw new ApiException('SMS_LIMIT', __('box_office.errors.sms_wait', ['seconds' => trans_choice('box_office.seconds', $wait, ['count' => $wait])]), 429,
                    ['sms_count' => $locked->sms_count, 'max' => self::MAX_SMS, 'retry_after' => $wait]);
            }
            $this->dispatchSms($locked, $tenant);

            return $locked->fresh();
        });
    }

    /** Seconds until another SMS may be sent (0 = now; null = no more SMS for this order). */
    public function smsRetryAfter(BoxOfficeOrder $order): ?int
    {
        if ($order->sms_count >= self::MAX_SMS) {
            return null;
        }
        if (! $order->sms_sent_at) {
            return 0;
        }

        return max(0, self::SMS_INTERVAL - (int) $order->sms_sent_at->diffInSeconds(now(), true));
    }

    public function cancel(BoxOfficeOrder $order, ?User $by, string $via): BoxOfficeOrder
    {
        $order = $this->expireIfDue($order);
        $this->assertPending($order);
        $closed = $this->close($order, BoxOfficeOrder::STATUS_CANCELLED, ['cancelled_by' => $by?->id, 'cancelled_via' => $via]);
        if ($closed->status !== BoxOfficeOrder::STATUS_CANCELLED) {
            $this->assertPending($closed); // paid or expired meanwhile
        }

        return $closed;
    }

    /** Mark a pending order past its expiry as expired (lazy; the scheduler does the rest). */
    public function expireIfDue(BoxOfficeOrder $order): BoxOfficeOrder
    {
        return $order->isDue() ? $this->close($order, BoxOfficeOrder::STATUS_EXPIRED) : $order;
    }

    /** box-office:expire (every minute). Returns the number of orders expired. */
    public function expireDue(): int
    {
        $count = 0;
        BoxOfficeOrder::withoutTenantScope()
            ->where('status', BoxOfficeOrder::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->lazyById(200)
            ->each(function (BoxOfficeOrder $order) use (&$count) {
                $tenant = Tenant::find($order->tenant_id);
                if (! $tenant) {
                    return;
                }
                $closed = $this->context->run($tenant, fn () => $this->expireIfDue($order));
                $count += $closed->status === BoxOfficeOrder::STATUS_EXPIRED ? 1 : 0;
            });

        return $count;
    }

    private function close(BoxOfficeOrder $order, string $status, array $meta = []): BoxOfficeOrder
    {
        $closed = DB::transaction(function () use ($order, $status, $meta) {
            $locked = BoxOfficeOrder::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending() || ($status === BoxOfficeOrder::STATUS_EXPIRED && ! $locked->isDue())) {
                return $locked;
            }
            $locked->status = $status;
            if ($status === BoxOfficeOrder::STATUS_CANCELLED) {
                $locked->cancelled_at = now();
            }
            if ($meta) {
                $locked->meta = array_merge($locked->meta ?? [], array_filter($meta, fn ($v) => $v !== null));
            }
            $locked->save();

            return $locked;
        });

        // A gateway checkout the visitor left open is cancelled too. If the
        // bank still captures it, fulfillment completes the order (markPaid).
        if ($closed->status === $status && $closed->payment_id) {
            $payment = Payment::withoutTenantScope()->find($closed->payment_id);
            if ($payment && $payment->status === 'pending') {
                $this->fulfillment->fail($payment, 'cancelled');
            }
        }

        return $closed;
    }

    /**
     * The gateway payment of a pending order (reused while pending, a new
     * one after a failed / cancelled attempt). Null when the order cannot be paid.
     */
    public function startPayment(BoxOfficeOrder $order, ?string $lang = null): ?Payment
    {
        return DB::transaction(function () use ($order, $lang) {
            $locked = BoxOfficeOrder::withoutTenantScope()->with('lines')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending() || $locked->isDue()) {
                return null;
            }
            $current = $locked->payment_id ? Payment::withoutTenantScope()->find($locked->payment_id) : null;
            if ($current && $current->status === 'pending') {
                if ($lang && ($current->metadata['box_office_lang'] ?? null) !== $lang) {
                    $current->metadata = array_merge($current->metadata ?? [], ['box_office_lang' => $lang]);
                    $current->save();
                }

                return $current;
            }

            $tenant = Tenant::findOrFail($locked->tenant_id);
            $event = Event::withoutTenantScope()->findOrFail($locked->event_id);
            $buyer = User::findOrFail($locked->buyer_user_id);
            $lines = $locked->lines->map(fn (BoxOfficeOrderLine $l) => [
                'ticket_type_id' => $l->ticket_type_id, 'key' => $l->key, 'name' => $l->name, 'quantity' => $l->quantity,
                'unit_price_minor' => $l->unit_price_minor, 'line_total_minor' => $l->line_total_minor, 'admits' => $l->admits,
            ])->all();
            $attempt = (int) ($locked->meta['payment_attempts'] ?? 0) + 1;

            $payment = $this->checkout->createGatewayPayment($buyer, $tenant, [
                'event' => $event,
                'quantity' => $locked->quantity,
                'ticket_type' => count($lines) === 1 ? $lines[0]['key'] : null,
                'unit_price_minor' => $locked->unit_price_minor,
                'lines' => $lines,
                'subtotal_minor' => $locked->subtotal_minor,
                'points_to_redeem' => 0,
                'points_discount_minor' => 0,
                'deposit_per_ticket_minor' => (int) ($locked->meta['deposit_per_ticket_minor'] ?? 0),
                'deposit_minor' => $locked->deposit_minor,
                'tickets_minor' => $locked->subtotal_minor,
                'total_minor' => $locked->total_minor,
            ], $locked->booking_date->toDateString(), "box_office:{$locked->id}:{$attempt}", [
                'box_office_order_id' => $locked->id,
                'box_office_reference' => $locked->reference,
                'box_office_lang' => $lang ?? $locked->locale,
                'gate_id' => $locked->gate_id,
                'sold_by' => $locked->staff_user_id,
            ]);

            $locked->payment_id = $payment->id;
            $locked->meta = array_merge($locked->meta ?? [], ['payment_attempts' => $attempt]);
            $locked->save();

            return $payment;
        });
    }

    /**
     * Called by PaymentFulfillmentService inside its transaction, before any
     * ticket is issued for a box office payment. True = issue the tickets.
     *
     * - pending order (its own seats are still held) → true;
     * - order expired / cancelled (or past its expiry) → re-check the seats of
     *   every line (type capacity and event capacity, under a lock on the
     *   event): free → true; taken → the order becomes `paid_unfulfilled`;
     * - order already paid by another payment, or already paid_unfulfilled /
     *   refunded → false.
     * When false, nothing is issued (no tickets, deposits or points) and the
     * payment is flagged `box_office_refund_required` for the admin.
     */
    public function reserveForPayment(Payment $payment): bool
    {
        $orderId = (int) ($payment->metadata['box_office_order_id'] ?? 0);
        $order = BoxOfficeOrder::withoutTenantScope()->with('lines')->whereKey($orderId)->lockForUpdate()->first();
        if (! $order || (int) $order->tenant_id !== (int) $payment->tenant_id) {
            return true; // not ours to judge; behave like any ticket payment
        }
        if ($order->isPending() && ! $order->isDue()) {
            return true;
        }

        $reason = null;
        if (in_array($order->status, [BoxOfficeOrder::STATUS_PAID, BoxOfficeOrder::STATUS_PAID_UNFULFILLED, BoxOfficeOrder::STATUS_REFUNDED], true)) {
            $reason = $order->status === BoxOfficeOrder::STATUS_PAID && (int) $order->payment_id === (int) $payment->id ? null : 'already_settled';
            if ($reason === null) {
                return true;
            }
        } elseif (! $this->seatsFree($order)) {
            $reason = 'sold_out';
        }

        if ($reason === null) {
            return true;
        }

        Log::warning('Box office payment captured without seats: refund required', [
            'order' => $order->reference, 'payment' => $payment->transaction_id, 'reason' => $reason, 'order_status' => $order->status,
        ]);
        $payment->metadata = array_merge($payment->metadata ?? [], ['box_office_refund_required' => true, 'box_office_unfulfilled_reason' => $reason]);
        if ($reason === 'sold_out') {
            $order->meta = array_merge($order->meta ?? [], ['paid_after' => $order->status, 'refund_required' => true]);
            $order->status = BoxOfficeOrder::STATUS_PAID_UNFULFILLED;
            $order->paid_at = $payment->paid_at ?? now();
            $order->payment_id = $payment->id;
        } else {
            $order->meta = array_merge($order->meta ?? [], ['refund_payment_ids' => array_values(array_unique(array_merge($order->meta['refund_payment_ids'] ?? [], [$payment->id])))]);
        }
        $order->save();

        return false;
    }

    /** Seats for every line of a (no longer holding) order on its booking date. */
    private function seatsFree(BoxOfficeOrder $order): bool
    {
        $event = Event::withoutTenantScope()->whereKey($order->event_id)->lockForUpdate()->first();
        if (! $event) {
            return false;
        }
        $date = $order->booking_date->toDateString();
        $eventLeft = $event->getAvailableTicketsForDate($date);
        if ($eventLeft !== -1 && $eventLeft < $order->quantity) {
            return false;
        }
        foreach ($order->lines as $line) {
            $type = $line->ticket_type_id ? EventTicketType::withoutTenantScope()->find($line->ticket_type_id) : null;
            if (! $type) {
                continue;
            }
            $left = $type->setRelation('event', $event)->availableOn($date, $eventLeft);
            if ($left !== null && $left < $line->quantity) {
                return false;
            }
        }

        return true;
    }

    /** The order's payment was refunded (Admin → Payments or Gate sales): the order follows. */
    public function markRefunded(Payment $payment): void
    {
        $order = BoxOfficeOrder::withoutTenantScope()->whereKey((int) ($payment->metadata['box_office_order_id'] ?? 0))->lockForUpdate()->first();
        if (! $order || (int) $order->payment_id !== (int) $payment->id) {
            return;
        }
        $order->meta = array_merge($order->meta ?? [], ['refunded_from' => $order->status, 'refunded_at' => now()->toIso8601String()]);
        $order->status = BoxOfficeOrder::STATUS_REFUNDED;
        $order->save();
    }

    /**
     * Called by PaymentFulfillmentService inside its transaction, once per
     * completed payment, after reserveForPayment() allowed the tickets: the
     * order becomes paid (a late capture with free seats completes it too).
     */
    public function markPaid(Payment $payment, Collection $tickets): void
    {
        $order = BoxOfficeOrder::withoutTenantScope()->whereKey((int) ($payment->metadata['box_office_order_id'] ?? 0))->lockForUpdate()->first();
        if (! $order || (int) $order->tenant_id !== (int) $payment->tenant_id) {
            return;
        }
        if ($order->status === BoxOfficeOrder::STATUS_PAID) {
            if ((int) $order->payment_id !== (int) $payment->id) {
                Log::warning('Box office order paid by a second payment', ['order' => $order->reference, 'payment' => $payment->transaction_id]);
                $order->meta = array_merge($order->meta ?? [], ['extra_payment_ids' => array_values(array_unique(array_merge($order->meta['extra_payment_ids'] ?? [], [$payment->id])))]);
                $order->save();
            }

            return;
        }
        if (! $order->isPending()) {
            Log::warning('Box office order paid after it was '.$order->status, ['order' => $order->reference]);
            $order->meta = array_merge($order->meta ?? [], ['paid_after' => $order->status]);
        }
        $order->status = BoxOfficeOrder::STATUS_PAID;
        $order->paid_at = $payment->paid_at ?? now();
        $order->payment_id = $payment->id;
        $order->save();
    }

    /**
     * Admit the order's tickets through TicketScanService (source
     * `box_office`). Default: every ticket with entries left; each ticket's
     * remaining entries are admitted (a family ticket = the whole family)
     * unless $entries limits it.
     *
     * @return list<array{ticket_id: int, outcome: ScanOutcome, entries_admitted: int}>
     */
    public function admit(BoxOfficeOrder $order, GateDevice $device, ?User $staff, ?array $ticketIds, ?int $entries = null, ?string $key = null): array
    {
        if ($order->status !== BoxOfficeOrder::STATUS_PAID) {
            throw new ApiException('INVALID_STATE', __('box_office.errors.order_not_paid'), 409, ['status' => $order->status]);
        }
        $gate = $device->gate;
        if ($gate && ! $gate->active) {
            throw new ApiException('GATE_INACTIVE', __('gate.errors.gate_inactive'), 409);
        }

        $tickets = $order->issuedTickets();
        if ($ticketIds !== null) {
            $ids = array_values(array_unique(array_map('intval', $ticketIds)));
            if (array_diff($ids, $tickets->pluck('id')->all())) {
                throw ValidationException::withMessages(['ticket_ids' => [__('box_office.errors.tickets_not_in_order')]]);
            }
            $tickets = $tickets->whereIn('id', $ids)->values();
        } else {
            $tickets = $tickets->filter(fn (Ticket $t) => $t->status === 'active' && (int) $t->check_in_count < $t->admits())->values();
        }

        $results = [];
        foreach ($tickets as $ticket) {
            $left = max(1, $ticket->admits() - (int) $ticket->check_in_count);
            $times = $entries ? min($entries, $left) : $left;
            $outcome = null;
            $admitted = 0;
            for ($i = 0; $i < $times; $i++) {
                $outcome = $this->scanner->scan(new ScanInput(
                    ticket: $ticket->fresh(),
                    gate: $gate,
                    device: $device,
                    staff: $staff,
                    source: TicketScanService::SOURCE_BOX_OFFICE,
                    direction: 'in',
                    idempotencyKey: $key ? 'bo:'.$order->id.':'.$ticket->id.':'.$i.':'.substr(sha1($key), 0, 16) : null,
                ));
                if (! $outcome->admitted()) {
                    break;
                }
                $admitted++;
            }
            $results[] = ['ticket_id' => $ticket->id, 'outcome' => $outcome, 'entries_admitted' => $admitted];
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Presentation
    // ------------------------------------------------------------------

    /**
     * Absolute public link of an order. APP_URL (or PAY_LINK_BASE_URL);
     * outside production the framework default http://localhost falls back
     * to the host the device called, so phones on the LAN can open it.
     */
    public function paymentUrl(BoxOfficeOrder $order): string
    {
        return rtrim(self::publicBase(), '/').'/pay/'.$order->public_token;
    }

    public static function publicBase(): string
    {
        $base = trim((string) config('payments.public_url')) ?: trim((string) config('app.url'));
        if (! app()->environment('production') && preg_match('#^https?://localhost/?$#i', $base)) {
            $request = request();
            if ($request && $request->getHost() && ! app()->runningInConsole()) {
                return $request->getSchemeAndHttpHost();
            }
        }

        return $base;
    }

    /** Order (docs/gate_sales_contract.md §3 + ticket_types_contract.md §2 lines). */
    public function toApi(BoxOfficeOrder $order, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::find($order->tenant_id);
        $locale = app()->getLocale();
        $order->loadMissing(['event', 'lines', 'gate', 'staff']);
        $event = $order->event;
        $url = $this->paymentUrl($order);
        $lines = $order->lines->map(fn (BoxOfficeOrderLine $l) => $l->toApi() + [
            'unit_price' => $tenant->formatMoney($l->unit_price_minor),
            'line_total' => $tenant->formatMoney($l->line_total_minor),
        ])->values()->all();
        $prices = array_unique(array_column($lines, 'unit_price_minor'));

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'status_text' => __('box_office.statuses.'.$order->status),
            'event' => $event ? [
                'id' => $event->id,
                'title_text' => $locale === 'ar' ? ($event->title_ar ?: $event->title) : $event->title,
                'title' => ['en' => $event->title, 'ar' => $event->title_ar ?: $event->title],
            ] : null,
            'booking_date' => $order->booking_date?->toDateString(),
            'ticket_type' => count($lines) === 1 ? $lines[0]['key'] : null,
            'quantity' => $order->quantity,
            'lines' => $lines,
            'unit_price_minor' => count($prices) === 1 ? (int) reset($prices) : null,
            'total_minor' => $order->total_minor,
            'total' => $tenant->formatMoney($order->total_minor),
            'currency' => $order->currency,
            'buyer_phone_masked' => $order->maskedPhone($tenant),
            'buyer_name' => $order->buyer_name,
            'payment_url' => $url,
            'qr_payload' => $url,
            'expires_at' => TenantTime::iso($order->expires_at, $tenant),
            'seconds_left' => $order->secondsLeft(),
            'sms_sent' => $order->sms_count > 0,
            'sms_count' => $order->sms_count,
            'sms_max' => self::MAX_SMS,
            'sms_retry_after' => $order->isPending() ? $this->smsRetryAfter($order) : null,
            'tickets' => $order->issuedTickets()->map(fn (Ticket $t) => [
                'id' => $t->id,
                'number' => $t->ticket_number,
                'qr_code' => $t->qr_code,
                'status' => $t->status,
                'ticket_type' => $t->ticketTypeApi(),
                'admits' => $t->admits(),
                'entries_used' => (int) $t->check_in_count,
            ])->values()->all(),
            'gate' => $order->gate ? ['id' => $order->gate->id, 'code' => $order->gate->code] : null,
            'staff' => $order->staff ? ['id' => $order->staff->id, 'name' => $order->staff->name] : null,
            'created_at' => TenantTime::iso($order->created_at, $tenant),
            'paid_at' => TenantTime::iso($order->paid_at, $tenant),
        ];
    }

    /** SMS text in the order's locale (tenant template messages.box_office_sms). */
    public function smsText(BoxOfficeOrder $order, Tenant $tenant): string
    {
        $locale = in_array($order->locale, ['ar', 'en'], true) ? $order->locale : 'ar';
        $template = (string) ($tenant->setting("messages.box_office_sms.$locale") ?: $tenant->setting('messages.box_office_sms.en')
            ?: config("tenancy.defaults.messages.box_office_sms.$locale"));
        $event = Event::withoutTenantScope()->find($order->event_id);
        $minutes = max(1, (int) ceil(max(0, now()->diffInSeconds($order->expires_at, false)) / 60));

        return strtr($template, [
            ':app' => $tenant->localizedName($locale),
            ':tickets' => trans_choice('box_office.tickets', $order->quantity, ['count' => $order->quantity], $locale),
            ':event' => $event ? ($locale === 'ar' ? ($event->title_ar ?: $event->title) : $event->title) : '',
            ':amount' => $tenant->currency().' '.$tenant->formatMoney($order->total_minor),
            ':minutes' => trans_choice('box_office.minutes', $minutes, ['count' => $minutes], $locale),
            ':url' => $this->paymentUrl($order),
            ':reference' => $order->reference,
        ]);
    }

    public function typeName(string $key, string $locale): string
    {
        $line = trans("box_office.types.$key", [], $locale);

        return $line !== "box_office.types.$key" ? $line : Str::headline($key);
    }

    // ------------------------------------------------------------------

    /** Ticket types of the event this gate may sell (box office channel, gate's type list). */
    public function gateTypes(Event $event, Gate $gate): Collection
    {
        return $event->sellableTicketTypes(EventTicketType::CHANNEL_BOX_OFFICE)
            ->filter(fn (EventTicketType $t) => $gate->acceptsTicketType($t->key))->values();
    }

    private function typePayload(EventTicketType $t, string $date, Tenant $tenant, int $eventAvailable): array
    {
        $api = $t->toApi($date, $tenant, $eventAvailable);

        // docs/gate_sales_contract.md §3 names for the price.
        return $api + ['unit_price_minor' => $api['price_minor'], 'unit_price' => $api['price']];
    }

    private function eventPayload(Event $event, Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $venue = $event->mapLocation
            ? ($locale === 'ar' ? ($event->mapLocation->name_ar ?: $event->mapLocation->name) : $event->mapLocation->name)
            : ($locale === 'ar' ? ($event->location_ar ?: $event->location) : $event->location);

        return [
            'id' => $event->id,
            'title_text' => $locale === 'ar' ? ($event->title_ar ?: $event->title) : $event->title,
            'title' => ['en' => $event->title, 'ar' => $event->title_ar ?: $event->title],
            'start' => TenantTime::iso($event->start_time, $tenant),
            'end' => TenantTime::iso($event->end_time, $tenant),
            'venue_text' => $venue ?: null,
            'image_url' => Tenant::assetUrl($event->image_url),
            'currency' => $tenant->currency(),
        ];
    }

    private function sellableEvent(Tenant $tenant, Gate $gate, int $eventId, string $date): Event
    {
        $event = Event::active()
            ->when($gate->event_id, fn ($q) => $q->whereKey($gate->event_id))
            ->whereDate('start_time', '<=', $date)->whereDate('end_time', '>=', $date)
            ->find($eventId);
        if (! $event || $this->ended($event, $date, $tenant)) {
            throw new ApiException('EVENT_NOT_FOUND', __('box_office.errors.event_not_sellable'), 404);
        }

        return $event;
    }

    /** Selling today after the event's end (tenant wall-clock) is over. */
    private function ended(Event $event, string $date, Tenant $tenant): bool
    {
        if ($date !== GateDay::today($tenant) || ! $event->end_time) {
            return false;
        }

        return TenantTime::localNow($tenant) > $event->end_time->format('Y-m-d H:i:s');
    }

    /** Country code + local number, digits only (tenant `auth` settings), else INVALID_PHONE. */
    public function normalizePhone(Tenant $tenant, string $input): string
    {
        $cc = (string) $tenant->setting('auth.country_code', '968');
        $len = (int) $tenant->setting('auth.phone_length', 8);
        $digits = preg_replace('/\D/', '', $input);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        $local = strlen($digits) === strlen($cc) + $len && str_starts_with($digits, $cc) ? substr($digits, strlen($cc)) : $digits;
        // Omani mobiles start with 7 or 9.
        $mobile = $cc !== '968' || in_array($local[0] ?? '', ['7', '9'], true);
        if (strlen($local) !== $len || ! $mobile) {
            throw new ApiException('INVALID_PHONE', __('box_office.errors.invalid_phone'), 422, ['field' => 'buyer_phone']);
        }

        return $cc.$local;
    }

    /** The buyer's account by phone (users are global), created without a password they know. */
    private function buyer(string $phone, ?string $name): User
    {
        $name = filled($name) ? trim((string) $name) : null;
        $user = User::firstOrCreate(['phone_number' => $phone], [
            'name' => $name ?: 'User '.substr($phone, -4),
            'email' => null,
            'password' => Hash::make(Str::random(32)),
        ]);
        if ($name && ! $user->wasRecentlyCreated && preg_match('/^User \d{4}$/', (string) $user->name)) {
            $user->forceFill(['name' => $name])->save();
        }

        return $user;
    }

    private function dispatchSms(BoxOfficeOrder $order, Tenant $tenant): void
    {
        SendOtpSms::dispatch($order->buyer_phone, $this->smsText($order, $tenant), 'box_office');
        $order->forceFill(['sms_sent_at' => now(), 'sms_count' => (int) $order->sms_count + 1])->save();
    }

    private function findByKey(GateDevice $device, string $key): ?BoxOfficeOrder
    {
        return BoxOfficeOrder::where('device_id', $device->id)->where('idempotency_key', $key)->first();
    }

    private function assertSamePayload(BoxOfficeOrder $order, int $eventId, string $phone): BoxOfficeOrder
    {
        if ((int) $order->event_id !== $eventId || $order->buyer_phone !== $phone) {
            throw ApiException::idempotencyConflict();
        }

        return $order;
    }

    public function assertPending(BoxOfficeOrder $order): void
    {
        if ($order->status === BoxOfficeOrder::STATUS_EXPIRED) {
            throw new ApiException('ORDER_EXPIRED', __('box_office.errors.order_expired'), 410, ['status' => $order->status]);
        }
        if ($order->status !== BoxOfficeOrder::STATUS_PENDING) {
            throw new ApiException('ORDER_NOT_PENDING', __('box_office.errors.order_not_pending'), 409, ['status' => $order->status]);
        }
    }

    /** BO-YYMMDD-NNNN, sequential per tenant and gate day (retried on a concurrent clash). */
    private function saveWithReference(BoxOfficeOrder $order, Tenant $tenant, GateDevice $device, string $key): void
    {
        $prefix = 'BO-'.Carbon::parse(GateDay::today($tenant))->format('ymd').'-';
        for ($try = 1; ; $try++) {
            $last = BoxOfficeOrder::withoutTenantScope()->where('tenant_id', $tenant->id)->where('reference', 'like', $prefix.'%')->max('reference');
            $order->reference = $prefix.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
            try {
                DB::transaction(fn () => $order->save());

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5 || $this->findByKey($device, $key)) {
                    throw $e;
                }
            }
        }
    }
}
