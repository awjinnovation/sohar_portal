<?php

namespace App\Support;

use Illuminate\Support\Arr;

class Arr2
{
    /**
     * Deep merge where associative arrays merge recursively and lists
     * (sequential arrays) from $override replace those of $base.
     */
    public static function mergeSettings(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && Arr::isAssoc($value) && (Arr::isAssoc($base[$key]) || $base[$key] === [])) {
                $base[$key] = self::mergeSettings($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
