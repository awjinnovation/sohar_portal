<?php

namespace App\Support;

/**
 * Integer minor-unit helpers. Never use floats for money.
 */
class Money
{
    /**
     * "1.500" (or 1.5) -> 1500 for 3 decimals. Parses the decimal string
     * digit by digit to avoid binary floating point errors.
     */
    public static function toMinor(string|int|float|null $amount, int $decimals = 3): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_float($amount)) {
            $amount = number_format($amount, $decimals, '.', '');
        }
        $amount = trim((string) $amount);
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');
        [$int, $frac] = array_pad(explode('.', $amount, 2), 2, '');
        $int = $int === '' ? '0' : $int;
        $frac = substr(str_pad($frac, $decimals, '0'), 0, $decimals);
        $minor = (int) $int * (10 ** $decimals) + (int) ($frac === '' ? 0 : $frac);

        return $negative ? -$minor : $minor;
    }

    /** 1500 -> "1.500" */
    public static function format(int $minor, int $decimals = 3): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $div = 10 ** $decimals;
        $str = intdiv($minor, $div).($decimals > 0 ? '.'.str_pad((string) ($minor % $div), $decimals, '0', STR_PAD_LEFT) : '');

        return ($negative ? '-' : '').$str;
    }
}
