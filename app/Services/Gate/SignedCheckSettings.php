<?php

namespace App\Services\Gate;

use App\Models\Gate;
use App\Models\Tenant;

/**
 * When an offline gate may admit a ticket by the system's signature
 * (docs/gate_signed_tickets.md §2). Tenant settings, edited in admin →
 * branding & settings (admin/gates/_offline-check-settings):
 *
 *   gate.signed_check       off | always | window
 *   gate.signed_check_from  HH:MM, tenant time (window start)
 *   gate.signed_check_to    HH:MM (window end; before `from` = past midnight)
 *   gate.signed_keys_epoch  key generation (TicketSignature), bumped by "Change signing keys now"
 *
 * Tickets are always signed; this only decides when gates use it.
 */
final class SignedCheckSettings
{
    public const MODES = ['off', 'always', 'window'];

    public static function mode(Tenant $tenant): string
    {
        $mode = (string) $tenant->setting('gate.signed_check', 'off');

        return in_array($mode, self::MODES, true) ? $mode : 'off';
    }

    public static function time(Tenant $tenant, string $key, string $default): string
    {
        $value = (string) $tenant->setting("gate.signed_check_$key", $default);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    /**
     * GET /gate/session → settings.signed_check. Keys only when the mode is on.
     *
     * @return array{mode: string, from: string, to: string, keys: array<string, string>|object}
     */
    public static function forSession(Tenant $tenant, string $today, ?Gate $gate): array
    {
        $mode = self::mode($tenant);
        $keys = $mode === 'off' ? [] : TicketSignature::publicKeys($tenant, $today, $gate);

        return [
            'mode' => $mode,
            'from' => self::time($tenant, 'from', '18:30'),
            'to' => self::time($tenant, 'to', '21:00'),
            // An empty map stays a JSON object.
            'keys' => $keys ?: (object) [],
        ];
    }
}
