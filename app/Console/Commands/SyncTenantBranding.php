<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Console\Command;

/**
 * Bring a seeded tenant's name and branding up to TenantSeeder's current
 * definition, without re-seeding:
 *
 *   php artisan tenant:sync-branding --dry-run
 *   php artisan tenant:sync-branding --tenant=north-batinah
 *
 * Writes only the app name and the settings in BRANDING_PATHS (each path is
 * replaced as a whole, so a Radi logo set in the admin is reset too).
 * Support contacts, legal links, the other modules, wallet, payments and
 * every other setting an admin may have changed are left alone, which
 * re-running TenantSeeder would not do. Idempotent.
 */
class SyncTenantBranding extends Command
{
    protected $signature = 'tenant:sync-branding
        {--dry-run : List the changes without writing them}
        {--tenant=* : Only these tenant slugs (default: north-batinah and demo)}';

    protected $description = "Update a seeded tenant's name, logos, theme and welcome texts to the seeder's definition";

    /** Settings replaced from the seeder (dot paths). */
    public const BRANDING_PATHS = [
        'tenant.tagline',
        'branding',
        'theme',
        'ai.assistant_name',
        'ai.welcome',
        'ai.suggestions',
        'ai.venue_info',
        // The «راضي» survey (docs/radi_satisfaction_contract.md §1): on, with its texts.
        'modules.radi',
        'radi',
    ];

    public function handle(): int
    {
        $definitions = ['north-batinah' => TenantSeeder::northBatinah(...), 'demo' => TenantSeeder::demo(...)];
        $only = array_filter((array) $this->option('tenant'));
        if ($unknown = array_diff($only, array_keys($definitions))) {
            $this->error('No seeded branding for tenant(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;
        foreach ($only ?: array_keys($definitions) as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->warn("Tenant {$slug} not found, skipped.");

                continue;
            }

            $def = $definitions[$slug]();
            $settings = $tenant->settings ?? [];
            $changed = [];
            if ($tenant->name != $def['name']) {
                $changed[] = 'name';
            }
            foreach (self::BRANDING_PATHS as $path) {
                $value = data_get($def['settings'], $path);
                if ($value !== null && data_get($settings, $path) != $value) {
                    data_set($settings, $path, $value);
                    $changed[] = $path;
                }
            }

            $this->line("{$slug}: ".($changed ? implode(', ', $changed) : 'up to date'));
            $total += count($changed);
            if ($changed && ! $dryRun) {
                $tenant->name = $def['name'];
                $tenant->settings = $settings;
                $tenant->save();
            }
        }

        $this->info(($dryRun ? 'Dry run: ' : '')."{$total} change(s)".($dryRun ? ' would be written.' : '.'));

        return self::SUCCESS;
    }
}
