<?php

namespace Tests\Feature\Content;

use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncTenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function sync(array $options = []): string
    {
        $this->assertSame(0, Artisan::call('tenant:sync-branding', $options));

        return Artisan::output();
    }

    #[Test]
    public function it_updates_name_and_branding_but_keeps_admin_settings(): void
    {
        $this->seed(TenantSeeder::class);
        $nb = Tenant::where('slug', 'north-batinah')->first();
        $nb->name = ['en' => 'North Batinah Events', 'ar' => 'فعاليات شمال الباطنة'];
        $nb->putSetting('theme.colors.primary', '#2F8E80')
            ->putSetting('branding.logo_url', '/tenants/north-batinah/logo.png')
            ->putSetting('tenant.support.email', 'events@example.org')
            ->putSetting('modules.wallet', false)
            ->save();

        $dry = $this->sync(['--dry-run' => true, '--tenant' => ['north-batinah']]);
        $this->assertStringContainsString('north-batinah: name, branding, theme', $dry);
        $this->assertSame('#2F8E80', $nb->fresh()->setting('theme.colors.primary'), 'dry run writes nothing');

        $this->sync(['--tenant' => ['north-batinah']]);
        $nb = $nb->fresh();
        $def = TenantSeeder::northBatinah();
        $this->assertSame($def['name'], $nb->name);
        $this->assertSame($def['settings']['theme']['colors']['primary'], $nb->setting('theme.colors.primary'));
        $this->assertSame($def['settings']['branding']['logo_url'], $nb->setting('branding.logo_url'));
        $this->assertSame('events@example.org', $nb->setting('tenant.support.email'));
        $this->assertFalse($nb->setting('modules.wallet'));

        $this->assertStringContainsString('north-batinah: up to date', $this->sync(['--tenant' => ['north-batinah']]));
    }

    #[Test]
    public function it_rejects_unknown_tenants(): void
    {
        $this->assertSame(1, Artisan::call('tenant:sync-branding', ['--tenant' => ['nope']]));
    }
}
