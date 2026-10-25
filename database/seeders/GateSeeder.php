<?php

namespace Database\Seeders;

use App\Models\Gate;
use App\Models\GateDevice;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Gates of the current tenant (runs inside TenantContext::run, see
 * DatabaseSeeder). Idempotent: existing gates / devices (matched by code /
 * name) are left as edited in the admin panel.
 *
 * Box office (docs/gate_sales_contract.md): Main Gate A, Family Gate B and
 * the demo Hall Entrance may sell tickets (`can_sell`).
 *
 * No pairing code is seeded (codes are single-use and expire after 10 min):
 * create one with `php artisan gate:pair-code <tenant> <gate-code>`.
 */
class GateSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app(TenantContext::class)->get();

        $definitions = match ($tenant?->slug) {
            'north-batinah' => [
                ['code' => 'A', 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة الرئيسية أ'], 'direction' => 'entry', 'allow_reentry' => false, 'can_sell' => true, 'sort_order' => 1],
                ['code' => 'B', 'name' => ['en' => 'Family Gate B', 'ar' => 'بوابة العائلات ب'], 'direction' => 'entry', 'allow_reentry' => true, 'can_sell' => true, 'sort_order' => 2],
                ['code' => 'C', 'name' => ['en' => 'VIP Gate C', 'ar' => 'بوابة كبار الشخصيات ج'], 'direction' => 'entry', 'allow_reentry' => true, 'ticket_types' => ['vip'], 'sort_order' => 3],
                ['code' => 'EXIT1', 'name' => ['en' => 'Exit 1 (Beach Road)', 'ar' => 'المخرج 1 (طريق الشاطئ)'], 'direction' => 'exit', 'allow_reentry' => false, 'sort_order' => 4],
            ],
            'demo' => [
                ['code' => 'H1', 'name' => ['en' => 'Hall Entrance', 'ar' => 'مدخل القاعة'], 'direction' => 'entry', 'allow_reentry' => true, 'can_sell' => true, 'sort_order' => 1],
                ['code' => 'H2', 'name' => ['en' => 'Hall Exit', 'ar' => 'مخرج القاعة'], 'direction' => 'exit', 'allow_reentry' => false, 'sort_order' => 2],
            ],
            default => [],
        };

        foreach ($definitions as $def) {
            Gate::firstOrCreate(['code' => $def['code']], $def + ['active' => true]);
        }

        if ($tenant?->slug === 'north-batinah' && ($main = Gate::where('code', 'A')->first())) {
            GateDevice::firstOrCreate(['name' => 'Main Gate A · Lane 1'], [
                'gate_id' => $main->id,
                'device_type' => 'android_pos',
                'model' => 'Sunmi V2s',
                'platform' => 'Android 11',
                'app_version' => '1.0.0',
                'scanner_source' => 'hardware',
                'status' => GateDevice::STATUS_ACTIVE,
                'paired_at' => now(),
            ]);
        }
    }
}
