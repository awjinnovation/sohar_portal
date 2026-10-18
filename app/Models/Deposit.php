<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const TYPES = ['wristband', 'cup', 'locker', 'equipment', 'other'];

    protected $fillable = ['wallet_id', 'user_id', 'vendor_id', 'event_id', 'payment_id', 'type', 'label',
        'amount_minor', 'status', 'idempotency_key', 'created_by', 'released_by', 'released_at'];

    protected $casts = [
        'amount_minor' => 'integer',
        'released_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
