<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Laravel Horizon runs the queue workers (config/horizon.php: one pool per
 * queue, MSR-347). Its dashboard (/horizon) is for platform super-admins
 * only. The `viewHorizon` gate is not used, because Gate::before lets a
 * tenant admin pass every ability that is not a platform one.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        Horizon::auth(fn ($request) => (bool) $request->user()?->isSuperAdmin());
    }
}
