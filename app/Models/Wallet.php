<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Closed-loop stored value per (user, tenant). Two buckets (contract §6a):
 * cash (top-ups, refundable) and promo (points redemption, bonuses,
 * goodwill — not refundable). balance = cash + promo; held = deposits.
 * Mutated only through App\Services\Wallet\WalletService.
 */
class Wallet extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FROZEN = 'frozen';

    protected $fillable = ['user_id', 'currency', 'status'];

    protected $casts = [
        'balance_minor' => 'integer',
        'cash_minor' => 'integer',
        'promo_minor' => 'integer',
        'held_minor' => 'integer',
    ];

    protected $attributes = [
        'balance_minor' => 0,
        'cash_minor' => 0,
        'promo_minor' => 0,
        'held_minor' => 0,
        'status' => self::STATUS_ACTIVE,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    public function availableMinor(): int
    {
        return max(0, $this->balance_minor - $this->held_minor);
    }

    /** Refundable = cash − held deposits (promo is never refundable). */
    public function refundableMinor(): int
    {
        return max(0, min($this->cash_minor, $this->cash_minor - $this->held_minor));
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }
}
