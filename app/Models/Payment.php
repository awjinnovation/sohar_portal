<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A checkout (ticket purchase or wallet top-up) paid through the gateway or
 * the wallet. Amounts: `amount_minor` is authoritative; `amount` (decimal)
 * is kept for backward compatibility with older app versions / reports.
 */
class Payment extends Model
{
    use \App\Tenancy\BelongsToTenant;
    use HasFactory;

    public const TYPE_TICKET = 'ticket';
    public const TYPE_TOPUP = 'topup';

    public const METHOD_GATEWAY = 'gateway';
    public const METHOD_WALLET = 'wallet';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'bank_muscat_order_id',
        'bank_muscat_tid',
        'payment_type',
        'method',
        'gateway',
        'gateway_session_id',
        'gateway_payment_id',
        'payable_id',
        'payable_type',
        'amount',
        'amount_minor',
        'currency',
        'status',
        'bank_muscat_response',
        'metadata',
        'payment_method',
        'idempotency_key',
        'paid_at',
        'fulfilled_at',
        'refunded_at',
        'next_check_at',
        'last_checked_at',
        'check_count',
        'needs_review',
        'review_reason',
    ];

    protected $casts = [
        'bank_muscat_response' => 'array',
        'metadata' => 'array',
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'next_check_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'check_count' => 'integer',
        'needs_review' => 'boolean',
    ];

    protected $hidden = ['bank_muscat_response'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payable()
    {
        return $this->morphTo();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'transaction_id', 'transaction_id');
    }

    public function gatewaySessions()
    {
        return $this->hasMany(PaymentGatewaySession::class)->latest('id');
    }

    public function events()
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    public function gatewayCalls()
    {
        return $this->hasMany(PaymentGatewayCall::class)->orderBy('id');
    }

    public function webhookEvents()
    {
        return $this->hasMany(PaymentWebhookEvent::class)->orderBy('id');
    }

    public function gatewayRefund()
    {
        return $this->hasOne(PaymentRefund::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
