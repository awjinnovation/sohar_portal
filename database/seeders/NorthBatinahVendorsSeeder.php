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
    /**
     * Generic Sohar Festival stalls (not real businesses). The food stall is
     * linked to its listing in the food court (NorthBatinahEventsSeeder).
     * Also read by `content:sync-arabic`.
     */
    public const VENDORS = [
        ['en' => 'Sohar Karak House', 'ar' => 'بيت كرك صحار', 'category' => 'food', 'restaurant' => 'Sohar Karak House'],
        ['en' => 'Omani Village Crafts', 'ar' => 'حرفيات القرية العمانية', 'category' => 'merchandise', 'restaurant' => null],
        ['en' => 'Festival Karting', 'ar' => 'كارتينج المهرجان', 'category' => 'services', 'restaurant' => null],
    ];

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
        $vendors = collect(self::VENDORS)->map(fn ($v) => self::vendor(
            ['en' => $v['en'], 'ar' => $v['ar']],
            $v['category'],
            $v['restaurant'] ? Restaurant::where('name', $v['restaurant'])->value('id') : null,
        ));

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
