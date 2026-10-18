<?php

namespace App\Support\Landing;

/**
 * Signed "rendered at" token for the public demo form: "<unix>.<hmac>".
 * Lets the server reject submissions that arrive faster than a human could
 * fill the form, without trusting a client-side clock.
 */
class FormTimestamp
{
    public static function issue(?int $at = null): string
    {
        $at ??= time();

        return $at.'.'.self::sign($at);
    }

    /** Seconds since the token was issued, or null when missing / forged. */
    public static function age(?string $token): ?int
    {
        if (! is_string($token) || ! preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        if (! hash_equals(self::sign((int) $m[1]), $m[2])) {
            return null;
        }

        return time() - (int) $m[1];
    }

    private static function sign(int $at): string
    {
        return hash_hmac('sha256', 'demo-form|'.$at, (string) config('app.key'));
    }
}
