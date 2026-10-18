<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTopup extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = ['wallet_id', 'user_id', 'payment_id', 'amount_minor', 'bonus_minor', 'status',
        'idempotency_key', 'wallet_transaction_id', 'completed_at'];

    protected $casts = [
        'amount_minor' => 'integer',
        'bonus_minor' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
