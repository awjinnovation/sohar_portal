<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const METHODS = ['original_payment', 'bank_transfer'];

    protected $fillable = ['wallet_id', 'user_id', 'amount_minor', 'fee_minor', 'method', 'status', 'reason',
        'bank_iban', 'bank_name', 'admin_note', 'decided_by', 'decided_at', 'paid_at', 'idempotency_key',
        'hold_transaction_id', 'reversal_transaction_id'];

    protected $casts = [
        'amount_minor' => 'integer',
        'fee_minor' => 'integer',
        'decided_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $hidden = ['bank_iban'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
