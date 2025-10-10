<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class EventCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $category;

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

        // Create test category
        $this->category = Category::create([
            'name' => 'Test Category',
            'name_ar' => 'فئة تجريبية',
            'description' => 'Test category description',
            'description_ar' => 'وصف فئة تجريبية',
            'icon_name' => 'bi-calendar-event',
            'color_value' => 0x4A90E2,
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_can_view_events_index_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.events.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.events.index');
        $response->assertSee('إدارة الفعاليات');
        $response->assertSee('إضافة فعالية');
    }

    #[Test]
    public function admin_can_view_events_in_list_view()
    {
        $this->actingAs($this->admin);

        // Create test events
        $event = Event::create([
            'title' => 'Test Event',
            'title_ar' => 'فعالية تجريبية',
            'description' => 'Test event description',
            'description_ar' => 'وصف فعالية تجريبية',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'location' => 'Test Location',
            'location_ar' => 'موقع تجريبي',
            'price' => 10.00,
            'currency' => 'OMR',
            'available_tickets' => 100,
            'total_tickets' => 100,
            'is_featured' => false,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.events.index', ['view' => 'list']));

        $response->assertStatus(200);
        $response->assertSee('فعالية تجريبية');
        $response->assertSee('موقع تجريبي');
    }

    #[Test]
    public function admin_can_view_events_in_calendar_view()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.events.index', ['view' => 'calendar']));

        $response->assertStatus(200);
        $response->assertSee('calendar'); // Check for calendar div
        $response->assertViewHas('calendarEvents');
    }

    #[Test]
    public function admin_can_view_create_event_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.events.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.events.create');
        $response->assertSee('إضافة فعالية جديدة');
        $response->assertViewHas('categories');
    }

    #[Test]
    public function admin_can_create_new_event_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $eventData = [
            'title' => 'New Test Event',
            'title_ar' => 'فعالية جديدة للاختبار',
            'description' => 'This is a test event description',
            'description_ar' => 'هذا وصف فعالية تجريبية',
            'category_id' => $this->category->id,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->addHours(3)->format('Y-m-d H:i:s'),
            'location' => 'Test Venue',
            'location_ar' => 'مكان الاختبار',
            'latitude' => 23.5880,
            'longitude' => 58.3829,
            'image_url' => 'https://example.com/test-image.jpg',
            'price' => 25.00,
            'currency' => 'OMR',
            'available_tickets' => 200,
            'total_tickets' => 200,
            'organizer_name' => 'Test Organizer',
            'organizer_name_ar' => 'منظم الاختبار',
            'is_featured' => true,
            'is_active' => true,
        ];

        $response = $this->post(route('admin.events.store'), $eventData);

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'تم إضافة الفعالية بنجاح');

        $this->assertDatabaseHas('events', [
            'title' => 'New Test Event',
            'title_ar' => 'فعالية جديدة للاختبار',
            'category_id' => $this->category->id,
            'price' => 25.00,
            'is_featured' => true,
        ]);
    }

    #[Test]
    public function event_creation_fails_with_invalid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'title' => '', // Required field is empty
            'title_ar' => '',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'category_id' => 999, // Non-existent category
            'start_time' => 'invalid-date',
            'end_time' => 'invalid-date',
            'location' => '',
            'price' => -10, // Negative price
            'currency' => 'INVALID',
        ];

        $response = $this->post(route('admin.events.store'), $invalidData);

        $response->assertSessionHasErrors([
            'title',
            'title_ar',
            'category_id',
            'start_time',
            'end_time',
            'location',
            'price',
            'currency',
        ]);

        $this->assertDatabaseCount('events', 0);
    }

    #[Test]
    public function end_time_must_be_after_start_time()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'title' => 'Test Event',
            'title_ar' => 'فعالية تجريبية',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'category_id' => $this->category->id,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->format('Y-m-d H:i:s'), // Before start time
            'location' => 'Test',
            'location_ar' => 'اختبار',
            'price' => 10.00,
            'currency' => 'OMR',
        ];

        $response = $this->post(route('admin.events.store'), $invalidData);

        $response->assertSessionHasErrors('end_time');
    }

    #[Test]
    public function admin_can_view_event_details()
    {
        $this->actingAs($this->admin);

        $event = Event::create([
            'title' => 'Detail Test Event',
            'title_ar' => 'فعالية تفاصيل الاختبار',
            'description' => 'Test event description',
            'description_ar' => 'وصف فعالية تجريبية',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'location' => 'Test Location',
            'location_ar' => 'موقع تجريبي',
            'price' => 15.00,
            'currency' => 'OMR',
            'available_tickets' => 50,
            'total_tickets' => 100,
            'is_featured' => true,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.events.show', $event));

        $response->assertStatus(200);
        $response->assertViewIs('admin.events.show');
        $response->assertSee('فعالية تفاصيل الاختبار');
        $response->assertSee('موقع تجريبي');
        $response->assertSee('15');
    }

    #[Test]
    public function admin_can_view_edit_event_page()
    {
        $this->actingAs($this->admin);

        $event = Event::create([
            'title' => 'Edit Test Event',
            'title_ar' => 'فعالية تعديل الاختبار',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'location' => 'Test',
            'location_ar' => 'اختبار',
            'price' => 10.00,
            'currency' => 'OMR',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.events.edit', $event));

        $response->assertStatus(200);
        $response->assertViewIs('admin.events.edit');
        $response->assertSee('فعالية تعديل الاختبار');
        $response->assertViewHas('categories');
    }

    #[Test]
    public function admin_can_update_event_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $event = Event::create([
            'title' => 'Original Title',
            'title_ar' => 'العنوان الأصلي',
            'description' => 'Original description',
            'description_ar' => 'الوصف الأصلي',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'location' => 'Original Location',
            'location_ar' => 'الموقع الأصلي',
            'price' => 10.00,
            'currency' => 'OMR',
            'is_active' => true,
        ]);

        $updatedData = [
            'title' => 'Updated Title',
            'title_ar' => 'العنوان المحدث',
            'description' => 'Updated description',
            'description_ar' => 'الوصف المحدث',
            'category_id' => $this->category->id,
            'start_time' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(3)->addHours(4)->format('Y-m-d H:i:s'),
            'location' => 'Updated Location',
            'location_ar' => 'الموقع المحدث',
            'price' => 20.00,
            'currency' => 'OMR',
            'available_tickets' => 150,
            'total_tickets' => 150,
            'is_featured' => 1,  // Use 1 instead of true for boolean
            'is_active' => 1,
        ];

        $response = $this->put(route('admin.events.update', $event), $updatedData);

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'تم تحديث الفعالية بنجاح');
    }

    #[Test]
    public function admin_can_delete_event_without_tickets()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $event = Event::create([
            'title' => 'To Be Deleted',
            'title_ar' => 'سيتم الحذف',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'location' => 'Test',
            'location_ar' => 'اختبار',
            'price' => 10.00,
            'currency' => 'OMR',
            'is_active' => true,
        ]);

        $response = $this->delete(route('admin.events.destroy', $event));

        $response->assertRedirect(route('admin.events.index'));
        $response->assertSessionHas('success', 'تم حذف الفعالية بنجاح');
    }

    #[Test]
    public function guest_cannot_access_events_pages()
    {
        $response = $this->get(route('admin.events.index'));
        $response->assertRedirect(route('login')); // Laravel's default redirects to 'login' route

        $response = $this->get(route('admin.events.create'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function event_currency_defaults_to_omr_if_not_provided()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $eventData = [
            'title' => 'Currency Test Event',
            'title_ar' => 'فعالية اختبار العملة',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'category_id' => $this->category->id,
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'location' => 'Test',
            'location_ar' => 'اختبار',
            'price' => 10.00,
            // currency not provided
            'is_active' => true,
        ];

        $response = $this->post(route('admin.events.store'), $eventData);

        $this->assertDatabaseHas('events', [
            'title' => 'Currency Test Event',
            'currency' => 'OMR',
        ]);
    }
}
