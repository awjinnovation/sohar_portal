<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;

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
    }

    private function makeAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'type' => 'info',
            'priority' => 0,
            'is_active' => true,
            'start_datetime' => now(),
            'end_datetime' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'New Test Announcement',
            'title_ar' => 'إعلان جديد للاختبار',
            'content' => 'This is a test announcement content',
            'content_ar' => 'هذا محتوى إعلان تجريبي',
            'type' => 'warning',
            'priority' => 5,
            'is_pinned' => 1,
            'is_active' => 1,
            'start_datetime' => now()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    #[Test]
    public function admin_can_view_announcements_index_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.announcements.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.announcements.index');
        $response->assertSee('إدارة الإعلانات');
        $response->assertSee('إضافة إعلان');
    }

    #[Test]
    public function admin_can_view_create_announcement_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.announcements.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.announcements.create');
        $response->assertSee('إضافة إعلان جديد');
    }

    #[Test]
    public function admin_can_create_new_announcement_with_valid_data()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload());

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'أُضيف الإعلان');

        $this->assertDatabaseHas('announcements', [
            'title' => 'New Test Announcement',
            'title_ar' => 'إعلان جديد للاختبار',
            'type' => 'warning',
            'priority' => 5,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function announcement_creation_fails_with_invalid_data()
    {
        $this->actingAs($this->admin);

        $invalidData = [
            'title' => '',
            'title_ar' => '',
            'content' => '',
            'content_ar' => '',
            'type' => 'invalid-type',
            'priority' => 'invalid-priority',
            'start_datetime' => 'invalid-date',
            'end_datetime' => 'invalid-date',
        ];

        $response = $this->post(route('admin.announcements.store'), $invalidData);

        $response->assertSessionHasErrors([
            'title',
            'title_ar',
            'content',
            'content_ar',
            'type',
            'priority',
            'start_datetime',
            'end_datetime',
        ]);

        $this->assertDatabaseCount('announcements', 0);
    }

    #[Test]
    public function end_date_must_be_after_start_date()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload([
            'start_datetime' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->format('Y-m-d H:i:s'),
        ]));

        $response->assertSessionHasErrors('end_datetime');
    }

    #[Test]
    public function admin_can_view_announcement_details()
    {
        $this->actingAs($this->admin);

        $announcement = $this->makeAnnouncement([
            'title' => 'Detail Test Announcement',
            'title_ar' => 'إعلان تفاصيل الاختبار',
            'content_ar' => 'محتوى إعلان تجريبي',
        ]);

        $response = $this->get(route('admin.announcements.show', $announcement));

        $response->assertStatus(200);
        $response->assertViewIs('admin.announcements.show');
        $response->assertSee('إعلان تفاصيل الاختبار');
        $response->assertSee('محتوى إعلان تجريبي');
    }

    #[Test]
    public function admin_can_view_edit_announcement_page()
    {
        $this->actingAs($this->admin);

        $announcement = $this->makeAnnouncement([
            'title_ar' => 'إعلان تعديل الاختبار',
        ]);

        $response = $this->get(route('admin.announcements.edit', $announcement));

        $response->assertStatus(200);
        $response->assertViewIs('admin.announcements.edit');
        $response->assertSee('تعديل الإعلان');
        $response->assertSee('إعلان تعديل الاختبار');
    }

    #[Test]
    public function admin_can_update_announcement_with_valid_data()
    {
        $this->actingAs($this->admin);

        $announcement = $this->makeAnnouncement(['title' => 'Original Title']);

        $response = $this->put(route('admin.announcements.update', $announcement), $this->validPayload([
            'title' => 'Updated Title',
            'title_ar' => 'العنوان المحدث',
            'type' => 'celebration',
            'priority' => 9,
        ]));

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'حُدِّث الإعلان');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Title',
            'title_ar' => 'العنوان المحدث',
            'type' => 'celebration',
            'priority' => 9,
        ]);
    }

    #[Test]
    public function admin_can_delete_announcement()
    {
        $this->actingAs($this->admin);

        $announcement = $this->makeAnnouncement(['title' => 'To Be Deleted']);

        $response = $this->delete(route('admin.announcements.destroy', $announcement));

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'حُذف الإعلان');

        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    #[Test]
    public function guest_cannot_access_announcements_pages()
    {
        $response = $this->get(route('admin.announcements.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.announcements.create'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function title_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload(['title' => '']));

        $response->assertSessionHasErrors('title');
    }

    #[Test]
    public function title_ar_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload(['title_ar' => '']));

        $response->assertSessionHasErrors('title_ar');
    }

    #[Test]
    public function content_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload(['content' => '']));

        $response->assertSessionHasErrors('content');
    }

    #[Test]
    public function content_ar_is_required()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload(['content_ar' => '']));

        $response->assertSessionHasErrors('content_ar');
    }

    #[Test]
    public function priority_must_be_valid_value()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), $this->validPayload(['priority' => 'invalid']));

        $response->assertSessionHasErrors('priority');
    }

    #[Test]
    public function type_and_start_datetime_are_required()
    {
        $this->actingAs($this->admin);

        $payload = $this->validPayload();
        unset($payload['type'], $payload['start_datetime']);

        $response = $this->post(route('admin.announcements.store'), $payload);

        $response->assertSessionHasErrors(['type', 'start_datetime']);
    }

    #[Test]
    public function announcement_can_be_created_without_optional_fields()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.announcements.store'), [
            'title' => 'Minimal Announcement',
            'title_ar' => 'إعلان بسيط',
            'content' => 'Minimal content',
            'content_ar' => 'محتوى بسيط',
            'type' => 'info',
            'start_datetime' => now()->format('Y-m-d H:i:s'),
            // Optional: priority, is_pinned, is_active, end_datetime
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Minimal Announcement',
            'title_ar' => 'إعلان بسيط',
            'end_datetime' => null,
        ]);
    }

    #[Test]
    public function admin_can_filter_active_announcements()
    {
        $this->actingAs($this->admin);

        $this->makeAnnouncement([
            'title' => 'Active Announcement',
            'title_ar' => 'إعلان نشط',
            'is_active' => true,
        ]);

        $this->makeAnnouncement([
            'title' => 'Inactive Announcement',
            'title_ar' => 'إعلان غير نشط',
            'is_active' => false,
        ]);

        $response = $this->get(route('admin.announcements.index'));

        $response->assertStatus(200);
        $response->assertSee('إعلان نشط');
    }
}
