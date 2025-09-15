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
        Permission::firstOrCreate(['name' => 'view events']);
        Permission::firstOrCreate(['name' => 'create events']);
        Permission::firstOrCreate(['name' => 'edit events']);
        Permission::firstOrCreate(['name' => 'delete events']);
        Permission::firstOrCreate(['name' => 'publish events']);

        // Ticket Management Permissions
        Permission::firstOrCreate(['name' => 'view tickets']);
        Permission::firstOrCreate(['name' => 'create tickets']);
        Permission::firstOrCreate(['name' => 'edit tickets']);
        Permission::firstOrCreate(['name' => 'delete tickets']);
        Permission::firstOrCreate(['name' => 'validate tickets']);
        Permission::firstOrCreate(['name' => 'refund tickets']);

        // Restaurant Management Permissions
        Permission::firstOrCreate(['name' => 'view restaurants']);
        Permission::firstOrCreate(['name' => 'create restaurants']);
        Permission::firstOrCreate(['name' => 'edit restaurants']);
        Permission::firstOrCreate(['name' => 'delete restaurants']);
        Permission::firstOrCreate(['name' => 'manage menus']);

        // Heritage Village Management Permissions
        Permission::firstOrCreate(['name' => 'view heritage villages']);
        Permission::firstOrCreate(['name' => 'create heritage villages']);
        Permission::firstOrCreate(['name' => 'edit heritage villages']);
        Permission::firstOrCreate(['name' => 'delete heritage villages']);

        // Healthcare Management Permissions
        Permission::firstOrCreate(['name' => 'view healthcare services']);
        Permission::firstOrCreate(['name' => 'create healthcare services']);
        Permission::firstOrCreate(['name' => 'edit healthcare services']);
        Permission::firstOrCreate(['name' => 'delete healthcare services']);

        // User Management Permissions
        Permission::firstOrCreate(['name' => 'view users']);
        Permission::firstOrCreate(['name' => 'create users']);
        Permission::firstOrCreate(['name' => 'edit users']);
        Permission::firstOrCreate(['name' => 'delete users']);
        Permission::firstOrCreate(['name' => 'assign roles']);

        // Report Permissions
        Permission::firstOrCreate(['name' => 'view reports']);
        Permission::firstOrCreate(['name' => 'export reports']);
        Permission::firstOrCreate(['name' => 'view analytics']);

        // System Settings Permissions
        Permission::firstOrCreate(['name' => 'manage settings']);
        Permission::firstOrCreate(['name' => 'manage translations']);
        Permission::firstOrCreate(['name' => 'manage notifications']);

        // Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $eventManager = Role::firstOrCreate(['name' => 'event-manager']);
        $ticketManager = Role::firstOrCreate(['name' => 'ticket-manager']);
        $contentManager = Role::firstOrCreate(['name' => 'content-manager']);
        $viewer = Role::firstOrCreate(['name' => 'viewer']);

        // Super Admin gets all permissions
        $superAdmin->syncPermissions(Permission::all());

        // Admin gets most permissions except system critical ones
        $admin->syncPermissions([
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
        $eventManager->syncPermissions([
            'view events', 'create events', 'edit events', 'publish events',
            'view tickets', 'create tickets', 'edit tickets',
            'view reports', 'view analytics'
        ]);

        // Ticket Manager permissions
        $ticketManager->syncPermissions([
            'view tickets', 'create tickets', 'edit tickets', 'validate tickets', 'refund tickets',
            'view events',
            'view reports', 'export reports'
        ]);

        // Content Manager permissions
        $contentManager->syncPermissions([
            'view restaurants', 'create restaurants', 'edit restaurants', 'manage menus',
            'view heritage villages', 'create heritage villages', 'edit heritage villages',
            'view healthcare services', 'create healthcare services', 'edit healthcare services',
            'view events', 'create events', 'edit events'
        ]);

        // Viewer permissions
        $viewer->syncPermissions([
            'view events',
            'view tickets',
            'view restaurants',
            'view heritage villages',
            'view healthcare services',
            'view reports'
        ]);

        // Create default super admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@soharfestival.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123')
            ]
        );
        $user->syncRoles(['super-admin']);

        // Create additional demo users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin.user@soharfestival.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123')
            ]
        );
        $adminUser->syncRoles(['admin']);

        $eventManagerUser = User::firstOrCreate(
            ['email' => 'events@soharfestival.com'],
            [
                'name' => 'Event Manager',
                'password' => Hash::make('password123')
            ]
        );
        $eventManagerUser->syncRoles(['event-manager']);

        $ticketManagerUser = User::firstOrCreate(
            ['email' => 'tickets@soharfestival.com'],
            [
                'name' => 'Ticket Manager',
                'password' => Hash::make('password123')
            ]
        );
        $ticketManagerUser->syncRoles(['ticket-manager']);
    }
}