<?php

namespace Tests\Feature\Admin;

use App\Models\EventTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class EventTagCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin role
        $adminRole = Role::create(['name' => 'admin']);

        // Create admin user
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function admin_can_view_event_tags_index_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.event-tags.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.index');
        $response->assertSee('إدارة وسوم الفعاليات');
    }

    #[Test]
    public function admin_can_view_create_event_tag_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.event-tags.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.create');
        $response->assertSee('إضافة وسم جديد');
    }

    #[Test]
    public function admin_can_create_new_event_tag_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => 'Technology',
            'tag_name_ar' => 'تكنولوجيا',
            'color_hex' => '#4A90E2',
            'is_active' => 1,
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'تم إضافة الوسم بنجاح');

        $this->assertDatabaseHas('event_tags', [
            'tag_name' => 'Technology',
            'tag_name_ar' => 'تكنولوجيا',
            'color_hex' => '#4A90E2',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function event_tag_creation_fails_with_invalid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'tag_name' => '', // Required field is empty
            'tag_name_ar' => '', // Required field is empty
            'color_hex' => '#INVALID_COLOR_CODE_TOO_LONG',
        ];

        $response = $this->post(route('admin.event-tags.store'), $invalidData);

        $response->assertSessionHasErrors([
            'tag_name',
            'tag_name_ar',
        ]);

        $this->assertDatabaseCount('event_tags', 0);
    }

    #[Test]
    public function admin_can_view_event_tag_details()
    {
        $this->actingAs($this->admin);

        $tag = EventTag::create([
            'tag_name' => 'Music',
            'tag_name_ar' => 'موسيقى',
            'color_hex' => '#FF5733',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.event-tags.show', $tag));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.show');
        $response->assertSee('Music');
        $response->assertSee('موسيقى');
    }

    #[Test]
    public function admin_can_view_edit_event_tag_page()
    {
        $this->actingAs($this->admin);

        $tag = EventTag::create([
            'tag_name' => 'Sports',
            'tag_name_ar' => 'رياضة',
            'color_hex' => '#FFA726',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.event-tags.edit', $tag));

        $response->assertStatus(200);
        $response->assertViewIs('admin.event-tags.edit');
        $response->assertSee('تعديل الوسم');
        $response->assertSee('Sports');
    }

    #[Test]
    public function admin_can_update_event_tag_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tag = EventTag::create([
            'tag_name' => 'Original Tag',
            'tag_name_ar' => 'الوسم الأصلي',
            'color_hex' => '#000000',
            'is_active' => true,
        ]);

        $updatedData = [
            'tag_name' => 'Updated Tag',
            'tag_name_ar' => 'الوسم المحدث',
            'color_hex' => '#FFFFFF',
            'is_active' => 1,
        ];

        $response = $this->put(route('admin.event-tags.update', $tag), $updatedData);

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'تم تحديث الوسم بنجاح');

        $this->assertDatabaseHas('event_tags', [
            'id' => $tag->id,
            'tag_name' => 'Updated Tag',
            'tag_name_ar' => 'الوسم المحدث',
            'color_hex' => '#FFFFFF',
        ]);
    }

    #[Test]
    public function admin_can_delete_event_tag()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tag = EventTag::create([
            'tag_name' => 'To Be Deleted',
            'tag_name_ar' => 'سيتم الحذف',
            'color_hex' => '#FF0000',
            'is_active' => true,
        ]);

        $response = $this->delete(route('admin.event-tags.destroy', $tag));

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success', 'تم حذف الوسم بنجاح');

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
    public function tag_name_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => '', // Missing required field
            'tag_name_ar' => 'اختبار',
            'color_hex' => '#4A90E2',
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertSessionHasErrors('tag_name');
    }

    #[Test]
    public function tag_name_ar_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => 'Test',
            'tag_name_ar' => '', // Missing required field
            'color_hex' => '#4A90E2',
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertSessionHasErrors('tag_name_ar');
    }

    #[Test]
    public function tag_name_must_be_unique()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        // Create first tag
        EventTag::create([
            'tag_name' => 'Unique Tag',
            'tag_name_ar' => 'وسم فريد',
            'color_hex' => '#4A90E2',
            'is_active' => true,
        ]);

        // Try to create another tag with same name
        $duplicateData = [
            'tag_name' => 'Unique Tag',
            'tag_name_ar' => 'وسم آخر',
            'color_hex' => '#FF5733',
        ];

        $response = $this->post(route('admin.event-tags.store'), $duplicateData);

        $response->assertSessionHasErrors('tag_name');
    }

    #[Test]
    public function tag_name_must_not_exceed_max_length()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => str_repeat('a', 51), // Exceeds max length of 50
            'tag_name_ar' => 'اختبار',
            'color_hex' => '#4A90E2',
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertSessionHasErrors('tag_name');
    }

    #[Test]
    public function color_hex_is_optional()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => 'No Color Tag',
            'tag_name_ar' => 'وسم بدون لون',
            // color_hex not provided
            'is_active' => 1,
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertRedirect(route('admin.event-tags.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('event_tags', [
            'tag_name' => 'No Color Tag',
            'tag_name_ar' => 'وسم بدون لون',
        ]);
    }

    #[Test]
    public function is_active_defaults_to_false_when_not_provided()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $tagData = [
            'tag_name' => 'Inactive Tag',
            'tag_name_ar' => 'وسم غير نشط',
            'color_hex' => '#4A90E2',
            // is_active not provided
        ];

        $response = $this->post(route('admin.event-tags.store'), $tagData);

        $response->assertRedirect(route('admin.event-tags.index'));

        $this->assertDatabaseHas('event_tags', [
            'tag_name' => 'Inactive Tag',
            'is_active' => false,
        ]);
    }
}
