<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only wallet ledger row. Never update amounts; only `status` may
 * change (pending refund payout -> completed / reversed).
 */
class WalletTransaction extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const TYPES = ['topup', 'ticket_purchase', 'vendor_payment', 'deposit_hold', 'deposit_release',
        'deposit_forfeit', 'refund', 'refund_reversal', 'points_redemption', 'adjustment'];

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'amount_minor' => 'integer',
        'cash_delta_minor' => 'integer',
        'promo_delta_minor' => 'integer',
        'held_delta_minor' => 'integer',
        'balance_after_minor' => 'integer',
        'cash_after_minor' => 'integer',
        'promo_after_minor' => 'integer',
        'held_after_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $tx) {
            $tx->uuid ??= (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bucket(): string
    {
        return $this->meta['bucket'] ?? 'cash';
    }
}
