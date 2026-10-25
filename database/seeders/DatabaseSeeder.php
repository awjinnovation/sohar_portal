<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the platform: roles, tenants (north-batinah + demo), staff
     * accounts, vendors and each tenant's sample content.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TenantSeeder::class,
        ]);

        $context = app(TenantContext::class);

        $context->run(Tenant::where('slug', 'north-batinah')->firstOrFail(), function () {
            $this->call([
                PlatformUsersSeeder::class,
                NorthBatinahEventsSeeder::class,
                TermsAndConditionSeeder::class,
                PrivacyPolicySeeder::class,
                NorthBatinahVendorsSeeder::class,
                GateSeeder::class,
                PlacesContentSeeder::class,
            ]);
        });

        $context->run(Tenant::where('slug', 'demo')->firstOrFail(), function () {
            $this->call([DemoTenantSeeder::class, GateSeeder::class, PlacesContentSeeder::class]);
        });

        // Ticket types per event (both tenants; sample types, see README).
        $this->call(TicketTypesSeeder::class);
    }
}
