<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * A paired scanner (Android POS, handheld, phone, tablet or a PC browser).
 *
 * The device itself is the API principal: pairing issues a Sanctum personal
 * access token for this model with the single ability `gate`. User routes
 * (`auth:sanctum`, provider `users`) never accept these tokens, and gate
 * routes (App\Http\Middleware\AuthenticateGateDevice) accept nothing else.
 * A gate staff member may additionally be "logged in" on the device
 * (staff_user_id) — scans are then attributed to them.
 */
class GateDevice extends Model
{
    use \App\Tenancy\BelongsToTenant, HasApiTokens;

    public const TYPES = ['android_pos', 'handheld', 'phone', 'tablet', 'web'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    /** Seconds since the last heartbeat/request for a device to count as online. */
    public const ONLINE_SECONDS = 120;

    protected $fillable = [
        'gate_id', 'name', 'device_type', 'model', 'platform', 'app_version', 'scanner_source',
        'status', 'battery', 'queue_size', 'last_seen_at', 'paired_at',
    ];

    protected $casts = [
        'battery' => 'integer',
        'queue_size' => 'integer',
        'last_seen_at' => 'datetime',
        'paired_at' => 'datetime',
        'revoked_at' => 'datetime',
        'staff_login_at' => 'datetime',
    ];

    public function gate()
    {
        return $this->belongsTo(Gate::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function scans()
    {
        return $this->hasMany(GateScan::class, 'device_id');
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /** online | idle (seen in the last 15 min) | offline | revoked */
    public function connectivity(): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }
        if (! $this->last_seen_at) {
            return 'offline';
        }
        $age = $this->last_seen_at->diffInSeconds(now(), true);

        return $age <= self::ONLINE_SECONDS ? 'online' : ($age <= 900 ? 'idle' : 'offline');
    }

    public function revoke(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'staff_user_id' => null,
            'staff_login_at' => null,
        ])->save();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_type' => $this->device_type,
            'model' => $this->model,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'status' => $this->status,
            'paired_at' => \App\Support\TenantTime::isoForTenantId($this->paired_at, $this->tenant_id),
        ];
    }
}
