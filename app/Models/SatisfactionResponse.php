<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One visitor answer to the satisfaction survey «راضي»
 * (docs/radi_satisfaction_contract.md §2).
 *
 * @property int $score 1–5
 * @property array|null $aspects {key: 1–5}
 * @property string $response_date tenant-local Y-m-d
 */
class SatisfactionResponse extends Model
{
    use \App\Tenancy\BelongsToTenant;

    /** Scores counted as satisfied for CSAT (share of 4–5). */
    public const SATISFIED_MIN = 4;

    protected $fillable = [
        'user_id', 'event_id', 'ticket_id', 'score', 'aspects', 'comment', 'contact_ok',
        'locale', 'platform', 'app_version', 'device_id', 'response_date', 'dedupe_key',
    ];

    protected $casts = [
        'score' => 'integer',
        'aspects' => 'array',
        'contact_ok' => 'boolean',
    ];

    protected $hidden = ['device_id', 'dedupe_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /** sha256 of the app's per-install id (the raw value is never stored). */
    public static function hashDevice(?string $deviceId): ?string
    {
        $deviceId = trim((string) $deviceId);

        return $deviceId === '' ? null : hash('sha256', config('app.key').'|satisfaction-device|'.$deviceId);
    }
}
