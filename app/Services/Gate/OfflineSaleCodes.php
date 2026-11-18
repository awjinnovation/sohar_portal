<?php

namespace App\Services\Gate;

use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Ticket codes made on a gate device with no connection (docs/gate_sales_contract.md §10).
 *
 * Ticket number (printed, unique platform-wide):
 *
 *     OFF-{gate code}-{device id}-{sequence}          OFF-A-9-0007
 *
 * The sequence is the device's own counter, so a number is unique to its gate
 * and device without asking the server. QR code: the number, the ticket type,
 * the people it admits and the operating day, signed:
 *
 *     OFF-A-9-0007.12.1.20261118.K3QZ7M2XPA4D
 *
 * The signature (HMAC-SHA256, 12 base32 characters) uses a key per tenant and
 * operating day, derived from APP_KEY. Devices get the keys of today and the
 * next days in GET /gate/session (settings.sales.offline_keys), so any device
 * of the tenant checks an offline ticket with no connection, and the server
 * checks it when the sale (or a scan) arrives. A key opens one day only.
 */
final class OfflineSaleCodes
{
    public const PREFIX = 'OFF-';

    /** Operating days a device gets keys for: today and the next 2 (offline for up to 3 days). */
    public const KEY_DAYS = 3;

    private const SIG_LENGTH = 12;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function looksOffline(?string $code): bool
    {
        return $code !== null && str_starts_with(strtoupper(trim($code)), self::PREFIX);
    }

    /** Key of a tenant's operating day (64 hex characters). */
    public static function dayKey(Tenant|int $tenant, string $date): string
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return hash_hmac('sha256', "gate-offline-sale|{$id}|{$date}", (string) config('app.key'));
    }

    /** @return array<string, string> operating day (Y-m-d) => key, from today */
    public static function keys(Tenant $tenant): array
    {
        $today = Carbon::parse(GateDay::today($tenant));
        $keys = [];
        for ($i = 0; $i < self::KEY_DAYS; $i++) {
            $date = $today->copy()->addDays($i)->toDateString();
            $keys[$date] = self::dayKey($tenant, $date);
        }

        return $keys;
    }

    public static function number(string $gateCode, int $deviceId, int $seq): string
    {
        return self::PREFIX.strtoupper($gateCode).'-'.$deviceId.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** The signed QR code (what the device makes offline; used by tests). */
    public static function make(Tenant|int $tenant, string $gateCode, int $deviceId, int $seq, int $typeId, int $admits, string $date): string
    {
        $body = self::number($gateCode, $deviceId, $seq).'.'.$typeId.'.'.max(1, $admits).'.'.str_replace('-', '', $date);

        return $body.'.'.self::sign(self::dayKey($tenant, $date), $body);
    }

    /** First 12 base32 characters of HMAC-SHA256(key bytes, body). */
    public static function sign(string $keyHex, string $body): string
    {
        $bits = '';
        foreach (str_split(hash_hmac('sha256', $body, (string) hex2bin($keyHex), true)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i < self::SIG_LENGTH; $i++) {
            $out .= self::BASE32[bindec(substr($bits, $i * 5, 5))];
        }

        return $out;
    }

    /**
     * Splits a code without checking the signature.
     *
     * @return array{number: string, gate_code: string, device_id: int, seq: int, type_id: int, admits: int, date: string, body: string, sig: string, code: string}|null
     */
    public static function parse(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));
        if (! preg_match('/^(OFF-[A-Z0-9\-]+-\d+-\d{4,})\.(\d+)\.(\d+)\.(\d{8})\.([A-Z2-7]{12})$/', $code, $m)) {
            return null;
        }
        // OFF-{code}-{device}-{seq}: the gate code may itself contain "-".
        $parts = explode('-', substr($m[1], strlen(self::PREFIX)));
        if (count($parts) < 3) {
            return null;
        }
        $seq = (int) array_pop($parts);
        $device = (int) array_pop($parts);
        $date = substr($m[4], 0, 4).'-'.substr($m[4], 4, 2).'-'.substr($m[4], 6, 2);
        if (! checkdate((int) substr($m[4], 4, 2), (int) substr($m[4], 6, 2), (int) substr($m[4], 0, 4))) {
            return null;
        }

        return [
            'number' => $m[1],
            'gate_code' => implode('-', $parts),
            'device_id' => $device,
            'seq' => $seq,
            'type_id' => (int) $m[2],
            'admits' => max(1, (int) $m[3]),
            'date' => $date,
            'body' => $m[1].'.'.$m[2].'.'.$m[3].'.'.$m[4],
            'sig' => $m[5],
            'code' => $code,
        ];
    }

    /** The parsed code when its signature is this tenant's, else null. */
    public static function verify(Tenant|int $tenant, ?string $code): ?array
    {
        $p = self::parse($code);
        if (! $p || $p['device_id'] < 1) {
            return null;
        }

        return hash_equals(self::sign(self::dayKey($tenant, $p['date']), $p['body']), $p['sig']) ? $p : null;
    }
}
