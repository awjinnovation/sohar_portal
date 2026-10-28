<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Timeline of a payment: every state change and gateway step, with where it
 * came from (api, redirect, webhook, reconciler, poll, admin, scheduler,
 * system). Append-only; written by PaymentRecorder. Not tenant-scoped.
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'payment_id', 'type', 'from_status', 'to_status', 'source', 'actor_id', 'data'];

    protected $casts = ['data' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
