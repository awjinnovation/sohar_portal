<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token that writes `last_used_at` at most every 5 minutes instead
 * of on every API request (docs/caching.md): at peak that is one database
 * write fewer per signed-in request. Registered in AppServiceProvider.
 */
class PersonalAccessToken extends SanctumToken
{
    public const LAST_USED_RESOLUTION_SECONDS = 300;

    public function save(array $options = []): bool
    {
        $dirty = array_keys($this->getDirty());
        $previous = $this->getOriginal('last_used_at');

        if ($this->exists && $dirty === ['last_used_at'] && $previous
            && $this->asDateTime($previous)->gt(now()->subSeconds(self::LAST_USED_RESOLUTION_SECONDS))) {
            $this->syncOriginalAttribute('last_used_at');

            return true;
        }

        return parent::save($options);
    }
}
