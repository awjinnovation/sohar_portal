<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One HTTP call to a payment gateway (each retry is its own row, sharing the
 * correlation_id). Request bodies are stored with secrets redacted; response
 * bodies are truncated. Append-only. Not tenant-scoped.
 */
class PaymentGatewayCall extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'payment_id', 'gateway', 'mode', 'operation', 'correlation_id', 'attempt', 'method', 'url',
        'request_body', 'response_status', 'response_body', 'outcome', 'error', 'duration_ms',
    ];

    protected $casts = [
        'request_body' => 'array',
        'attempt' => 'integer',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
    ];
}
