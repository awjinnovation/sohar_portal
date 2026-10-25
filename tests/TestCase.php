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

    /**
     * assertSame for values read back from JSON columns: MySQL stores JSON
     * objects with their keys re-ordered ({"ar":…,"en":…}), SQLite keeps the
     * written order. Object keys are compared in sorted order, lists keep
     * theirs, values stay strictly compared.
     */
    protected function assertSameJson(mixed $expected, mixed $actual, string $message = ''): void
    {
        $sort = function (mixed $v) use (&$sort) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        $this->assertSame($sort($expected), $sort($actual), $message);
    }

    /** Switch the tenant context used by test code (models, role assignment). */
    protected function actingAsTenant(?Tenant $tenant): static
    {
        app(TenantContext::class)->set($tenant);

        return $this;
    }
}
