<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpSms;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Wallet;
use App\Tenancy\TenantContext;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Send OTP to phone number.
     *
     * Throttled (route middleware throttle:otp-send). The code is stored as
     * an HMAC, never returned to the client unless OTP_DEBUG=true outside
     * production.
     */
    public function sendOtp(Request $request, TenantContext $context)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]{8,15}$/'
        ]);

        $phoneNumber = $request->phone_number;
        $ttl = (int) config('auth.otp.ttl_minutes', 5);

        $otp = (string) random_int(100000, 999999);
        if (!app()->environment('production') && config('auth.otp.test_code')) {
            $otp = (string) config('auth.otp.test_code');
        }

        DB::table('otp_verifications')
            ->where('phone_number', $phoneNumber)
            ->where('is_used', false)
            ->delete();

        DB::table('otp_verifications')->insert([
            'phone_number' => $phoneNumber,
            'otp_code' => self::hashOtp($phoneNumber, $otp),
            'expires_at' => Carbon::now()->addMinutes($ttl),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $tenant = $context->get();
        $locale = app()->getLocale();
        $template = $tenant->setting("messages.otp_sms.$locale") ?? $tenant->setting('messages.otp_sms.en');
        $message = strtr((string) $template, [
            ':app' => $tenant->localizedName($locale),
            ':code' => $otp,
            ':minutes' => (string) $ttl,
        ]);
        SendOtpSms::dispatch($phoneNumber, $message);

        $response = [
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in' => $ttl * 60,
        ];
        if (config('auth.otp.debug') && !app()->environment('production')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    public static function hashOtp(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    /**
     * Verify OTP and login/register user (throttle:otp-verify).
     * The current code is invalidated after auth.otp.max_attempts failures.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp_code' => 'required_without:otp|string|size:6',
            'otp' => 'required_without:otp_code|string|size:6',
            'device_token' => 'nullable|string'
        ]);

        $phoneNumber = $request->phone_number;
        $otpCode = (string) ($request->otp_code ?? $request->otp);
        $maxAttempts = (int) config('auth.otp.max_attempts', 5);

        $otpRecord = DB::table('otp_verifications')
            ->where('phone_number', $phoneNumber)
            ->where('is_used', false)
            ->orderByDesc('id')
            ->first();

        if (!$otpRecord || Carbon::parse($otpRecord->expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
                'code' => 'OTP_INVALID',
            ], 401);
        }

        if ((int) $otpRecord->attempts >= $maxAttempts) {
            DB::table('otp_verifications')->where('id', $otpRecord->id)->update(['is_used' => true]);

            return response()->json([
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new code.',
                'code' => 'OTP_ATTEMPTS_EXCEEDED',
            ], 429);
        }

        if (!hash_equals($otpRecord->otp_code, self::hashOtp($phoneNumber, $otpCode))) {
            DB::table('otp_verifications')->where('id', $otpRecord->id)->increment('attempts');
            $remaining = max(0, $maxAttempts - ((int) $otpRecord->attempts + 1));

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
                'code' => 'OTP_INVALID',
                'data' => ['attempts_remaining' => $remaining],
            ], 401);
        }

        // Mark OTP as used (atomically: a concurrent verify cannot reuse it).
        $claimed = DB::table('otp_verifications')
            ->where('id', $otpRecord->id)
            ->where('is_used', false)
            ->update(['is_used' => true]);
        if ($claimed !== 1) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP', 'code' => 'OTP_INVALID'], 401);
        }

        // Users are global identities (phone); wallets/points are per tenant.
        $user = User::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'name' => 'User ' . substr($phoneNumber, -4),
                'email' => null,
                'password' => Hash::make(Str::random(32)),
                'phone_verified_at' => now()
            ]
        );

        if ($request->device_token) {
            $user->device_token = $request->device_token;
            $user->save();
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'preferred_language' => $user->preferred_language ?? 'en'
            ],
            'token' => $token
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'preferred_language' => $user->preferred_language ?? 'en',
                'created_at' => $user->created_at
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $request->user()->id,
            'preferred_language' => 'nullable|in:en,ar',
            'device_token' => 'nullable|string'
        ]);

        $user = $request->user();

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('preferred_language')) {
            $user->preferred_language = $request->preferred_language;
        }

        if ($request->has('device_token')) {
            $user->device_token = $request->device_token;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'preferred_language' => $user->preferred_language
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    }

    /**
     * Delete (anonymise) the account. Refused while any tenant wallet still
     * holds money, so balances are refunded first. Tickets/payments are kept
     * for accounting but no longer linked to personal data.
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $funded = Wallet::withoutTenantScope()->where('user_id', $user->id)
            ->where(fn ($q) => $q->where('balance_minor', '>', 0)->orWhere('held_minor', '>', 0))
            ->exists();
        if ($funded) {
            return response()->json([
                'success' => false,
                'message' => 'Please request a refund of your wallet balance before deleting your account.',
                'code' => 'WALLET_NOT_EMPTY',
            ], 409);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Deleted user',
                'email' => null,
                'phone_number' => null,
                'device_token' => null,
                'password' => Hash::make(Str::random(40)),
            ])->save();
        });

        return response()->json(['success' => true, 'message' => 'Account deleted']);
    }
}
