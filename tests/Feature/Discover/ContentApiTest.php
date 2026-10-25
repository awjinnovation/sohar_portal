<?php

namespace Tests\Feature\Discover;

use PHPUnit\Framework\Attributes\Test;

class ContentApiTest extends DiscoverTestCase
{
    #[Test]
    public function collections_items_and_pages_follow_the_contract(): void
    {
        $services = $this->collection();
        $partners = $this->collection(null, ['key' => 'partners', 'layout' => 'logos', 'name' => ['en' => 'Partners', 'ar' => 'الشركاء'], 'sort' => 2]);
        $this->collection(null, ['key' => 'hidden', 'is_active' => false]);
        $place = $this->place();
        $item = $this->item($services, ['place_id' => $place->id, 'tags' => ['health'], 'link_url' => 'https://example.om', 'phone' => '9999']);
        $this->item($services, ['slug' => 'draft', 'status' => 'draft']);
        $this->page();
        $this->page(null, ['slug' => 'draft-page', 'status' => 'draft']);

        $cols = $this->getJson('/api/v1/content/collections', $this->tenantHeaders(null, ['Accept-Language' => 'ar']))->assertOk();
        $this->assertSame(['services', 'partners'], array_column($cols->json('data'), 'key'));
        $this->assertSame(1, $cols->json('data.0.items_count'));
        $this->assertSame(0, $cols->json('data.1.items_count'));       // empty partners collection
        $this->assertSame('logos', $cols->json('data.1.layout'));
        $this->assertSame('خدمات الزوار', $cols->json('data.0.name_text'));

        $items = $this->getJson('/api/v1/content/collections/services/items', $this->tenantHeaders(null, ['Accept-Language' => 'en']))->assertOk();
        $items->assertJsonStructure(['data' => [['id', 'slug', 'title', 'title_text', 'subtitle', 'subtitle_text', 'icon', 'image', 'link_url', 'link_label_text', 'featured', 'tags']], 'pagination']);
        $this->assertCount(1, $items->json('data'));
        $this->assertSame('More info', $items->json('data.0.link_label_text'));
        $this->getJson('/api/v1/content/collections/services/items?tag=health', $this->tenantHeaders())->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/content/collections/services/items?tag=food', $this->tenantHeaders())->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/content/collections/services/items?q=aid', $this->tenantHeaders())->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/content/collections/hidden/items', $this->tenantHeaders())->assertStatus(404);

        $detail = $this->getJson('/api/v1/content/items/'.$item->id, $this->tenantHeaders(null, ['Accept-Language' => 'en']))->assertOk();
        $this->assertSame('<p>Open daily.</p>', $detail->json('data.body_text'));
        $this->assertSame('9999', $detail->json('data.phone'));
        $this->assertSame($place->id, $detail->json('data.place.id'));
        $this->assertSame(24.36203, $detail->json('data.latitude'));      // from the linked place
        $this->assertSame('services', $detail->json('data.collection.key'));

