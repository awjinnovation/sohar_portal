<?php

namespace App\Services\Auth;

use App\Jobs\SendOtpSms;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Phone sign-in codes (otp_verifications), shared by the app login
 * (Api\AuthController) and the event booking page
 * (Web\VisitorBookingController), so both draw on the same codes and the
 * same per-code attempt limit.
 *
 * Codes are stored as an HMAC. The store-review number
 * (auth.otp.review_phone) gets the fixed auth.otp.review_code in every
 * environment and no SMS. Outside production, OTP_TEST_CODE gives every
 * number that code and no SMS.
 */
class OtpService
{
    public const INVALID = 'OTP_INVALID';

    public const ATTEMPTS_EXCEEDED = 'OTP_ATTEMPTS_EXCEEDED';

    /**
     * Replaces any unused code of the phone with a new one and texts it.
     *
     * @return array{expires_in:int, code:string} `code` is for OTP_DEBUG responses only.
     */
    public function send(string $phone, Tenant $tenant, string $locale): array
    {
        $ttl = (int) config('auth.otp.ttl_minutes', 5);

        $otp = (string) random_int(100000, 999999);
        $isTestCode = ! app()->environment('production') && config('auth.otp.test_code');
        if ($isTestCode) {
            $otp = (string) config('auth.otp.test_code');
        }
        $isReviewer = self::isReviewPhone($phone);
        if ($isReviewer) {
            $otp = (string) config('auth.otp.review_code');
        }

        DB::table('otp_verifications')
            ->where('phone_number', $phone)
            ->where('is_used', false)
            ->delete();

        DB::table('otp_verifications')->insert([
            'phone_number' => $phone,
            'otp_code' => self::hash($phone, $otp),
            'expires_at' => Carbon::now()->addMinutes($ttl),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $template = $tenant->setting("messages.otp_sms.$locale") ?? $tenant->setting('messages.otp_sms.en');
        $message = strtr((string) $template, [
            ':app' => $tenant->localizedName($locale),
            ':code' => $otp,
            ':minutes' => (string) $ttl,
        ]);
        if (! $isReviewer && ! $isTestCode) {
            SendOtpSms::dispatch($phone, $message);
        }

        return ['expires_in' => $ttl * 60, 'code' => $otp];
    }

    /**
     * Checks the phone's latest unused code and claims it (single use). The
     * code is invalidated after auth.otp.max_attempts failures.
     *
     * @return array{ok:bool, code?:string, attempts_remaining?:int} `code` is INVALID or ATTEMPTS_EXCEEDED;
     *                                                               `attempts_remaining` only after a wrong guess.
     */
    public function verify(string $phone, string $code): array
    {
        $maxAttempts = (int) config('auth.otp.max_attempts', 5);

        $record = DB::table('otp_verifications')
            ->where('phone_number', $phone)
            ->where('is_used', false)
            ->orderByDesc('id')
            ->first();

        if (! $record || Carbon::parse($record->expires_at)->isPast()) {
            return ['ok' => false, 'code' => self::INVALID];
        }

        if ((int) $record->attempts >= $maxAttempts) {
            DB::table('otp_verifications')->where('id', $record->id)->update(['is_used' => true]);

            return ['ok' => false, 'code' => self::ATTEMPTS_EXCEEDED];
        }

        if (! hash_equals($record->otp_code, self::hash($phone, $code))) {
            DB::table('otp_verifications')->where('id', $record->id)->increment('attempts');

            return ['ok' => false, 'code' => self::INVALID, 'attempts_remaining' => max(0, $maxAttempts - ((int) $record->attempts + 1))];
        }

        // Mark the code used atomically: a concurrent verify cannot reuse it.
        $claimed = DB::table('otp_verifications')
            ->where('id', $record->id)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return $claimed === 1 ? ['ok' => true] : ['ok' => false, 'code' => self::INVALID];
    }

    /** The phone's account (users are global identities; wallets/points are per tenant), created on first sign-in. */
    public function userFor(string $phone): User
    {
        return User::firstOrCreate(
            ['phone_number' => $phone],
            [
                'name' => 'User '.substr($phone, -4),
                'email' => null,
                'password' => Hash::make(Str::random(32)),
                'phone_verified_at' => now(),
            ]
        );
    }

    public static function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    /** Only when both review settings are set and the code is exactly 6 digits. */
    private static function isReviewPhone(string $phone): bool
    {
        $reviewPhone = (string) config('auth.otp.review_phone');

        return $reviewPhone !== ''
            && preg_match('/^\d{6}$/', (string) config('auth.otp.review_code')) === 1
            && hash_equals($reviewPhone, $phone);
    }
}
