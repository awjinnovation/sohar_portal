<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Send OTP to phone number
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]{8,15}$/'
        ]);

        $phoneNumber = $request->phone_number;

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Delete old OTPs for this phone number
        DB::table('otp_verifications')
            ->where('phone_number', $phoneNumber)
            ->where('is_used', false)
            ->delete();

        // Store OTP in database
        DB::table('otp_verifications')->insert([
            'phone_number' => $phoneNumber,
            'otp_code' => $otp,
            'expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // TODO: Send actual SMS via SMS gateway
        // For development, we'll return the OTP in response (remove in production)

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in' => 300, // 5 minutes in seconds
            // Remove this in production
            'otp' => app()->environment('local') ? $otp : null
        ]);
    }

    /**
     * Verify OTP and login/register user
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string|size:6',
            'device_token' => 'nullable|string'
        ]);

        $phoneNumber = $request->phone_number;
        $otpCode = $request->otp;

        // Check OTP validity
        $otpRecord = DB::table('otp_verifications')
            ->where('phone_number', $phoneNumber)
            ->where('otp_code', $otpCode)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            // Increment attempts
            DB::table('otp_verifications')
                ->where('phone_number', $phoneNumber)
                ->where('is_used', false)
                ->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 401);
        }

        // Mark OTP as used
        DB::table('otp_verifications')
            ->where('id', $otpRecord->id)
            ->update(['is_used' => true]);

        // Find or create user
        $user = User::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'name' => 'User ' . substr($phoneNumber, -4),
                'email' => $phoneNumber . '@soharfestival.om',
                'password' => Hash::make(Str::random(16)),
                'phone_verified_at' => now()
            ]
        );

        // Update device token if provided
        if ($request->device_token) {
            $user->device_token = $request->device_token;
            $user->save();
        }

        // Create API token
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
}