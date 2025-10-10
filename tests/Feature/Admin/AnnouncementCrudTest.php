<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class AnnouncementCrudTest extends TestCase
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
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'New Test Announcement',
            'title_ar' => 'إعلان جديد للاختبار',
            'content' => 'This is a test announcement content',
            'content_ar' => 'هذا محتوى إعلان تجريبي',
            'image_url' => 'https://example.com/test-image.jpg',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'priority' => 'high',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'تم إضافة الإعلان بنجاح');

        $this->assertDatabaseHas('announcements', [
            'title' => 'New Test Announcement',
            'title_ar' => 'إعلان جديد للاختبار',
            'priority' => 'high',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function announcement_creation_fails_with_invalid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'title' => '', // Required field is empty
            'title_ar' => '', // Required field is empty
            'content' => '', // Required field is empty
            'content_ar' => '', // Required field is empty
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date',
            'priority' => 'invalid-priority',
        ];

        $response = $this->post(route('admin.announcements.store'), $invalidData);

        $response->assertSessionHasErrors([
            'title',
            'title_ar',
            'content',
            'content_ar',
            'start_date',
            'end_date',
            'priority',
        ]);

        $this->assertDatabaseCount('announcements', 0);
    }

    #[Test]
    public function end_date_must_be_after_start_date()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'), // Before start date
            'priority' => 'medium',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $invalidData);

        $response->assertSessionHasErrors('end_date');
    }

    #[Test]
    public function admin_can_view_announcement_details()
    {
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'title' => 'Detail Test Announcement',
            'title_ar' => 'إعلان تفاصيل الاختبار',
            'content' => 'Test announcement content',
            'content_ar' => 'محتوى إعلان تجريبي',
            'image_url' => 'https://example.com/image.jpg',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'priority' => 'high',
            'is_active' => true,
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

        $announcement = Announcement::create([
            'title' => 'Edit Test Announcement',
            'title_ar' => 'إعلان تعديل الاختبار',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'priority' => 'low',
            'is_active' => true,
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
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'title' => 'Original Title',
            'title_ar' => 'العنوان الأصلي',
            'content' => 'Original content',
            'content_ar' => 'المحتوى الأصلي',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'priority' => 'low',
            'is_active' => true,
        ]);

        $updatedData = [
            'title' => 'Updated Title',
            'title_ar' => 'العنوان المحدث',
            'content' => 'Updated content',
            'content_ar' => 'المحتوى المحدث',
            'image_url' => 'https://example.com/updated-image.jpg',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(14)->format('Y-m-d'),
            'priority' => 'high',
            'is_active' => 1,
        ];

        $response = $this->put(route('admin.announcements.update', $announcement), $updatedData);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'تم تحديث الإعلان بنجاح');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Title',
            'title_ar' => 'العنوان المحدث',
            'priority' => 'high',
        ]);
    }

    #[Test]
    public function admin_can_delete_announcement()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcement = Announcement::create([
            'title' => 'To Be Deleted',
            'title_ar' => 'سيتم الحذف',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'priority' => 'low',
            'is_active' => true,
        ]);

        $response = $this->delete(route('admin.announcements.destroy', $announcement));

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success', 'تم حذف الإعلان بنجاح');

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
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => '', // Missing required field
            'title_ar' => 'إعلان تجريبي',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertSessionHasErrors('title');
    }

    #[Test]
    public function title_ar_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'Test Announcement',
            'title_ar' => '', // Missing required field
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertSessionHasErrors('title_ar');
    }

    #[Test]
    public function content_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'content' => '', // Missing required field
            'content_ar' => 'محتوى تجريبي',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertSessionHasErrors('content');
    }

    #[Test]
    public function content_ar_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'content' => 'Test content',
            'content_ar' => '', // Missing required field
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertSessionHasErrors('content_ar');
    }

    #[Test]
    public function priority_must_be_valid_value()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'Test Announcement',
            'title_ar' => 'إعلان تجريبي',
            'content' => 'Test content',
            'content_ar' => 'محتوى تجريبي',
            'priority' => 'invalid', // Invalid priority value
            'is_active' => true,
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertSessionHasErrors('priority');
    }

    #[Test]
    public function announcement_can_be_created_without_optional_fields()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $announcementData = [
            'title' => 'Minimal Announcement',
            'title_ar' => 'إعلان بسيط',
            'content' => 'Minimal content',
            'content_ar' => 'محتوى بسيط',
            'is_active' => true,
            // Optional fields not provided: image_url, start_date, end_date, priority
        ];

        $response = $this->post(route('admin.announcements.store'), $announcementData);

        $response->assertRedirect(route('admin.announcements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Minimal Announcement',
            'title_ar' => 'إعلان بسيط',
        ]);
    }

    #[Test]
    public function admin_can_filter_active_announcements()
    {
        $this->actingAs($this->admin);

        // Create active announcement
        $activeAnnouncement = Announcement::create([
            'title' => 'Active Announcement',
            'title_ar' => 'إعلان نشط',
            'content' => 'Active content',
            'content_ar' => 'محتوى نشط',
            'is_active' => true,
        ]);

        // Create inactive announcement
        $inactiveAnnouncement = Announcement::create([
            'title' => 'Inactive Announcement',
            'title_ar' => 'إعلان غير نشط',
            'content' => 'Inactive content',
            'content_ar' => 'محتوى غير نشط',
            'is_active' => false,
        ]);

        $response = $this->get(route('admin.announcements.index'));

        $response->assertStatus(200);
        $response->assertSee('إعلان نشط');
    }
}
