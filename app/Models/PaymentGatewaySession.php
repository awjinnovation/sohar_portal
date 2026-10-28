<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A checkout session created at the gateway for a payment (Thawani). A
 * payment can have several (a new one when the previous expired or a
 * retried create succeeded twice); every one is checked when reconciling.
 *
 * Not tenant-scoped (written from webhooks and jobs): always filter by
 * payment_id / tenant_id explicitly.
 */
class PaymentGatewaySession extends Model
{
    public const OPEN = 'open';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id', 'payment_id', 'gateway', 'mode', 'session_id', 'client_reference_id', 'invoice', 'status',
        'amount_minor', 'currency', 'checkout_url', 'expires_at', 'paid_at', 'last_checked_at', 'data',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'data' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class)->withoutGlobalScopes();
    }

    /**
     * Still payable: open and not past the gateway's expiry (with a safety
     * margin). Without an expiry from the gateway, only for an hour.
     */
    public function isReusable(int $marginSeconds = 120): bool
    {
        if ($this->status !== self::OPEN) {
            return false;
        }

        return $this->expires_at !== null
            ? $this->expires_at->isAfter(now()->addSeconds($marginSeconds))
            : $this->created_at?->isAfter(now()->subHour()) ?? false;
    }
}
