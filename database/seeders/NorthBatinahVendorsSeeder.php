<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class NorthBatinahVendorsSeeder extends Seeder
{
    /** Find a vendor of the current tenant by English name, or create it (idempotent seeding). */
    public static function vendor(array $name, string $category, ?int $restaurantId = null): Vendor
    {
        $existing = Vendor::all()->first(fn (Vendor $v) => ($v->name['en'] ?? null) === $name['en']);

        return $existing ?? Vendor::create([
            'name' => $name,
            'category' => $category,
            'restaurant_id' => $restaurantId,
            'is_active' => true,
        ]);
    }

    public function run(): void
    {
        $restaurants = Restaurant::orderBy('id')->take(3)->get();
        $vendors = collect([
            ['en' => 'Sohar Karak House', 'ar' => 'بيت الكرك صحار', 'category' => 'food'],
            ['en' => 'Festival Souvenirs', 'ar' => 'هدايا المهرجان', 'category' => 'merchandise'],
            ['en' => 'Kids Rides', 'ar' => 'ألعاب الأطفال', 'category' => 'services'],
        ])->map(fn ($v, $i) => self::vendor(['en' => $v['en'], 'ar' => $v['ar']], $v['category'], $restaurants[$i]->id ?? null));

        $cashier = User::where('email', 'vendor@northbatinah.events')->first();
        if ($cashier) {
            $vendors->first()->staff()->syncWithoutDetaching([$cashier->id => ['role' => 'manager']]);
        }

        // Demo visitor gets a starting balance to try wallet payments.
        $visitor = User::where('phone_number', '96891234567')->first();
        if ($visitor) {
            $wallets = app(WalletService::class);
            $wallet = $wallets->walletFor($visitor, app(TenantContext::class)->get());
            $wallets->credit($wallet, 10000, 'topup', ['idempotency_key' => 'seed:topup', 'meta' => ['kind' => 'seed']]);
        }
    }
}
