<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the tenant of the current request / job.
 *
 * Resolution order: explicitly set (ResolveTenant API middleware, admin
 * middleware, jobs, seeders) -> lazily the default tenant
 * (config('tenancy.default')). The lazy fallback keeps console commands,
 * public web pages and legacy callers working and is always *scoped* — data
 * of other tenants is only reachable via withoutTenantScope().
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    protected bool $resolvedDefault = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolvedDefault = $tenant !== null;

        // spatie/laravel-permission teams: team = tenant
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant?->id);
    }

    public function get(): ?Tenant
    {
        if (! $this->tenant && ! $this->resolvedDefault) {
            $this->resolvedDefault = true;
            if (Schema::hasTable('tenants')) {
                $default = Tenant::findBySlug(config('tenancy.default'));
                if ($default) {
                    $this->set($default);
                }
            }
        }

        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function check(): bool
    {
        return $this->get() !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->resolvedDefault = false;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    /**
     * Run a callback in the context of another tenant, restoring afterwards.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);
        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->set($previous) : $this->forget();
        }
    }
}
