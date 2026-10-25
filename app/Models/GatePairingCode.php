<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-use, short-lived (10 min) code an admin shows to pair a scanner with a gate. */
class GatePairingCode extends Model
{
    use \App\Tenancy\BelongsToTenant;

    /** A–Z0–9 without the ambiguous 0/O, 1/I/L. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const TTL_MINUTES = 10;

    protected $fillable = ['gate_id', 'code', 'expires_at', 'used_at', 'device_id', 'created_by'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function gate()
    {
        return $this->belongsTo(Gate::class);
    }

    public function device()
    {
        return $this->belongsTo(GateDevice::class, 'device_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }
}
