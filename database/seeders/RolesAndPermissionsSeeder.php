<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Global role *definitions* (team_id NULL) and permissions. Assignments are
 * per tenant (spatie teams, team = tenant) and are made by the tenant
 * seeders / admin panel. Platform super-admins are users.is_super_admin.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'view dashboard',
        'manage events',        // events, categories, tags
        'manage tickets',
        'validate tickets',     // gate scanner API
        'view payments',
        'refund payments',
        'manage restaurants',
        'manage locations',
        'manage announcements',
        'manage notifications',
        'manage users',
        'manage roles',
        'manage settings',      // app settings, legal pages, own tenant branding
        'manage wallets',       // ledger, manual adjustments, freeze
        'manage refunds',       // refund requests
        'manage vendors',
        'manage deposits',
        'charge wallets',       // vendor-staff API
        'manage gates',         // gates, scanner devices, live gate dashboard, scan log
        'manage places',        // places to visit, place categories, places map
        'manage content',       // content collections + items, pages
        'sell tickets',         // gate box office (docs/gate_sales_contract.md)
    ];

    public const ROLES = [
        // Tenant admin: everything inside the tenant (also via Gate::before).
        'admin' => self::PERMISSIONS,
        'event-manager' => ['view dashboard', 'manage events', 'manage tickets', 'view payments', 'manage announcements'],
        'ticket-manager' => ['view dashboard', 'manage tickets', 'validate tickets', 'view payments', 'refund payments', 'manage gates'],
        'content-manager' => ['view dashboard', 'manage events', 'manage restaurants', 'manage locations', 'manage announcements', 'manage notifications', 'manage settings', 'manage places', 'manage content'],
        'finance-manager' => ['view dashboard', 'view payments', 'refund payments', 'manage wallets', 'manage refunds', 'manage deposits', 'manage vendors'],
        'viewer' => ['view dashboard', 'view payments'],
        // App-side roles
        'staff' => ['validate tickets', 'manage deposits', 'sell tickets'],
        'vendor-staff' => ['charge wallets', 'manage deposits'],
        'user' => [],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null); // role definitions are global

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->whereNull('team_id')->first()
                ?? Role::create(['name' => $roleName, 'guard_name' => 'web', 'team_id' => null]);
            $role->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId($previousTeam);
        $registrar->forgetCachedPermissions();
    }
}
