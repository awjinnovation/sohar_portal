<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Event tags belong to one event (event_tags: event_id, tag, tag_ar).
 */
class EventTagCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;

    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Global role definitions; the assignment below is made in the
        // current tenant (north-batinah, set by the base TestCase).
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create admin user
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');

        $this->event = $this->makeEvent('Tagged Event', 'فعالية موسومة');
    }

    private function makeEvent(string $title, string $titleAr): Event
    {
        $category = Category::create([
            'name' => 'Music', 'name_ar' => 'موسيقى', 'description' => 'd', 'description_ar' => 'd',
            'icon_name' => 'music', 'color_value' => 4283215696, 'is_active' => true,
        ]);

        return Event::create([
            'title' => $title, 'title_ar' => $titleAr, 'description' => 'd', 'description_ar' => 'd',
            'category_id' => $category->id,
            'start_time' => now()->addDay(), 'end_time' => now()->addDays(2),
            'location' => 'Hall', 'location_ar' => 'قاعة', 'price' => 0, 'is_active' => true,
        ]);
    }

    private function makeTag(array $attributes = []): EventTag
    {
        return EventTag::create(array_merge([
            'event_id' => $this->event->id,
            'tag' => 'Music',
            'tag_ar' => 'موسيقى',
        ], $attributes));
    }

    #[Test]
    public function admin_can_view_event_tags_index_page()
    {
        $this->makeTag(['tag' => 'Listed', 'tag_ar' => 'مدرج']);
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.event-tags.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.index');
        $response->assertSee('إدارة وسوم الفعاليات');
        $response->assertSee('مدرج');
        $response->assertSee('فعالية موسومة');
    }

    #[Test]
    public function admin_can_view_create_event_tag_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.event-tags.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.create');
        $response->assertSee('إضافة وسم جديد');
        $response->assertSee('فعالية موسومة');
    }

    #[Test]
    public function admin_can_create_new_event_tag_with_valid_data()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => $this->event->id,
            'tag' => 'Technology',
            'tag_ar' => 'تكنولوجيا',
        ]);

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'أُضيف الوسم');

        $this->assertDatabaseHas('event_tags', [
            'event_id' => $this->event->id,
            'tag' => 'Technology',
            'tag_ar' => 'تكنولوجيا',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function event_tag_creation_fails_with_invalid_data()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => '',
            'tag' => '',
            'tag_ar' => '',
        ]);

        $response->assertSessionHasErrors(['event_id', 'tag', 'tag_ar']);

        $this->assertDatabaseCount('event_tags', 0);
    }

    #[Test]
    public function admin_can_view_event_tag_details()
    {
        $this->actingAs($this->admin);

        $tag = $this->makeTag(['tag' => 'Music', 'tag_ar' => 'موسيقى']);

        $response = $this->get(route('admin.event-tags.show', $tag));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.show');
        $response->assertSee('Music');
        $response->assertSee('موسيقى');
        $response->assertSee('فعالية موسومة');
    }

    #[Test]
    public function admin_can_view_edit_event_tag_page()
    {
        $this->actingAs($this->admin);

        $tag = $this->makeTag(['tag' => 'Sports', 'tag_ar' => 'رياضة']);

        $response = $this->get(route('admin.event-tags.edit', $tag));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.edit');
        $response->assertSee('تعديل الوسم');
        $response->assertSee('Sports');
    }

    #[Test]
    public function admin_can_update_event_tag_with_valid_data()
    {
        $this->actingAs($this->admin);

        $tag = $this->makeTag(['tag' => 'Original Tag', 'tag_ar' => 'الوسم الأصلي']);
        $otherEvent = $this->makeEvent('Other Event', 'فعالية أخرى');

        $response = $this->put(route('admin.event-tags.update', $tag), [
            'event_id' => $otherEvent->id,
            'tag' => 'Updated Tag',
            'tag_ar' => 'الوسم المحدث',
        ]);

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'حُدِّث الوسم');

        $this->assertDatabaseHas('event_tags', [
            'id' => $tag->id,
            'event_id' => $otherEvent->id,
            'tag' => 'Updated Tag',
            'tag_ar' => 'الوسم المحدث',
        ]);
    }

    #[Test]
    public function admin_can_delete_event_tag()
    {
        $this->actingAs($this->admin);

        $tag = $this->makeTag(['tag' => 'To Be Deleted']);

        $response = $this->delete(route('admin.event-tags.destroy', $tag));

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'حُذف الوسم');

        $this->assertDatabaseMissing('event_tags', [
            'id' => $tag->id,
        ]);
    }

    #[Test]
    public function guest_cannot_access_event_tags_pages()
    {
        $response = $this->get(route('admin.event-tags.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.event-tags.create'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function tag_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => $this->event->id,
            'tag' => '',
            'tag_ar' => 'اختبار',
        ]);

        $response->assertSessionHasErrors('tag');
    }

    #[Test]
    public function tag_ar_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => $this->event->id,
            'tag' => 'Test',
            'tag_ar' => '',
        ]);

        $response->assertSessionHasErrors('tag_ar');
    }

    #[Test]
    public function tag_must_be_unique_per_event()
    {
        $this->actingAs($this->admin);

        $this->makeTag(['tag' => 'Unique Tag']);

        $duplicate = $this->post(route('admin.event-tags.store'), [
            'event_id' => $this->event->id,
            'tag' => 'Unique Tag',
            'tag_ar' => 'وسم آخر',
        ]);
        $duplicate->assertSessionHasErrors('tag');

        // The same tag on another event is fine.
        $otherEvent = $this->makeEvent('Other Event', 'فعالية أخرى');
        $this->post(route('admin.event-tags.store'), [
            'event_id' => $otherEvent->id,
            'tag' => 'Unique Tag',
            'tag_ar' => 'وسم آخر',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function tag_must_not_exceed_max_length()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => $this->event->id,
            'tag' => str_repeat('a', 51),
            'tag_ar' => 'اختبار',
        ]);

        $response->assertSessionHasErrors('tag');
    }

    #[Test]
    public function event_of_another_tenant_is_rejected()
    {
        $this->actingAs($this->admin);

        $other = Tenant::create(['slug' => 'b', 'name' => ['en' => 'B', 'ar' => 'B'], 'status' => 'active']);
        $foreignEvent = app(TenantContext::class)->run($other, fn () => $this->makeEvent('Foreign', 'أجنبية'));

        $response = $this->post(route('admin.event-tags.store'), [
            'event_id' => $foreignEvent->id,
            'tag' => 'Leak',
            'tag_ar' => 'تسريب',
        ]);

        $response->assertSessionHasErrors('event_id');
        $this->assertDatabaseCount('event_tags', 0);
    }
}
