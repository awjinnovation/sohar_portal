<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\AuthController;
use App\Models\GateDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff login on a paired scanner (contract §B2 `POST /gate/staff/login`).
 *
 * Reuses the app's OTP flow: the scanner calls `POST /api/v1/auth/send-otp`
 * {phone_number}, then sends {phone, otp} here. The code is checked exactly
 * like /auth/verify-otp (HMAC, expiry, attempt limit, single use) but no
 * user is created and no user token is issued: the staff member is bound to
 * the device until logout. Allowed: platform super-admins and users holding,
 * in the device's tenant, the admin/staff role or the `validate tickets`
 * permission (so vendor-staff qualify once granted that permission).
 */
class GateStaffAuthenticator
{
    public function login(GateDevice $device, string $phone, string $otp): User
    {
        $phone = preg_replace('/\D/', '', $phone);
        $this->verifyOtp($phone, $otp);

        $user = User::where('phone_number', $phone)->first();
        if (! $user || ! self::isGateStaff($user)) {
            throw new ApiException('NOT_STAFF', __('gate.errors.not_staff'), 403);
        }

        $device->forceFill(['staff_user_id' => $user->id, 'staff_login_at' => now()])->save();

        return $user;
    }

    public function logout(GateDevice $device): void
    {
        $device->forceFill(['staff_user_id' => null, 'staff_login_at' => null])->save();
    }

    /** Same rule as the `staff` API middleware, in the current tenant (team). */
    public static function isGateStaff(User $user): bool
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user->isSuperAdmin()
            || $user->hasAnyRole(['admin', 'staff'])
            || $user->checkPermissionTo('validate tickets');
    }

    private function verifyOtp(string $phone, string $otp): void
    {
        $invalid = fn (array $extra = []) => new ApiException('OTP_INVALID', 'Invalid or expired OTP', 401, $extra);
        $max = (int) config('auth.otp.max_attempts', 5);

        $record = DB::table('otp_verifications')->where('phone_number', $phone)->where('is_used', false)->orderByDesc('id')->first();
        if (! $record || Carbon::parse($record->expires_at)->isPast()) {
            throw $invalid();
        }
        if ((int) $record->attempts >= $max) {
            DB::table('otp_verifications')->where('id', $record->id)->update(['is_used' => true]);
            throw new ApiException('OTP_ATTEMPTS_EXCEEDED', 'Too many incorrect attempts. Please request a new code.', 429);
        }
        if (! hash_equals($record->otp_code, AuthController::hashOtp($phone, $otp))) {
            DB::table('otp_verifications')->where('id', $record->id)->increment('attempts');
            throw $invalid(['attempts_remaining' => max(0, $max - ((int) $record->attempts + 1))]);
        }
        $claimed = DB::table('otp_verifications')->where('id', $record->id)->where('is_used', false)->update(['is_used' => true]);
        if ($claimed !== 1) {
            throw $invalid();
        }
    }
}
