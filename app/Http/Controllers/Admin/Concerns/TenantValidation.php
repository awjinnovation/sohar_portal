<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation helpers for admin controllers: `exists` / `unique` rules are
 * evaluated inside the current tenant only (the DB validator bypasses the
 * Eloquent global scope, so the tenant must be added explicitly).
 */
trait TenantValidation
{
    protected function currentTenant(): ?Tenant
    {
        return app(TenantContext::class)->get();
    }

    protected function currentTenantId(): ?int
    {
        return app(TenantContext::class)->id();
    }

    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', $this->currentTenantId());
    }

    protected function tenantUnique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('tenant_id', $this->currentTenantId());
    }
}
