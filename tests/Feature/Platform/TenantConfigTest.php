<?php

namespace Tests\Feature\Platform;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class TenantConfigTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    #[Test]
    public function it_returns_the_contract_document_with_etag(): void
    {
        $this->makeTenant('demo', [
            'theme' => ['colors' => ['primary' => '#4F46E5']],
            'modules' => ['map' => false],
            'navigation' => ['home', 'events', 'map', 'wallet', 'more'],
            'branding' => ['logo_url' => '/tenants/demo/logo.png'],
        ]);

        $res = $this->getJson('/api/v1/tenant/config', ['X-Tenant' => 'demo'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.slug', 'demo')
            ->assertJsonPath('data.theme.colors.primary', '#4F46E5')
            ->assertJsonPath('data.theme.colors.on_primary', '#FFFFFF') // default merged in
            ->assertJsonPath('data.modules.map', false)
            ->assertJsonPath('data.tenant.currency_decimals', 3)
            ->assertJsonStructure(['data' => [
                'tenant' => ['slug', 'name' => ['en', 'ar'], 'tagline', 'default_locale', 'locales', 'currency', 'timezone', 'support', 'social', 'legal'],
                'branding' => ['logo_url', 'logo_on_primary_url', 'icon_url', 'splash_url', 'onboarding'],
                'theme' => ['mode', 'colors', 'font_family_ar', 'font_family_en', 'corner_radius', 'density'],
                'modules', 'navigation', 'map' => ['center' => ['lat', 'lng'], 'zoom'],
                'wallet' => ['topup_presets_minor', 'min_topup_minor', 'max_topup_minor', 'max_balance_minor', 'refunds'],
                'points' => ['label', 'earn_per_currency_unit', 'redeem_value_minor_per_point', 'min_redeem_points', 'expiry_days', 'tiers'],
                'app' => ['min_version', 'latest_version', 'force_update', 'maintenance_mode', 'store_urls'],
                'home_blocks', 'quick_actions', 'merchant' => ['confirm_threshold_minor'],
                'auth' => ['country_code', 'phone_length', 'phone_hint'],
            ]])
            ->assertJsonPath('data.auth.country_code', '968')
            ->assertJsonPath('data.auth.phone_length', 8)
            ->assertJsonMissingPath('data.payments')
            ->assertJsonMissingPath('data.messages')
            ->assertHeader('ETag');

        // navigation is filtered by enabled modules
        $this->assertNotContains('map', $res->json('data.navigation'));
        $this->assertStringStartsWith('http', $res->json('data.branding.logo_url'));

        $this->getJson('/api/v1/tenant/config', ['X-Tenant' => 'demo', 'If-None-Match' => $res->headers->get('ETag')])
            ->assertStatus(304);
    }

    #[Test]
    public function auth_phone_settings_pass_through_with_defaults(): void
    {
        $this->makeTenant('gulf', ['auth' => ['country_code' => '971', 'phone_length' => 9]]);

        $this->getJson('/api/v1/tenant/config', ['X-Tenant' => 'gulf'])
            ->assertOk()
            ->assertJsonPath('data.auth.country_code', '971')
            ->assertJsonPath('data.auth.phone_length', 9)
            ->assertJsonPath('data.auth.phone_hint', '9123 4567'); // default merged in
    }

    #[Test]
    public function etag_changes_when_settings_change(): void
    {
        $etag1 = $this->getJson('/api/v1/tenant/config')->headers->get('ETag');
        $this->tenant->putSetting('theme.colors.primary', '#000000')->save();
        $etag2 = $this->getJson('/api/v1/tenant/config')->assertJsonPath('data.theme.colors.primary', '#000000')->headers->get('ETag');

        $this->assertNotSame($etag1, $etag2);
    }

    #[Test]
    public function legacy_config_endpoints_read_from_the_tenant(): void
    {
        $this->makeTenant('demo', ['theme' => ['colors' => ['primary' => '#4F46E5']], 'modules' => ['map' => false]]);

        $this->getJson('/api/v1/config/theme', ['X-Tenant' => 'demo'])->assertOk()->assertJsonPath('data.primary_color', '#4F46E5');
        $this->getJson('/api/v1/config/branding', ['X-Tenant' => 'demo'])->assertOk()->assertJsonPath('data.app_name_en', 'Demo');
        $this->getJson('/api/v1/config/features', ['X-Tenant' => 'demo'])->assertOk()->assertJsonPath('data.enable_maps', false);
        $this->getJson('/api/v1/config/status', ['X-Tenant' => 'demo'])->assertOk()->assertJsonStructure(['data' => ['maintenance_mode', 'app_version']]);
        $this->getJson('/api/v1/config', ['X-Tenant' => 'demo'])->assertOk()->assertJsonPath('data.primary_color', '#4F46E5');
    }

    #[Test]
    public function config_key_and_app_settings_never_leak_private_settings(): void
    {
        AppSetting::set('internal_secret', 'shh', 'string', null, false);
        AppSetting::set('promo_banner', 'hello', 'string', null, true);

        $this->getJson('/api/v1/config/internal_secret')->assertNotFound();
        $this->getJson('/api/v1/config/promo_banner')->assertOk()->assertJsonPath('data.value', 'hello');
        $this->getJson('/api/v1/config')->assertOk()->assertJsonMissingPath('data.internal_secret');

        $this->getJson('/api/v1/app-settings/internal_secret')->assertNotFound();
        $this->getJson('/api/v1/app-settings/promo_banner')->assertOk()->assertJsonPath('data.value', 'hello');
        $this->getJson('/api/v1/app-settings')->assertOk()->assertJsonMissingPath('data.internal_secret')->assertJsonPath('data.promo_banner', 'hello');

        // another tenant does not see this tenant's public settings
        $this->makeTenant('demo');
        $this->getJson('/api/v1/app-settings/promo_banner', ['X-Tenant' => 'demo'])->assertNotFound();
    }
}
