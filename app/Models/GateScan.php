<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One scan at a gate (online, synced offline, or from the legacy staff
 * screen). Append-only; `idempotency_key` is unique so client retries and
 * re-synced batches are recorded once.
 *
 * result: valid|reentry_ok|already_used|invalid|wrong_event|wrong_date|
 *         not_yet_open|cancelled|refunded|wrong_gate
 * direction: in|out · source: camera|hardware|wedge|manual
 */
class GateScan extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'gate_id', 'device_id', 'staff_user_id', 'ticket_id', 'code_hash', 'result', 'direction',
        'source', 'scanned_at', 'received_at', 'offline', 'idempotency_key', 'conflict',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'received_at' => 'datetime',
        'offline' => 'boolean',
        'conflict' => 'boolean',
    ];

    public function gate()
    {
        return $this->belongsTo(Gate::class);
    }

    public function device()
    {
        return $this->belongsTo(GateDevice::class, 'device_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isAdmission(): bool
    {
        return $this->direction === 'in' && in_array($this->result, ['valid', 'reentry_ok'], true);
    }
}
