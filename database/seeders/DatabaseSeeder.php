<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create permissions
        $permissions = [
            'manage users',
            'manage roles',
            'manage events',
            'manage heritage villages',
            'manage restaurants',
            'manage announcements',
            'manage emergency contacts',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to admin role
        $adminRole->givePermissionTo(Permission::all());

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@soharfestival.om'],
            [
                'name' => 'مدير النظام',
                'password' => bcrypt('admin123'),
            ]
        );
        $admin->assignRole('admin');

        // Create test user
        $user = User::firstOrCreate(
            ['email' => 'user@soharfestival.om'],
            [
                'name' => 'مستخدم تجريبي',
                'password' => bcrypt('user123'),
            ]
        );
        $user->assignRole('user');

        // Call other seeders
        $this->call([
            RolesAndPermissionsSeeder::class,
            
            HeritageVillageSeeder::class,
            TestDataSeeder::class,
            FixedApiSeeder::class,
        ]);
    }
}
