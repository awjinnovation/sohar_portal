<?php

namespace App\Models\Concerns;

use App\Support\PublicApiCache;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalogue models served by the public API cache: any change drops the
 * tenant's cached responses (after the transaction commits). A model can
 * define affectsPublicApiCache(): bool to opt out per record.
 */
trait BustsPublicApiCache
{
    public static function bootBustsPublicApiCache(): void
    {
        $bump = function ($model) {
            if (method_exists($model, 'affectsPublicApiCache') && ! $model->affectsPublicApiCache()) {
                return;
            }
            PublicApiCache::bumpAfterCommit($model->tenant_id ?? null);
        };

        static::saved($bump);
        static::deleted($bump);
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored($bump);
        }
    }
}
