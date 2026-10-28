<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A refund sent back to the card through the gateway (one per payment:
 * Thawani refunds the full amount). Processed by ProcessGatewayRefund with
 * retries. Not tenant-scoped.
 */
class PaymentRefund extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'tenant_id', 'payment_id', 'gateway', 'gateway_payment_id', 'gateway_refund_id', 'amount_minor', 'reason',
        'status', 'attempts', 'last_error', 'requested_by', 'data', 'completed_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'attempts' => 'integer',
        'data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class)->withoutGlobalScopes();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