        $pages = $this->getJson('/api/v1/content/pages', $this->tenantHeaders())->assertOk();
        $this->assertSame(['about'], array_column($pages->json('data'), 'slug'));
        $this->assertSame(['id', 'slug', 'title', 'title_text', 'icon', 'cover', 'show_in_more', 'sort'], array_keys($pages->json('data.0')));
        $this->getJson('/api/v1/content/pages/about', $this->tenantHeaders(null, ['Accept-Language' => 'ar']))->assertOk()->assertJsonPath('data.body_text', '<p>مرحبًا</p>');
        $this->getJson('/api/v1/content/pages/draft-page', $this->tenantHeaders())->assertStatus(404);
    }

    #[Test]
    public function content_is_isolated_per_tenant_and_drafts_are_404(): void
    {
        $other = $this->enableModules($this->makeTenant('other'));
        $foreignItem = $this->item($this->collection($other));
        $this->page($other, ['slug' => 'foreign']);
        $draft = $this->item($this->collection(), ['status' => 'draft']);

        $this->getJson('/api/v1/content/items/'.$foreignItem->id, $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/content/items/'.$draft->id, $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/content/pages/foreign', $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/content/items/'.$foreignItem->id, $this->tenantHeaders($other))->assertOk();
    }

    #[Test]
    public function tenant_config_exposes_modules_and_filters_the_discover_tab(): void
    {
        $this->tenant->putSetting('navigation', ['home', 'events', 'discover', 'more'])
            ->putSetting('home_blocks', ['places_featured', 'collection:services'])->putSetting('quick_actions', ['places'])->save();
        $cfg = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk();
        $this->assertTrue($cfg->json('data.modules.places'));
        $this->assertTrue($cfg->json('data.modules.content'));
        $this->assertContains('discover', $cfg->json('data.navigation'));
        $this->assertSame(['places_featured', 'collection:services'], $cfg->json('data.home_blocks'));

        $this->enableModules($this->tenant, false, false);
        $cfg = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk();
        $this->assertNotContains('discover', $cfg->json('data.navigation'));

        // New tenants: both modules off by default.
        $fresh = $this->makeTenant('fresh');
        $this->assertFalse($fresh->moduleEnabled('places'));
        $this->assertFalse($fresh->moduleEnabled('content'));
    }

    #[Test]
    public function api_and_storage_paths_send_cors_headers_for_web_builds(): void
    {
        $this->assertSame(['api/*', 'sanctum/csrf-cookie', 'storage/*', 'tenants/*', 'images/*'], config('cors.paths'));
        $this->getJson('/api/v1/places/map', $this->tenantHeaders(null, ['Origin' => 'http://localhost:5000']))
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        // Tenant branding (logos, onboarding images) is fetched by the web builds too.
        $this->get('/tenants/north-batinah/logo.png', ['Origin' => 'http://localhost:5000'])->assertHeader('Access-Control-Allow-Origin', '*');
        // Admin / web routes stay without CORS.
        $this->get('/admin/login', ['Origin' => 'http://localhost:5000'])->assertHeaderMissing('Access-Control-Allow-Origin');
        // The dev router (php artisan serve) adds the same header to /storage/*, /tenants/* and /images/* files.
        $router = file_get_contents(base_path('server.php'));
        $this->assertStringContainsString("header('Access-Control-Allow-Origin: *')", $router);
        $this->assertStringContainsString("['/storage/', '/tenants/', '/images/']", $router);
    }

    #[Test]
    public function cors_origins_come_from_the_environment(): void
    {
        $key = 'CORS_ALLOWED_ORIGINS';
        $saved = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
        $load = function (?string $value) use ($key): array {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
            if ($value !== null) {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv("$key=$value");
            }

            return (require config_path('cors.php'))['allowed_origins'];
        };

        $this->assertSame(['*'], $load(null));
        $this->assertSame(['*'], $load(''));
        $this->assertSame(['https://gate.mawsim.awj.om', 'http://localhost:5173'], $load(' https://gate.mawsim.awj.om/, http://localhost:5173 '));
        $this->assertSame(['*'], $load('https://gate.mawsim.awj.om,*'));

        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);
        if ($saved[0] !== null) {
            $_SERVER[$key] = $saved[0];
        }
        if ($saved[1] !== null) {
            $_ENV[$key] = $saved[1];
        }
        if ($saved[2] !== false) {
            putenv("$key={$saved[2]}");
        }
    }

    #[Test]
    public function seeded_tenants_keep_their_approved_navigation(): void
    {
        $nb = \Database\Seeders\TenantSeeder::northBatinah()['settings'];
        $this->assertSame(['home', 'events', 'tickets', 'map', 'more'], $nb['navigation']);
        $this->assertSame(['topup', 'pay', 'tickets', 'ai', 'points'], $nb['quick_actions']);
        $this->assertContains('places_featured', $nb['home_blocks']);
        $demo = \Database\Seeders\TenantSeeder::demo()['settings'];
        $this->assertSame(['home', 'events', 'wallet', 'tickets', 'map', 'more'], $demo['navigation']);
        $this->assertContains('collection:brands', $demo['home_blocks']);
    }
}
