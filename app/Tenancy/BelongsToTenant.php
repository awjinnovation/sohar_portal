<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant-owned model: global TenantScope + tenant_id auto-filled on create.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(TenantContext::class)->id();
            }
        });
    }

    /**
     * Dates in JSON (models returned directly by API controllers) are ISO
     * 8601 in the tenant time zone with an explicit offset.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \App\Support\TenantTime::isoForTenantId($date, $this->tenant_id ?? null)
            ?? $date->format(DATE_ATOM);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Query across all tenants (super-admin / system jobs only). */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
