<?php

namespace App\Services\Gate;

use App\Models\EventTicketType;
use App\Models\Gate;
use App\Models\Tenant;
use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Signed scan codes (docs/gate_signed_tickets.md §1).
 *
 * The ticket keeps its `qr_code`; what the visitor is shown is
 *
 *     {base}.S1.{ticket_id}.{event_id}.{type_id}.{admits}.{validity}.{key_day}.{sig}
 *
 * signed with Ed25519 by a key pair per tenant and day, derived from APP_KEY
 * and never stored. Gate devices get the public keys only, so an offline gate
 * can check a ticket sold after its last download but can never make one.
 * The base code stays the identity: every lookup strips the suffix.
 */
final class TicketSignature
{
    public const MARK = '.S1.';

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PATTERN = '/^(.+)\.S1\.(\d+)\.(\d+)\.(\d+)\.(\d+)\.(A|S|D\d{8})\.(\d{8})\.([A-Z2-7]{103})$/';

    /** Gates get the keys up to this many days ahead (offline for up to 2 days). */
    public const KEYS_AHEAD = 2;

    /** @var array<string, string> "tenant|epoch|day" => secret key (64 bytes) */
    private static array $secrets = [];

    /** @var array<int, ?Tenant> tenants of tickets signed in this request */
    private static array $tenants = [];

    /** Forget memoized tenants (tests; a long-running worker after a settings change). */
    public static function flush(): void
    {
        self::$tenants = [];
    }

    public static function epoch(Tenant $tenant): int
    {
        return max(1, (int) $tenant->setting('gate.signed_keys_epoch', 1));
    }

    /** 32-byte Ed25519 seed of a tenant's key day (YYYYMMDD). */
    public static function seed(int $tenantId, int $epoch, string $day): string
    {
        return hash_hmac('sha256', "ticket-sign|{$tenantId}|{$epoch}|{$day}", (string) config('app.key'), true);
    }

    public static function publicKey(int $tenantId, int $epoch, string $day): string
    {
        return sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(self::seed($tenantId, $epoch, $day)));
    }

    private static function secretKey(int $tenantId, int $epoch, string $day): string
    {
        return self::$secrets["{$tenantId}|{$epoch}|{$day}"] ??=
            sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(self::seed($tenantId, $epoch, $day)));
    }

    /** Key day (YYYYMMDD) of a ticket: the operating day it was issued. */
    public static function keyDay(Ticket $ticket, Tenant $tenant): string
    {
        $at = $ticket->created_at ? Carbon::instance($ticket->created_at) : Carbon::now();

        return str_replace('-', '', GateDay::dateFor($at, $tenant));
    }

    public static function validityField(Ticket $ticket): string
    {
        return match (EventTicketType::normalizeValidity($ticket->validity)) {
            EventTicketType::VALIDITY_ALL_DAYS => 'S',
            EventTicketType::VALIDITY_ANY_DAY => 'A',
            default => $ticket->booking_date ? 'D'.Carbon::parse($ticket->booking_date)->format('Ymd') : 'A',
        };
    }

    /**
     * What the QR encodes and the visitor is shown. Codes that already carry a
     * dot (offline sales, `OFF-…`, signed on the gate device) are returned as
     * they are, and so is everything when the tenant is unknown.
     */
    public static function scanCode(Ticket $ticket, ?Tenant $tenant = null): string
    {
        $base = (string) $ticket->qr_code;
        if (! $tenant && $ticket->tenant_id) {
            $tenant = array_key_exists($ticket->tenant_id, self::$tenants)
                ? self::$tenants[$ticket->tenant_id]
                : (self::$tenants[$ticket->tenant_id] = Tenant::find($ticket->tenant_id));
        }
        if ($base === '' || str_contains($base, '.') || ! $tenant) {
            return $base;
        }
        $epoch = self::epoch($tenant);
        $day = self::keyDay($ticket, $tenant);
        $body = implode('.', [
            $base, 'S1', (int) $ticket->id, (int) $ticket->event_id, (int) ($ticket->ticket_type_id ?? 0),
            max(1, (int) $ticket->max_check_ins), self::validityField($ticket), $day,
        ]);

        return $body.'.'.self::base32(sodium_crypto_sign_detached($body, self::secretKey($tenant->id, $epoch, $day)));
    }

    /** The base code of a scanned value: the signed suffix removed, anything else unchanged. */
    public static function base(string $code): string
    {
        $p = self::parse($code);

        return $p ? $p['base'] : $code;
    }

    /**
     * Splits a signed scan code (signature not checked).
     *
     * @return array{base: string, ticket_id: int, event_id: int, type_id: int, admits: int, validity: string, key_day: string, body: string, sig: string}|null
     */
    public static function parse(?string $code): ?array
    {
        $code = trim((string) $code);
        if (! str_contains($code, self::MARK) || ! preg_match(self::PATTERN, $code, $m)) {
            return null;
        }

        return [
            'base' => $m[1],
            'ticket_id' => (int) $m[2],
            'event_id' => (int) $m[3],
            'type_id' => (int) $m[4],
            'admits' => max(1, (int) $m[5]),
            'validity' => $m[6],
            'key_day' => $m[7],
            'body' => substr($code, 0, -104),
            'sig' => $m[8],
        ];
    }

    /** The parsed code when its signature is this tenant's (current epoch), else null. */
    public static function verify(Tenant $tenant, ?string $code): ?array
    {
        $p = self::parse($code);
        if (! $p) {
            return null;
        }
        $sig = self::base32Decode($p['sig']);
        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        return sodium_crypto_sign_verify_detached($sig, $p['body'], self::publicKey($tenant->id, self::epoch($tenant), $p['key_day'])) ? $p : null;
    }

    /**
     * Public keys a gate needs: every key day from the earliest issue day of
     * the tickets valid today in its scope through today + KEYS_AHEAD.
     *
     * @return array<string, string> YYYYMMDD => base64 public key
     */
    public static function publicKeys(Tenant $tenant, string $today, ?Gate $gate = null): array
    {
        $first = app(GateManifestService::class)->ticketsFor($today, $gate)->min('tickets.created_at');
        $start = $first ? Carbon::parse(GateDay::dateFor(Carbon::parse($first), $tenant)) : Carbon::parse($today);
        $end = Carbon::parse($today)->addDays(self::KEYS_AHEAD);
        if ($start->gt($end)) {
            $start = Carbon::parse($today);
        }
        $epoch = self::epoch($tenant);
        $keys = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $day = $d->format('Ymd');
            $keys[$day] = base64_encode(self::publicKey($tenant->id, $epoch, $day));
        }

        return $keys;
    }

    /** RFC 4648 base32, upper case, no padding. */
    public static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split($text) as $ch) {
            $i = strpos(self::BASE32, $ch);
            if ($i === false) {
                return '';
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
