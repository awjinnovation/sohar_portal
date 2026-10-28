<?php

namespace Tests\Feature\AdminPlatform;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantManagementTest extends AdminPlatformTestCase
{
    public function test_super_admin_can_create_a_tenant_with_settings_logo_and_admin(): void
    {
        Storage::fake('public');
        $super = $this->superAdmin();

        $response = $this->actingAs($super)->post(route('admin.tenants.store'), [
            'slug' => 'acme-mall',
            'name' => ['en' => 'Acme Mall', 'ar' => 'مول أكمي'],
            'status' => 'active',
            'domain' => 'acme.example.com',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
            's' => [
                'theme' => ['colors' => ['primary' => '#112233', 'secondary' => '#E5883C', 'tertiary' => '#445566', 'info' => ''], 'corner_radius' => 8],
                'modules' => ['wallet' => '1', 'points' => '0', 'ticket_transfer' => '1'],
                'navigation' => 'home, events, more',
                'tenant' => ['currency' => 'omr', 'legal' => ['terms_url' => 'https://acme.example.com/{lang}/terms']],
                'wallet' => [
                    'topup_presets_minor' => '1000, 2000,5000',
                    'topup_bonus' => '[{"min_minor": 20000, "bonus_percent": 5}]',
                    'refunds' => ['methods' => ['', 'bank_transfer']],
                ],
                'points' => ['tiers' => '[{"code": "basic", "name": {"en": "Basic", "ar": "أساسي"}, "min_points": 0, "multiplier": 1, "color": "#000000"}]'],
            ],
            'advanced' => '{"theme": {"header_style": "solid"}, "home_blocks": ["wallet_card", "vendors"], "merchant": {"confirm_threshold_minor": 5000}}',
            'gateway' => ['bank_muscat' => ['merchant_id' => 'M-1', 'access_code' => 'AC', 'working_key' => 'WK', 'gateway_url' => '']],
            'admin_email' => 'owner@acme.test',
            'admin_name' => 'Acme Owner',
            'admin_password' => 'secret-pass-123',
        ]);

        $response->assertRedirect(route('admin.tenants.index'));
        $response->assertSessionHasNoErrors();

        $tenant = Tenant::where('slug', 'acme-mall')->firstOrFail();
        $this->assertSameJson(['en' => 'Acme Mall', 'ar' => 'مول أكمي'], $tenant->name);
        $this->assertSame('acme.example.com', $tenant->domain);

        $settings = $tenant->settings;
        $this->assertSame('#112233', $settings['theme']['colors']['primary']);
        $this->assertSame('#445566', $settings['theme']['colors']['tertiary']);
        $this->assertArrayNotHasKey('secondary', $settings['theme']['colors'], 'default values are not stored as overrides');
        $this->assertArrayNotHasKey('info', $settings['theme']['colors']);
        $this->assertSame(8, $settings['theme']['corner_radius']);
        $this->assertSame('solid', $settings['theme']['header_style']);
        $this->assertFalse($settings['modules']['points']);
        $this->assertTrue($settings['modules']['ticket_transfer']);
        $this->assertArrayNotHasKey('wallet', $settings['modules']);
        $this->assertSame(['home', 'events', 'more'], $settings['navigation']);
        $this->assertSame([1000, 2000, 5000], $settings['wallet']['topup_presets_minor']);
        $this->assertSame([['min_minor' => 20000, 'bonus_percent' => 5]], $settings['wallet']['topup_bonus']);
        $this->assertSame(['bank_transfer'], $settings['wallet']['refunds']['methods']);
        $this->assertSame('basic', $settings['points']['tiers'][0]['code']);
        $this->assertSame(['wallet_card', 'vendors'], $settings['home_blocks']);
        $this->assertSame(5000, $settings['merchant']['confirm_threshold_minor']);
        $this->assertSame('https://acme.example.com/{lang}/terms', $tenant->setting('tenant.legal.terms_url'));
        $this->assertArrayNotHasKey('currency', $settings['tenant'] ?? [], 'OMR equals the default');

        // Logo stored on the public disk without a leading slash.
        $logo = $tenant->setting('branding.logo_url');
        $this->assertStringStartsWith('tenants/acme-mall/', $logo);
        Storage::disk('public')->assertExists($logo);

        // Encrypted, write-only gateway credentials.
        $this->assertSame('M-1', $tenant->gateway_credentials['bank_muscat']['merchant_id']);
        $this->assertArrayNotHasKey('gateway_url', $tenant->gateway_credentials['bank_muscat']);

        // Initial tenant admin holds `admin` in the new tenant only.
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $this->assertTrue(app(TenantContext::class)->run($tenant, fn () => $owner->fresh()->hasRole('admin')));
        $this->assertFalse($owner->fresh()->hasRole('admin'));
        $this->assertSame([$tenant->id], $owner->panelTenantIds());
    }

