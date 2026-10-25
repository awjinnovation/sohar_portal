<?php

namespace Database\Seeders;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Platform super-admin + north-batinah staff accounts. Runs inside the
 * north-batinah tenant context (role assignments are per tenant).
 *
 * All seeded passwords: "password123" (change them in production!).
 */
class PlatformUsersSeeder extends Seeder
{
    public function run(): void
    {
        $super = self::user('super@platform.local', 'Platform Super Admin');
        $super->forceFill(['is_super_admin' => true])->save();

        $tenant = app(TenantContext::class)->get();

        self::user('admin@northbatinah.events', 'North Batinah Admin')->syncRoles(['admin']);
        self::user('events@northbatinah.events', 'Event Manager')->syncRoles(['event-manager']);
        self::user('finance@northbatinah.events', 'Finance Manager')->syncRoles(['finance-manager']);
        self::user('staff@northbatinah.events', 'Gate Staff', '96890000001')->syncRoles(['staff']);
        self::user('vendor@northbatinah.events', 'Vendor Cashier', '96890000002')->syncRoles(['vendor-staff']);

        echo "Seeded staff accounts for tenant {$tenant->slug}\n";
    }

    public static function user(string $email, string $name, ?string $phone = null): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password123'),
            'phone_number' => $phone,
            'phone_verified_at' => $phone ? now() : null,
        ]);
        $user->unsetRelation('roles');

        return $user;
    }
}
