<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A webhook received from a payment gateway, stored before it is processed
 * (raw body, selected headers, signature result). Identical deliveries are
 * one row (payload_hash) with `deliveries` counted. Not tenant-scoped.
 */
class PaymentWebhookEvent extends Model
{
    public const RECEIVED = 'received';
    public const PROCESSED = 'processed';
    public const IGNORED = 'ignored';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'payment_id', 'gateway', 'event_type', 'session_id', 'payload_hash', 'signature_valid', 'headers',
        'raw_body', 'status', 'deliveries', 'attempts', 'last_error', 'ip', 'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'headers' => 'array',
        'deliveries' => 'integer',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function payload(): array
    {
        $data = json_decode($this->raw_body, true);

        return is_array($data) ? $data : [];
    }
}