    public function test_store_validates_slug_and_json(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.tenants.store'), [
            'slug' => 'Bad Slug',
            'name' => ['en' => 'X', 'ar' => 'X'],
            'status' => 'active',
            's' => ['points' => ['tiers' => '{not json']],
            'advanced' => '{"unknown_key": 1}',
        ])->assertSessionHasErrors(['slug', 's.points.tiers']);

        $this->actingAs($super)->post(route('admin.tenants.store'), [
            'slug' => 'good-slug',
            'name' => ['en' => 'X', 'ar' => 'X'],
            'status' => 'active',
            'advanced' => '{"unknown_key": 1}',
        ])->assertSessionHasErrors(['advanced']);

        $this->assertDatabaseMissing('tenants', ['slug' => 'good-slug']);
    }

    public function test_update_keeps_blank_gateway_credentials_and_resets_defaults(): void
    {
        $super = $this->superAdmin();
        $tenant = $this->otherTenant();
        $tenant->gateway_credentials = ['bank_muscat' => ['merchant_id' => 'OLD', 'working_key' => 'KEY']];
        $tenant->putSetting('theme.colors.primary', '#000000')->save();

        $this->actingAs($super)->put(route('admin.tenants.update', $tenant), [
            'name' => ['en' => 'B2', 'ar' => 'ب2'],
            'status' => 'inactive',
            's' => ['theme' => ['colors' => ['primary' => '#2F8E80']]], // back to the default
            'gateway' => ['bank_muscat' => ['merchant_id' => '', 'access_code' => 'NEW-AC', 'working_key' => '']],
        ])->assertRedirect(route('admin.tenants.edit', $tenant));

        $tenant->refresh();
        $this->assertSame('inactive', $tenant->status);
        $this->assertSame('ب2', $tenant->name['ar']);
        $this->assertSame(['merchant_id' => 'OLD', 'working_key' => 'KEY', 'access_code' => 'NEW-AC'], $tenant->gateway_credentials['bank_muscat']);
        $this->assertArrayNotHasKey('theme', $tenant->settings ?? []);
        $this->assertSame('#2F8E80', $tenant->setting('theme.colors.primary'));
    }

    public function test_tenant_admin_can_update_current_branding_but_not_identity(): void
    {
        $admin = $this->tenantUser();

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'slug' => 'hijacked',
            'status' => 'inactive',
            'gateway' => ['bank_muscat' => ['merchant_id' => 'EVIL']],
            's' => [
                'theme' => ['colors' => ['primary' => '#ABCDEF'], 'font_family_ar' => 'Tajawal'],
                'wallet' => ['refunds' => ['enabled' => '0']],
            ],
        ])->assertRedirect(route('admin.branding.edit'));

        $tenant = $this->tenant->fresh();
        $this->assertSame('north-batinah', $tenant->slug);
        $this->assertSame('active', $tenant->status);
        $this->assertSame('الشمال', $tenant->name['ar']);
        $this->assertSame('#ABCDEF', $tenant->setting('theme.colors.primary'));
        $this->assertSame('Tajawal', $tenant->setting('theme.font_family_ar'));
        $this->assertFalse($tenant->setting('wallet.refunds.enabled'));
        $this->assertNull(data_get($tenant->gateway_credentials, 'bank_muscat.merchant_id'));

        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('#ABCDEF', false);
    }

    public function test_tenant_switch(): void
    {
        $b = $this->otherTenant();

        // Tenant admin of north-batinah only: switching to B is rejected.
        $admin = $this->tenantUser();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->post(route('admin.tenant.switch'), ['tenant_id' => $b->id])
            ->assertSessionHas('error');
        $this->assertSame($this->tenant->id, session(AdminMiddleware::SESSION_KEY));

        // Super-admin can switch to any tenant.
        $super = $this->superAdmin();
        $this->actingAs($super)->post(route('admin.tenant.switch'), ['tenant_id' => $b->id])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame($b->id, session(AdminMiddleware::SESSION_KEY));
        $this->actingAs($super)->get(route('admin.dashboard'))->assertOk()->assertSee('B');
    }

    public function test_submitting_the_untouched_form_stores_no_overrides(): void
    {
        $super = $this->superAdmin();
        $tenant = $this->otherTenant('fresh');
        $tenant->gateway_credentials = ['bank_muscat' => ['merchant_id' => 'KEEP']];
        $tenant->save();

        $html = $this->actingAs($super)->get(route('admin.tenants.edit', $tenant))->assertOk()->getContent();
        $data = $this->formData($html, 'tenantForm');
        $this->assertArrayHasKey('advanced', $data);
        $this->assertSame('PUT', $data['_method']);
        $this->assertSame('#2F8E80', $data['s']['theme']['colors']['primary']);
        $this->assertCount(count(config('tenancy.defaults.modules')), $data['s']['modules']);
        $this->assertSame('24.3474', $data['s']['map']['center']['lat']);

        $this->actingAs($super)->put(route('admin.tenants.update', $tenant), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.tenants.edit', $tenant));

        $tenant->refresh();
        $this->assertSame([], $tenant->settings ?? []);
        $this->assertSame('KEEP', $tenant->gateway_credentials['bank_muscat']['merchant_id']);

        // Same for the current-tenant branding form.
        $admin = $this->tenantUser();
        $html = $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->getContent();
        $before = $this->tenant->fresh()->settings;
        $this->actingAs($admin)->put(route('admin.branding.update'), $this->formData($html, 'tenantForm'))
            ->assertSessionHasNoErrors();
        $this->assertEquals($before, $this->tenant->fresh()->settings);
    }

    /** Values a browser would submit for the given form (no file inputs). */
    public function test_platform_admin_sets_thawani_per_tenant(): void
    {
        $super = $this->superAdmin();
        $tenant = $this->otherTenant('thawani-org');

        $html = $this->actingAs($super)->get(route('admin.tenants.edit', $tenant))->assertOk()
            ->assertSee('Thawani Pay')
            ->assertSee(route('payments.webhooks.thawani', ['tenant' => 'thawani-org']))
            ->getContent();
        $data = $this->formData($html, 'tenantForm');
        $data['payments_driver'] = 'thawani';
        $data['thawani_mode'] = 'production';
        $data['gateway']['thawani'] = ['secret_key' => 'sk_live_value', 'publishable_key' => 'pk_live_value', 'webhook_secret' => 'wh_live_value'];

        $this->actingAs($super)->put(route('admin.tenants.update', $tenant), $data)->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame('thawani', $tenant->setting('payments.driver'));
        $this->assertSame('production', $tenant->setting('payments.thawani.mode'));
        $this->assertSame('sk_live_value', $tenant->gatewayCredential('thawani', 'secret_key'));
        // Encrypted at rest.
        $this->assertStringNotContainsString('sk_live_value', (string) \DB::table('tenants')->where('id', $tenant->id)->value('gateway_credentials'));

        // Back to the platform default: the keys are removed, not stored empty.
        $data = $this->formData($this->actingAs($super)->get(route('admin.tenants.edit', $tenant))->getContent(), 'tenantForm');
        $this->assertSame('thawani', $data['payments_driver']);
        $data['payments_driver'] = '';
        $data['thawani_mode'] = '';
        $this->actingAs($super)->put(route('admin.tenants.update', $tenant), $data)->assertSessionHasNoErrors();
        $this->assertNull(data_get($tenant->fresh()->settings, 'payments'));
    }

    private function formData(string $html, string $formId): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $form = (new \DOMXPath($dom))->query("//form[@id='$formId']")->item(0);
        $this->assertNotNull($form);

        $pairs = [];
        foreach ((new \DOMXPath($dom))->query('.//input|.//select|.//textarea', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            $tag = strtolower($el->nodeName);
            $type = strtolower($el->getAttribute('type'));
            if ($tag === 'input' && in_array($type, ['file', 'submit', 'button'], true)) {
                continue;
            }
            if ($tag === 'input' && in_array($type, ['checkbox', 'radio'], true) && ! $el->hasAttribute('checked')) {
                continue;
            }
            if ($tag === 'select') {
                $value = null;
                foreach ($el->getElementsByTagName('option') as $option) {
                    $value ??= $option->getAttribute('value');
                    if ($option->hasAttribute('selected')) {
                        $value = $option->getAttribute('value');
                        break;
                    }
                }
            } elseif ($tag === 'textarea') {
                $value = $el->textContent;
            } else {
                $value = $el->getAttribute('value');
            }
            $pairs[] = urlencode($name).'='.urlencode((string) $value);
        }
        parse_str(implode('&', $pairs), $data);

        return $data;
    }
}
