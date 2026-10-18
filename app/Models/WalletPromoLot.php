<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One promo credit (points redemption, top-up bonus, goodwill...) and how
 * much of it is left. Spending consumes lots FIFO (soonest expiry first,
 * then non-expiring); wallet:expire-promo debits what is left after
 * expires_at. Promo credited before lots existed has no lot and never
 * expires.
 */
class WalletPromoLot extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'amount_minor' => 'integer',
        'remaining_minor' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
