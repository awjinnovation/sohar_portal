<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Models\BoxOfficeOrder;
use App\Models\EventTicketType;
use App\Models\GateDevice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Payments\PaymentRecorder;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gate sales made with no connection (docs/gate_sales_contract.md §10).
 *
 * The device sells on the card terminal, makes the tickets itself
 * (OfflineSaleCodes), prints them and keeps the sale in its SQLite database.
 * When the connection returns it sends the sale (POST /gate/sales/offline):
 * `sync()` records a paid card terminal order, its payment and the tickets
 * with the device's numbers and codes. A visitor's ticket may reach a scanner
 * first: `materialize()` then creates the ticket from its signed code, and
 * the sale attaches to it later.
 *
 * Offline sales have no buyer phone and send no SMS; their tickets belong to
 * the tenant's "gate visitor" account. The money was taken already, so a sale
 * is never refused for capacity or a changed price: a price that differs from
 * the server's, or a sale without the terminal approval code, is flagged for
 * review against the terminal's settlement report.
 */
class OfflineSaleService
{
    public function __construct(private BoxOfficeService $boxOffice, private PaymentRecorder $recorder) {}

    /**
     * @param  array{idempotency_key: string, sold_at: string, booking_date: string, event_id: int, staff_id?: ?int,
     *               approval_code?: ?string, total_minor: int, lines: list<array{ticket_type_id: int, quantity: int, unit_price_minor: int}>,
     *               tickets: list<array{number: string, code: string, ticket_type_id: int}>}  $sale
     * @return array{order: BoxOfficeOrder, created: bool}
     */
    public function sync(Tenant $tenant, GateDevice $device, array $sale): array
    {
        $key = (string) $sale['idempotency_key'];
        if ($existing = $this->findByKey($device, $key)) {
            return ['order' => $existing, 'created' => false];
        }
        $gate = $device->gate ?? throw new ApiException('GATE_CANNOT_SELL', __('box_office.errors.no_gate'), 403);
        $date = (string) $sale['booking_date'];

        // Every ticket: signed by this tenant for that day, made by this device at its gate.
        $tickets = [];
        foreach ($sale['tickets'] as $t) {
            $p = OfflineSaleCodes::verify($tenant, $t['code']);
            if (! $p || $p['number'] !== strtoupper($t['number']) || $p['device_id'] !== (int) $device->id
                || strtoupper($p['gate_code']) !== strtoupper((string) $gate->code) || $p['date'] !== $date
                || $p['type_id'] !== (int) $t['ticket_type_id']) {
                throw new ApiException('OFFLINE_CODE_INVALID', __('box_office.errors.offline_code_invalid', ['number' => $t['number']]), 422);
            }
            $tickets[] = $p;
        }

        $types = EventTicketType::whereIn('id', collect($sale['lines'])->pluck('ticket_type_id'))->get()->keyBy('id');
        $lines = [];
        $priceChanged = false;
        foreach ($sale['lines'] as $l) {
            $type = $types->get((int) $l['ticket_type_id']);
            if (! $type || (int) $type->event_id !== (int) $sale['event_id']) {
                throw new ApiException('OFFLINE_TYPE_UNKNOWN', __('box_office.errors.offline_type_unknown'), 422);
            }
            $count = collect($tickets)->where('type_id', $type->id)->count();
            if ($count !== (int) $l['quantity']) {
                throw new ApiException('OFFLINE_SALE_INCOMPLETE', __('box_office.errors.offline_sale_incomplete'), 422);
            }
            $priceChanged = $priceChanged || (int) $l['unit_price_minor'] !== (int) $type->price_minor;
            $lines[] = [
                'type' => $type, 'ticket_type_id' => $type->id, 'key' => $type->key, 'name' => $type->name,
                'quantity' => (int) $l['quantity'], 'unit_price_minor' => (int) $l['unit_price_minor'],
                'line_total_minor' => (int) $l['unit_price_minor'] * (int) $l['quantity'],
                'admits' => max(1, (int) $type->admits), 'validity' => $type->validityValue(),
            ];
        }
        if (count($tickets) !== collect($lines)->sum('quantity')) {
            throw new ApiException('OFFLINE_SALE_INCOMPLETE', __('box_office.errors.offline_sale_incomplete'), 422);
        }

        $total = collect($lines)->sum('line_total_minor');
        $soldAt = $this->soldAt($sale['sold_at'] ?? null);
        $approval = filled($sale['approval_code'] ?? null) ? strtoupper(trim((string) $sale['approval_code'])) : null;
        $reviewReason = match (true) {
            $total !== (int) $sale['total_minor'] => 'offline_total_mismatch',
            $priceChanged => 'offline_price_changed',
            $approval === null => 'offline_no_approval_code',
            default => null,
        };
        $staffId = $this->staffId($tenant, $sale['staff_id'] ?? null) ?? $device->staff_user_id;
        $single = count($lines) === 1;
        $dated = collect($lines)->contains(fn ($l) => $l['validity'] === EventTicketType::VALIDITY_DATE);

        try {
            $order = DB::transaction(function () use ($tenant, $device, $gate, $sale, $key, $date, $lines, $tickets, $total, $soldAt, $approval, $reviewReason, $staffId, $single, $dated) {
                $visitor = $this->visitor($tenant);
                $decimals = $tenant->currencyDecimals();

                $order = new BoxOfficeOrder([
                    'gate_id' => $gate->id, 'device_id' => $device->id, 'staff_user_id' => $staffId, 'buyer_user_id' => $visitor->id,
                    'buyer_phone' => '', 'buyer_name' => null, 'event_id' => (int) $sale['event_id'], 'booking_date' => $date,
                    'ticket_type' => $single ? $lines[0]['key'] : 'mixed',
                    'quantity' => collect($lines)->sum('quantity'), 'unit_price_minor' => $single ? $lines[0]['unit_price_minor'] : 0,
                    'subtotal_minor' => $total, 'deposit_minor' => 0, 'total_minor' => $total, 'currency' => $tenant->currency(),
                    'status' => BoxOfficeOrder::STATUS_PAID, 'payment_method' => BoxOfficeOrder::METHOD_POS,
                    'public_token' => Str::random(32), 'locale' => app()->getLocale() === 'en' ? 'en' : 'ar',
                    'expires_at' => $soldAt, 'paid_at' => $soldAt, 'idempotency_key' => $key, 'needs_review' => $reviewReason !== null,
                    'meta' => array_filter([
                        'offline' => true, 'sold_at' => $soldAt->toIso8601String(), 'approval_code' => $approval,
                        'review_reason' => $reviewReason, 'numbers' => array_column($tickets, 'number'),
                    ], fn ($v) => $v !== null),
                ]);
                $order->offline = true;
                $order->synced_at = now();
                $order->tenant_id = $tenant->id;
                $this->boxOffice->saveWithReference($order, $tenant, $device, $key, $date);

                foreach ($lines as $l) {
                    $order->lines()->create(collect($l)->except('type')->all());
                }

                $payment = new Payment([
                    'user_id' => $visitor->id, 'transaction_id' => 'OFF'.strtoupper(Str::random(13)),
                    'payment_type' => Payment::TYPE_TICKET, 'method' => Payment::METHOD_POS, 'gateway' => null,
                    'payable_id' => (int) $sale['event_id'], 'payable_type' => \App\Models\Event::class,
                    'amount' => Money::format($total, $decimals), 'amount_minor' => $total, 'currency' => $tenant->currency(),
                    'status' => 'completed', 'idempotency_key' => 'offline:'.$device->id.':'.$key,
                    'paid_at' => $soldAt, 'fulfilled_at' => now(),
                    'needs_review' => $reviewReason !== null, 'review_reason' => $reviewReason,
                    'metadata' => [
                        'event_id' => (int) $sale['event_id'], 'quantity' => $order->quantity, 'ticket_type' => $order->ticket_type,
                        'lines' => array_map(fn ($l) => collect($l)->except('type')->all(), $lines),
                        'booking_date' => $dated ? $date : null, 'currency_decimals' => $decimals,
                        'subtotal_minor' => $total, 'tickets_minor' => $total, 'deposit_minor' => 0,
                        'box_office_order_id' => $order->id, 'offline' => true, 'approval_code' => $approval,
                        'device_id' => $device->id, 'gate_id' => $gate->id, 'sold_at' => $soldAt->toIso8601String(),
                    ],
                ]);
                $payment->tenant_id = $tenant->id;
                $payment->save();

                foreach ($tickets as $p) {
                    $line = collect($lines)->firstWhere('ticket_type_id', $p['type_id']);
                    $this->saveTicket($tenant, $p, $line['type'], $visitor, [
                        'transaction_id' => $payment->transaction_id,
                        'price' => Money::format($line['unit_price_minor'], $decimals),
                        'purchase_date' => $soldAt,
                    ]);
                }

                $order->payment_id = $payment->id;
                $order->save();

                $this->recorder->record($payment, 'created', 'gate', array_filter([
                    'type' => $payment->payment_type, 'method' => Payment::METHOD_POS, 'amount_minor' => $total,
                    'box_office_order_id' => $order->id, 'offline' => true, 'approval_code' => $approval,
                ], fn ($v) => $v !== null), null, 'completed');

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same sale sent twice at once.
            if ($existing = $this->findByKey($device, $key)) {
                return ['order' => $existing, 'created' => false];
            }
            throw $e;
        }

        return ['order' => $order->fresh(['lines']), 'created' => true];
    }

    /**
     * A signed offline ticket scanned before its sale reached the server:
     * created from its code, the sale attaches to it when it arrives.
     */
    public function materialize(Tenant $tenant, array $p): ?Ticket
    {
        $type = EventTicketType::find($p['type_id']);
        if (! $type) {
            return null;
        }
        try {
            return DB::transaction(fn () => $this->saveTicket($tenant, $p, $type, $this->visitor($tenant), [
                'price' => Money::format((int) $type->price_minor, $tenant->currencyDecimals()),
                'purchase_date' => now(),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            return Ticket::where('ticket_number', $p['number'])->first();
        }
    }

    /** The tenant's ticket for this code: updated when it exists (scanned first), else created. */
    private function saveTicket(Tenant $tenant, array $p, EventTicketType $type, User $visitor, array $attrs): Ticket
    {
        $dated = $type->validityValue() === EventTicketType::VALIDITY_DATE;
        $ticket = Ticket::where('ticket_number', $p['number'])->lockForUpdate()->first();
        if ($ticket) {
            $ticket->forceFill(array_filter($attrs, fn ($v) => $v !== null))->save();

            return $ticket;
        }
        $ticket = new Ticket($attrs + [
            'ticket_number' => $p['number'],
            'qr_code' => $p['code'],
            'user_id' => $visitor->id,
            'event_id' => $type->event_id,
            'booking_date' => $dated ? $p['date'] : null,
            'quantity' => 1,
            'ticket_type' => $type->key,
            'ticket_type_id' => $type->id,
            'validity' => $type->validityValue(),
            'max_check_ins' => $p['admits'],
            'status' => 'active',
            'currency' => $tenant->currency(),
            'holder_name' => __('box_office.offline_holder'),
        ]);
        $ticket->tenant_id = $tenant->id;
        $ticket->save();

        return $ticket;
    }

    /** The tenant's "gate visitor" account: owner of tickets sold with no buyer. */
    private function visitor(Tenant $tenant): User
    {
        return User::firstOrCreate(
            ['email' => 'gate-visitor+'.$tenant->id.'@offline.invalid'],
            ['name' => 'Gate visitor', 'password' => Hash::make(Str::random(40))],
        );
    }

    private function staffId(Tenant $tenant, mixed $id): ?int
    {
        if (! $id) {
            return null;
        }
        $user = User::find((int) $id);

        return $user && BoxOfficeService::canSell($user) ? $user->id : null;
    }

    /** The device's sale time, kept within the last 7 days and not in the future. */
    private function soldAt(?string $value): Carbon
    {
        try {
            $at = $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            $at = now();
        }

        return $at->isFuture() ? now() : ($at->lt(now()->subDays(7)) ? now()->subDays(7) : $at);
    }

    private function findByKey(GateDevice $device, string $key): ?BoxOfficeOrder
    {
        return BoxOfficeOrder::where('device_id', $device->id)->where('idempotency_key', $key)->first();
    }
}
