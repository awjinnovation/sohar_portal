<?php

namespace Tests\Feature\Discover;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class DiscoverAdminTest extends DiscoverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
    }

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    #[Test]
    public function pages_render_for_admins_and_content_managers(): void
    {
        $cat = $this->category();
        $place = $this->place(null, ['category_id' => $cat->id]);
        $col = $this->collection();
        $item = $this->item($col);
        $page = $this->page();

        foreach (['admin', 'content-manager'] as $role) {
            $this->actingAs($this->admin($role));
            foreach ([route('admin.places.index'), route('admin.places.create'), route('admin.places.edit', $place), route('admin.places.map'),
                route('admin.place-categories.index'), route('admin.place-categories.create'), route('admin.place-categories.edit', $cat),
                route('admin.content-collections.index'), route('admin.content-collections.create'), route('admin.content-collections.edit', $col),
                route('admin.content-items.index', $col), route('admin.content-items.create', $col), route('admin.content-items.edit', [$col, $item]),
                route('admin.content-pages.index'), route('admin.content-pages.create'), route('admin.content-pages.edit', $page)] as $url) {
                $this->get($url)->assertOk();
            }
        }
        $this->get(route('admin.places.index'))->assertSee('قلعة صحار')->assertSee('Material+Symbols+Rounded', false)->assertSee('leaflet/1.9.4', false);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.places.index'), false);
    }

    #[Test]
    public function roles_without_the_permissions_are_forbidden(): void
    {
        $this->actingAs($this->admin('event-manager'));
        $this->get(route('admin.places.index'))->assertForbidden();
        $this->get(route('admin.content-collections.index'))->assertForbidden();
        $this->get(route('admin.content-pages.index'))->assertForbidden();
        $this->post(route('admin.places.store'), [])->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.places.index'), false);
    }

    #[Test]
    public function admin_creates_and_updates_a_place_with_media_events_and_sanitized_html(): void
    {
        $this->actingAs($this->admin());
        $cat = $this->category();
        $event = $this->makeEvent();

        $res = $this->post(route('admin.places.store'), [
            'name' => ['ar' => 'قلعة صحار', 'en' => 'Sohar Fort'],
            'summary' => ['ar' => 'قلعة تاريخية', 'en' => ''],
            'description' => ['ar' => '<p>نص <script>alert(1)</script><b>مهم</b></p>', 'en' => 'Line one'],
            'wilayat' => ['ar' => 'صحار', 'en' => 'Sohar'],
            'category_id' => $cat->id, 'icon' => 'castle',
            'latitude' => '24.36203', 'longitude' => '56.74929',
            'entry_fee' => '0.5', 'link_url' => 'https://example.om', 'phone' => '+968 2684 0000',
            'status' => 'published', 'featured' => '1',
            'events' => [$event->id],
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            'gallery' => [UploadedFile::fake()->image('g1.jpg', 800, 600), UploadedFile::fake()->image('g2.png', 600, 600)],
        ]);
        $place = Place::firstOrFail();
        $res->assertRedirect(route('admin.places.edit', $place));

        $this->assertSame('sohar-fort', $place->slug);
        $this->assertSame(['en' => null, 'ar' => 'قلعة تاريخية'], $place->summary);
        $this->assertSame('<p>نص <b>مهم</b></p>', $place->description['ar']);
        $this->assertSame('<p>Line one</p>', $place->description['en']);
        $this->assertSame(500, $place->entry_fee_minor);
        $this->assertTrue($place->featured);
        $this->assertNotNull($place->published_at);
        $this->assertSame([$event->id], $place->events()->pluck('events.id')->all());
        $this->assertTrue($place->hasMedia('cover'));
        $this->assertSame(1200, $place->getFirstMedia('cover')->getCustomProperty('width'));
        $this->assertTrue($place->getFirstMedia('cover')->hasGeneratedConversion('thumb'));
        $gallery = $place->getMedia('gallery');
        $this->assertCount(2, $gallery);
        Storage::disk('public')->assertExists($gallery[0]->getPathRelativeToRoot());

        // Reorder + remove one gallery image, switch to draft, clear the fee.
        [$g1, $g2] = [$gallery[0]->id, $gallery[1]->id];
        $this->put(route('admin.places.update', $place), [
            'name' => ['ar' => 'قلعة صحار', 'en' => 'Sohar Fort'], 'latitude' => 24.36, 'longitude' => 56.74,
            'status' => 'draft', 'entry_fee' => '', 'gallery_order' => [$g2, $g1], 'remove_media' => [$g1],
            'gallery' => [UploadedFile::fake()->image('g3.jpg', 640, 480)],
        ])->assertRedirect();
        $place->refresh();
        $this->assertSame('draft', $place->status);
        $this->assertNull($place->published_at);
        $this->assertNull($place->entry_fee_minor);
        $this->assertSame([], $place->events()->pluck('events.id')->all());
        $ids = $place->getMedia('gallery')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertSame($g2, $ids[0]);
        $this->assertNotContains($g1, $ids);
        $this->assertTrue($place->hasMedia('cover'));   // untouched without remove_cover

        // Featured toggle + reorder endpoint + delete.
        $this->assertFalse($place->featured);   // not posted in the update = off
        $this->post(route('admin.places.featured', $place))->assertRedirect();
        $this->assertTrue($place->fresh()->featured);
        $other = $this->place(null, ['slug' => 'other', 'sort' => 7]);
        $place->forceFill(['sort' => 3])->save();
        $this->postJson(route('admin.places.reorder'), ['ids' => [$other->id, $place->id]])->assertOk();
        $this->assertSame(3, $other->fresh()->sort);
        $this->assertSame(7, $place->fresh()->sort);
        $this->delete(route('admin.places.destroy', $place))->assertRedirect(route('admin.places.index'));
        $this->assertNull(Place::find($place->id));
    }

    #[Test]
    public function place_validation_requires_a_name_and_coordinates_and_rejects_bad_files(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.places.store'), ['status' => 'published'])
            ->assertSessionHasErrors(['name', 'name.ar', 'name.en', 'latitude', 'longitude']);
        $this->post(route('admin.places.store'), [
            'name' => ['en' => 'X'], 'latitude' => 24, 'longitude' => 56, 'status' => 'draft', 'icon' => 'Bad Icon!',
            'cover' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'), 'link_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['icon', 'cover', 'link_url']);
        $this->assertSame(0, Place::count());
    }

    #[Test]
    public function admin_cannot_touch_another_tenants_rows(): void
    {
        $other = $this->makeTenant('other');
        $foreignPlace = $this->place($other, ['slug' => 'foreign']);
        $foreignCat = $this->category($other, ['key' => 'foreign']);
        $foreignCol = $this->collection($other);
        $this->actingAs($this->admin());

        $this->get(route('admin.places.edit', $foreignPlace))->assertNotFound();
        $this->delete(route('admin.places.destroy', $foreignPlace))->assertNotFound();
        $this->get(route('admin.content-items.index', $foreignCol))->assertNotFound();
        // Foreign category id is not accepted.
        $this->post(route('admin.places.store'), ['name' => ['en' => 'Mine'], 'latitude' => 24, 'longitude' => 56, 'status' => 'draft', 'category_id' => $foreignCat->id])
            ->assertSessionHasErrors('category_id');
        $this->postJson(route('admin.places.reorder'), ['ids' => [$foreignPlace->id]])->assertOk();
        $this->assertSame(1, Place::withoutTenantScope()->find($foreignPlace->id)->sort);
    }

    #[Test]
    public function categories_collections_items_and_pages_crud(): void
    {
        $this->actingAs($this->admin('content-manager'));

        $this->post(route('admin.place-categories.store'), ['name' => ['ar' => 'شواطئ', 'en' => 'Beaches'], 'icon' => 'beach_access', 'color' => '#0288d1', 'is_active' => '1'])
            ->assertRedirect(route('admin.place-categories.index'));
        $cat = PlaceCategory::firstOrFail();
        $this->assertSame(['beaches', '#0288D1'], [$cat->key, $cat->color]);
        $this->post(route('admin.place-categories.store'), ['name' => ['en' => 'X'], 'icon' => 'park', 'color' => 'red'])->assertSessionHasErrors('color');

        $this->post(route('admin.content-collections.store'), ['name' => ['ar' => 'الشركاء', 'en' => 'Partners'], 'key' => 'partners', 'layout' => 'logos', 'show_in_discover' => '1', 'is_active' => '1'])->assertRedirect();
        $col = ContentCollection::firstOrFail();
        $this->post(route('admin.content-collections.store'), ['name' => ['en' => 'Dup'], 'key' => 'partners', 'layout' => 'list'])->assertSessionHasErrors('key');
        $this->post(route('admin.content-collections.store'), ['name' => ['en' => 'Bad'], 'layout' => 'carousel'])->assertSessionHasErrors('layout');

        $place = $this->place();
        $this->post(route('admin.content-items.store', $col), [
            'title' => ['ar' => 'شريك', 'en' => 'Partner Co'], 'status' => 'published', 'tags' => 'gold، sponsor, gold',
            'location_mode' => 'place', 'place_id' => $place->id, 'link_url' => 'https://partner.example',
            'image' => UploadedFile::fake()->image('logo.png', 400, 200),
        ])->assertRedirect();
        $item = ContentItem::firstOrFail();
        $this->assertSame(['partner-co', ['gold', 'sponsor'], $place->id], [$item->slug, $item->tags, $item->place_id]);
        $this->assertTrue($item->hasMedia('image'));

        $this->put(route('admin.content-items.update', [$col, $item]), [
            'title' => ['en' => 'Partner Co'], 'status' => 'published', 'location_mode' => 'own', 'latitude' => 24.1, 'longitude' => 56.2, 'remove_image' => '1',
        ])->assertRedirect();
        $item->refresh();
        $this->assertSame([null, 24.1, 56.2], [$item->place_id, $item->latitude, $item->longitude]);
        $this->assertFalse($item->hasMedia('image'));
        $this->put(route('admin.content-items.update', [$col, $item]), ['title' => ['en' => 'P'], 'status' => 'published', 'location_mode' => 'own'])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->post(route('admin.content-pages.store'), [
            'title' => ['ar' => 'عن المحافظة', 'en' => 'About'], 'body' => ['ar' => '<p>نص</p><img src=x onerror=alert(1)>', 'en' => ''],
            'status' => 'published', 'show_in_more' => '1', 'cover' => UploadedFile::fake()->image('c.jpg', 900, 500),
        ])->assertRedirect();
        $page = ContentPage::firstOrFail();
        $this->assertSame('<p>نص</p>', $page->body['ar']);
        $this->assertSame('about', $page->slug);
        $this->assertTrue($page->hasMedia('cover'));

        $this->delete(route('admin.content-collections.destroy', $col))->assertRedirect();
        $this->assertSame(0, ContentItem::count());
        $this->delete(route('admin.content-pages.destroy', $page))->assertRedirect();
        $this->delete(route('admin.place-categories.destroy', $cat))->assertRedirect();
        $this->assertSame(0, PlaceCategory::count());
    }

    #[Test]
    public function items_are_bound_to_their_collection(): void
    {
        $this->actingAs($this->admin());
        $a = $this->collection();
        $b = $this->collection(null, ['key' => 'other']);
        $item = $this->item($a);
        $this->get(route('admin.content-items.edit', [$b, $item]))->assertNotFound();
        $this->delete(route('admin.content-items.destroy', [$b, $item]))->assertNotFound();
        $this->assertNotNull($item->fresh());
    }

    #[Test]
    public function tenant_settings_accept_the_new_home_blocks_and_quick_actions(): void
    {
        $this->assertContains('places_featured', \App\Http\Controllers\Admin\TenantController::HOME_BLOCKS);
        $this->assertContains('places', \App\Http\Controllers\Admin\TenantController::QUICK_ACTIONS);
        $this->assertMatchesRegularExpression(\App\Http\Controllers\Admin\TenantController::COLLECTION_BLOCK, 'collection:brands');
        $this->assertDoesNotMatchRegularExpression(\App\Http\Controllers\Admin\TenantController::COLLECTION_BLOCK, 'collection:<x>');
    }
}
