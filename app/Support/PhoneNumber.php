<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Mobile numbers as the app's OTP sign-in stores them: digits only, country
 * code first (`96891234567`). The tenant sets the country code and the local
 * length (`auth.country_code`, `auth.phone_length`).
 */
class PhoneNumber
{
    /**
     * `91234567`, `+968 9123 4567`, `0096891234567` → `96891234567`; null
     * when it is not a mobile number of the tenant's country.
     */
    public static function normalize(?Tenant $tenant, string $input): ?string
    {
        $cc = (string) ($tenant?->setting('auth.country_code', '968') ?? '968');
        $len = (int) ($tenant?->setting('auth.phone_length', 8) ?? 8);
        $digits = preg_replace('/\D/', '', $input);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        $local = strlen($digits) === strlen($cc) + $len && str_starts_with($digits, $cc) ? substr($digits, strlen($cc)) : $digits;
        // Omani mobiles start with 7 or 9.
        $mobile = $cc !== '968' || in_array($local[0] ?? '', ['7', '9'], true);

        return strlen($local) === $len && $mobile ? $cc.$local : null;
    }

    /** Local length of the tenant's numbers (8 in Oman), for form hints. */
    public static function localLength(?Tenant $tenant): int
    {
        return (int) ($tenant?->setting('auth.phone_length', 8) ?? 8);
    }
}
