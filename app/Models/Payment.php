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
    ];

    protected $casts = [
        'bank_muscat_response' => 'array',
        'metadata' => 'array',
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'refunded_at' => 'datetime',
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

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
