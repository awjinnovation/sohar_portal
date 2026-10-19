<?php

namespace Tests\Feature\Discover;

use App\Models\Place;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class PlacesApiTest extends DiscoverTestCase
{
    #[Test]
    public function list_and_detail_follow_the_contract_shape(): void
    {
        Storage::fake('public');
        $cat = $this->category();
        $place = $this->place(null, ['category_id' => $cat->id, 'featured' => true, 'entry_fee_minor' => 500,
            'description' => ['en' => '<p>Big fort</p>', 'ar' => '<p>قلعة كبيرة</p>'],
            'link_url' => 'https://example.om', 'opening_hours' => ['en' => 'Daily 8–18', 'ar' => 'يوميًا']]);
        $this->inTenant($this->tenant, fn () => $place->addMedia(UploadedFile::fake()->image('fort.jpg', 1200, 800))->toMediaCollection('cover'));
        $event = $this->makeEvent(null, ['title' => 'Fort Night', 'end_time' => now()->addDays(3)]);
        $place->events()->attach($event->id);

        $res = $this->getJson('/api/v1/places', $this->tenantHeaders(null, ['Accept-Language' => 'ar']))->assertOk();
        $res->assertJsonStructure(['success', 'data' => [['id', 'slug', 'name', 'name_text', 'summary', 'summary_text',
            'category' => ['id', 'key', 'name', 'name_text', 'icon', 'color'], 'icon', 'latitude', 'longitude',
            'cover' => ['url', 'thumb_url', 'width', 'height'], 'wilayat', 'wilayat_text', 'featured', 'entry_fee_minor', 'entry_fee']],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total']]);
        $row = $res->json('data.0');
        $this->assertSame('قلعة صحار', $row['name_text']);
        $this->assertSame(['en' => 'Sohar Fort', 'ar' => 'قلعة صحار'], $row['name']);
        $this->assertSame('fort', $row['icon']);               // category icon (no override)
        $this->assertSame('0.500', $row['entry_fee']);
        $this->assertSame(1200, $row['cover']['width']);
        $this->assertStringContainsString('thumb', $row['cover']['thumb_url']);
        $this->assertArrayNotHasKey('distance_m', $row);

        $detail = $this->getJson('/api/v1/places/sohar-fort', $this->tenantHeaders(null, ['Accept-Language' => 'en']))->assertOk();
        $this->assertSame('Sohar Fort', $detail->json('data.name_text'));
        $this->assertSame('<p>Big fort</p>', $detail->json('data.description_text'));
        $this->assertSame('More info', $detail->json('data.link_label_text'));   // default label
        $this->assertSame('Daily 8–18', $detail->json('data.opening_hours_text'));
        $this->assertSame([], $detail->json('data.gallery'));
        $this->assertSame($event->id, $detail->json('data.events.0.id'));
        $this->assertStringContainsString('/discover/places/sohar-fort', $detail->json('data.share_url'));
        // by id too
        $this->getJson('/api/v1/places/'.$place->id, $this->tenantHeaders())->assertOk()->assertJsonPath('data.slug', 'sohar-fort');
        // share page
        $this->get('/discover/places/sohar-fort?tenant='.$this->tenant->slug.'&lang=en')->assertOk()->assertSee('Sohar Fort');
    }

    #[Test]
    public function drafts_scheduled_and_inactive_category_places_are_hidden(): void
    {
        $inactive = $this->category(null, ['key' => 'old', 'is_active' => false]);
        $this->place(null, ['slug' => 'draft-place', 'status' => 'draft']);
        $this->place(null, ['slug' => 'future', 'published_at' => now()->addDay()]);
        $this->place(null, ['slug' => 'hidden-cat', 'category_id' => $inactive->id]);
        $this->place(null, ['slug' => 'visible']);

        $this->getJson('/api/v1/places', $this->tenantHeaders())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'visible');
        $this->getJson('/api/v1/places/draft-place', $this->tenantHeaders())->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/api/v1/places/future', $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/places/map', $this->tenantHeaders())->assertJsonCount(1, 'data');
        $this->get('/discover/places/draft-place?tenant='.$this->tenant->slug)->assertNotFound();
    }

    #[Test]
    public function places_are_isolated_per_tenant(): void
    {
        $other = $this->enableModules($this->makeTenant('other'));
        $foreign = $this->place($other, ['slug' => 'foreign', 'name' => ['en' => 'Foreign Fort', 'ar' => 'قلعة أخرى']]);
        $this->place();

        $this->getJson('/api/v1/places', $this->tenantHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'sohar-fort');
        $this->getJson('/api/v1/places/'.$foreign->id, $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/places/foreign', $this->tenantHeaders())->assertStatus(404);
        $this->getJson('/api/v1/places/foreign', $this->tenantHeaders($other))->assertOk();
        $this->getJson('/api/v1/places/categories', $this->tenantHeaders($other))->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function module_off_answers_module_disabled(): void
    {
        $this->enableModules($this->tenant, false, false);
        foreach (['/api/v1/places', '/api/v1/places/map', '/api/v1/places/categories', '/api/v1/places/x',
            '/api/v1/content/collections', '/api/v1/content/pages', '/api/v1/content/items/1', '/api/v1/content/collections/a/items'] as $url) {
            $this->getJson($url, $this->tenantHeaders())->assertStatus(403)->assertJsonPath('code', 'MODULE_DISABLED');
        }
    }

    #[Test]
    public function near_sorts_by_distance_and_adds_distance_m(): void
    {
        $this->place(null, ['slug' => 'suwaiq', 'latitude' => 23.84915, 'longitude' => 57.43970, 'sort' => 1]);
        $this->place(null, ['slug' => 'shinas', 'latitude' => 24.74421, 'longitude' => 56.46637, 'sort' => 2]);
        $this->place(null, ['slug' => 'sohar', 'latitude' => 24.36203, 'longitude' => 56.74929, 'sort' => 3]);

        $res = $this->getJson('/api/v1/places?near=24.3650,56.7450', $this->tenantHeaders())->assertOk();
        $this->assertSame(['sohar', 'shinas', 'suwaiq'], array_column($res->json('data'), 'slug'));
        $this->assertLessThan(1000, $res->json('data.0.distance_m'));
        $this->assertGreaterThan(40000, $res->json('data.1.distance_m'));
        $this->assertSame(3, $res->json('pagination.total'));

        $page2 = $this->getJson('/api/v1/places?near=24.3650,56.7450&per_page=2&page=2', $this->tenantHeaders())->assertOk();
        $this->assertSame(['suwaiq'], array_column($page2->json('data'), 'slug'));
        $this->getJson('/api/v1/places?near=abc', $this->tenantHeaders())->assertStatus(422);
    }

    #[Test]
    public function filters_search_and_categories_count(): void
    {
        $forts = $this->category();
        $beaches = $this->category(null, ['key' => 'beaches', 'name' => ['en' => 'Beaches', 'ar' => 'شواطئ'], 'icon' => 'beach_access', 'sort' => 2]);
        $this->place(null, ['category_id' => $forts->id, 'featured' => true]);
        $this->place(null, ['slug' => 'corniche', 'name' => ['en' => 'Sohar Corniche', 'ar' => 'كورنيش صحار'], 'category_id' => $beaches->id, 'icon' => 'waves']);
        $this->place(null, ['slug' => 'draft-beach', 'category_id' => $beaches->id, 'status' => 'draft']);

        $this->getJson('/api/v1/places?category=beaches', $this->tenantHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.icon', 'waves');
        $this->getJson('/api/v1/places?q='.urlencode('كورنيش'), $this->tenantHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'corniche');
        $this->getJson('/api/v1/places?featured=1', $this->tenantHeaders())->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'sohar-fort');
        $cats = $this->getJson('/api/v1/places/categories', $this->tenantHeaders(null, ['Accept-Language' => 'ar']))->assertOk();
        $this->assertSame([['forts', 1, 'قلاع'], ['beaches', 1, 'شواطئ']], array_map(fn ($c) => [$c['key'], $c['places_count'], $c['name_text']], $cats->json('data')));
    }

    #[Test]
    public function map_is_compact_and_supports_etag(): void
    {
        $this->place(null, ['category_id' => $this->category()->id]);
        $res = $this->getJson('/api/v1/places/map', $this->tenantHeaders())->assertOk();
        $this->assertSame(['id', 'name', 'name_text', 'latitude', 'longitude', 'icon', 'color', 'category_key', 'featured'], array_keys($res->json('data.0')));
        $res = $this->getJson('/api/v1/places/map', $this->tenantHeaders(null, ['Accept-Language' => 'ar']))->assertOk();
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->get('/api/v1/places/map', $this->tenantHeaders(null, ['If-None-Match' => $etag, 'Accept-Language' => 'ar']))->assertStatus(304);
        // Language changes the payload, hence the ETag.
        $en = $this->getJson('/api/v1/places/map', $this->tenantHeaders(null, ['Accept-Language' => 'en']));
        $this->assertNotSame($etag, $en->headers->get('ETag'));
    }

    #[Test]
    public function html_bodies_are_sanitized_on_output(): void
    {
        $place = $this->place();
        // Written around the admin (e.g. by a script): still sanitized when served.
        Place::withoutTenantScope()->whereKey($place->id)->update(['description' => json_encode([
            'en' => '<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://ok.om">ok</a></p><iframe src="https://evil"></iframe>',
            'ar' => null,
        ])]);
        $html = $this->getJson('/api/v1/places/sohar-fort', $this->tenantHeaders(null, ['Accept-Language' => 'en']))->json('data.description_text');
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringContainsString('<a href="https://ok.om" rel="noopener nofollow" target="_blank">ok</a>', $html);
        $this->assertStringContainsString('bad', $html);
    }
}
