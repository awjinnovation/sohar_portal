<?php

namespace Tests;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** The default tenant (north-batinah, created by the tenancy migration). */
    protected ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->tenant = Tenant::where('slug', config('tenancy.default'))->first();
            $this->actingAsTenant($this->tenant);
        }
    }

    /** Switch the tenant context used by test code (models, role assignment). */
    protected function actingAsTenant(?Tenant $tenant): static
    {
        app(TenantContext::class)->set($tenant);

        return $this;
    }
}
