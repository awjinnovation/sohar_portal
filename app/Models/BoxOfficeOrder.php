<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A ticket order sold at a gate (docs/gate_sales_contract.md §2).
 *
 * Gate staff create it with the visitor's phone; the visitor pays through
 * the public /pay/{public_token} page (existing gateway flow). While
 * `pending` and not past `expires_at` it holds its seats. On payment the
 * tickets are issued to the buyer by TicketIssuer and the order becomes
 * `paid` (App\Services\Gate\BoxOfficeService::markPaid).
 *
 * @property string $status pending|paid|expired|cancelled|paid_unfulfilled|refunded
 */
class BoxOfficeOrder extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /** Paid after it expired / was cancelled and no seats were left: no tickets, the payment must be refunded. */
    public const STATUS_PAID_UNFULFILLED = 'paid_unfulfilled';

    /** Its payment was refunded (tickets, if any, cancelled). */
    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_EXPIRED, self::STATUS_CANCELLED,
        self::STATUS_PAID_UNFULFILLED, self::STATUS_REFUNDED];

    protected $fillable = [
        'gate_id', 'device_id', 'staff_user_id', 'buyer_user_id', 'reference', 'buyer_phone', 'buyer_name',
        'event_id', 'booking_date', 'ticket_type', 'quantity', 'unit_price_minor', 'subtotal_minor', 'deposit_minor',
        'total_minor', 'currency', 'status', 'payment_id', 'public_token', 'locale', 'expires_at', 'paid_at',
        'cancelled_at', 'sms_sent_at', 'sms_count', 'idempotency_key', 'meta',
    ];

    protected $hidden = ['public_token'];

    protected $casts = [
        'booking_date' => 'date',
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'subtotal_minor' => 'integer',
        'deposit_minor' => 'integer',
        'total_minor' => 'integer',
        'sms_count' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function gate()
    {
        return $this->belongsTo(Gate::class);
    }

    public function device()
    {
        return $this->belongsTo(GateDevice::class, 'device_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function lines()
    {
        return $this->hasMany(BoxOfficeOrderLine::class, 'order_id')->orderBy('id');
    }

    /** Tickets issued for the paying payment (empty until paid; none for paid_unfulfilled). */
    public function issuedTickets(): Collection
    {
        $tx = $this->payment_id ? Payment::withoutTenantScope()->whereKey($this->payment_id)->value('transaction_id') : null;

        return $tx && in_array($this->status, [self::STATUS_PAID, self::STATUS_REFUNDED], true)
            ? Ticket::withoutTenantScope()->where('transaction_id', $tx)->orderBy('id')->get()
            : new \Illuminate\Database\Eloquent\Collection();
    }

    /** Orders that currently hold seats: pending and not past their expiry. */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Pending but past its expiry (the expiry job has not run yet). */
    public function isDue(): bool
    {
        return $this->isPending() && $this->expires_at && $this->expires_at->lte(now());
    }

    public function secondsLeft(): int
    {
        if (! $this->isPending() || ! $this->expires_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->expires_at, false)));
    }

    /** "+968 •••• 4567" (country code from the tenant's sign-in settings). */
    public function maskedPhone(?Tenant $tenant = null): string
    {
        $cc = (string) ($tenant?->setting('auth.country_code', '968') ?? '968');
        $digits = preg_replace('/\D/', '', (string) $this->buyer_phone);
        $local = str_starts_with($digits, $cc) && strlen($digits) > strlen($cc) + 4 ? substr($digits, strlen($cc)) : $digits;
        if (strlen($local) < 4) {
            return '+'.$digits;
        }
        $hidden = str_repeat('•', max(0, strlen($local) - 4));

        return '+'.$cc.' '.$hidden.' '.substr($local, -4);
    }
}
