<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Event Management Permissions
        Permission::create(['name' => 'view events']);
        Permission::create(['name' => 'create events']);
        Permission::create(['name' => 'edit events']);
        Permission::create(['name' => 'delete events']);
        Permission::create(['name' => 'publish events']);

        // Ticket Management Permissions
        Permission::create(['name' => 'view tickets']);
        Permission::create(['name' => 'create tickets']);
        Permission::create(['name' => 'edit tickets']);
        Permission::create(['name' => 'delete tickets']);
        Permission::create(['name' => 'validate tickets']);
        Permission::create(['name' => 'refund tickets']);

        // Restaurant Management Permissions
        Permission::create(['name' => 'view restaurants']);
        Permission::create(['name' => 'create restaurants']);
        Permission::create(['name' => 'edit restaurants']);
        Permission::create(['name' => 'delete restaurants']);
        Permission::create(['name' => 'manage menus']);

        // Heritage Village Management Permissions
        Permission::create(['name' => 'view heritage villages']);
        Permission::create(['name' => 'create heritage villages']);
        Permission::create(['name' => 'edit heritage villages']);
        Permission::create(['name' => 'delete heritage villages']);

        // Healthcare Management Permissions
        Permission::create(['name' => 'view healthcare services']);
        Permission::create(['name' => 'create healthcare services']);
        Permission::create(['name' => 'edit healthcare services']);
        Permission::create(['name' => 'delete healthcare services']);

        // User Management Permissions
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'create users']);
        Permission::create(['name' => 'edit users']);
        Permission::create(['name' => 'delete users']);
        Permission::create(['name' => 'assign roles']);

        // Report Permissions
        Permission::create(['name' => 'view reports']);
        Permission::create(['name' => 'export reports']);
        Permission::create(['name' => 'view analytics']);

        // System Settings Permissions
        Permission::create(['name' => 'manage settings']);
        Permission::create(['name' => 'manage translations']);
        Permission::create(['name' => 'manage notifications']);

        // Create Roles
        $superAdmin = Role::create(['name' => 'super-admin']);
        $admin = Role::create(['name' => 'admin']);
        $eventManager = Role::create(['name' => 'event-manager']);
        $ticketManager = Role::create(['name' => 'ticket-manager']);
        $contentManager = Role::create(['name' => 'content-manager']);
        $viewer = Role::create(['name' => 'viewer']);

        // Super Admin gets all permissions
        $superAdmin->givePermissionTo(Permission::all());

        // Admin gets most permissions except system critical ones
        $admin->givePermissionTo([
            'view events', 'create events', 'edit events', 'delete events', 'publish events',
            'view tickets', 'create tickets', 'edit tickets', 'delete tickets', 'validate tickets',
            'view restaurants', 'create restaurants', 'edit restaurants', 'delete restaurants', 'manage menus',
            'view heritage villages', 'create heritage villages', 'edit heritage villages', 'delete heritage villages',
            'view healthcare services', 'create healthcare services', 'edit healthcare services', 'delete healthcare services',
            'view users', 'create users', 'edit users',
            'view reports', 'export reports', 'view analytics',
            'manage notifications'
        ]);

        // Event Manager permissions
        $eventManager->givePermissionTo([
            'view events', 'create events', 'edit events', 'publish events',
            'view tickets', 'create tickets', 'edit tickets',
            'view reports', 'view analytics'
        ]);

        // Ticket Manager permissions
        $ticketManager->givePermissionTo([
            'view tickets', 'create tickets', 'edit tickets', 'validate tickets', 'refund tickets',
            'view events',
            'view reports', 'export reports'
        ]);

        // Content Manager permissions
        $contentManager->givePermissionTo([
            'view restaurants', 'create restaurants', 'edit restaurants', 'manage menus',
            'view heritage villages', 'create heritage villages', 'edit heritage villages',
            'view healthcare services', 'create healthcare services', 'edit healthcare services',
            'view events', 'create events', 'edit events'
        ]);

        // Viewer permissions
        $viewer->givePermissionTo([
            'view events',
            'view tickets',
            'view restaurants',
            'view heritage villages',
            'view healthcare services',
            'view reports'
        ]);

        // Create default super admin user
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@soharfestival.com',
            'password' => Hash::make('password123')
        ]);
        $user->assignRole('super-admin');

        // Create additional demo users
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.user@soharfestival.com',
            'password' => Hash::make('password123')
        ]);
        $adminUser->assignRole('admin');

        $eventManagerUser = User::create([
            'name' => 'Event Manager',
            'email' => 'events@soharfestival.com',
            'password' => Hash::make('password123')
        ]);
        $eventManagerUser->assignRole('event-manager');

        $ticketManagerUser = User::create([
            'name' => 'Ticket Manager',
            'email' => 'tickets@soharfestival.com',
            'password' => Hash::make('password123')
        ]);
        $ticketManagerUser->assignRole('ticket-manager');
    }
}