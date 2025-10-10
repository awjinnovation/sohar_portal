<?php

namespace Tests\Feature\Admin;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class RestaurantCrudTest extends TestCase
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
    public function admin_can_view_restaurants_index_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.restaurants.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.restaurants.index');
        $response->assertSee('إدارة المطاعم');
        $response->assertSee('إضافة مطعم');
    }

    #[Test]
    public function admin_can_view_create_restaurant_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.restaurants.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.restaurants.create');
        $response->assertSee('إضافة مطعم جديد');
    }

    #[Test]
    public function admin_can_create_new_restaurant_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => 'Test Restaurant',
            'name_ar' => 'مطعم تجريبي',
            'description' => 'This is a test restaurant description',
            'description_ar' => 'هذا وصف مطعم تجريبي',
            'cuisine' => 'Italian',
            'cuisine_ar' => 'إيطالي',
            'location' => 'Muscat, Oman',
            'location_ar' => 'مسقط، عمان',
            'latitude' => 23.5880,
            'longitude' => 58.3829,
            'phone' => '+968 1234 5678',
            'website' => 'https://example.com',
            'image_url' => 'https://example.com/test-image.jpg',
            'price_range' => '$$',
            'rating' => 4.5,
            'total_ratings' => 100,
            'is_open' => 1,
            'is_featured' => 1,
            'is_active' => 1,
            'has_delivery' => 1,
            'has_parking' => 1,
            'has_wifi' => 1,
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertRedirect(route('admin.restaurants.index'));
        $response->assertSessionHas('success', 'تم إضافة المطعم بنجاح');

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Test Restaurant',
            'name_ar' => 'مطعم تجريبي',
            'cuisine' => 'Italian',
            'phone' => '+968 1234 5678',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function restaurant_creation_fails_with_invalid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'name' => '',
            'name_ar' => '',
            'description' => '',
            'description_ar' => '',
            'latitude' => 'invalid-number',
            'longitude' => 'invalid-number',
            'rating' => 10, // Rating should be between 0 and 5
        ];

        $response = $this->post(route('admin.restaurants.store'), $invalidData);

        $response->assertSessionHasErrors([
            'name',
            'name_ar',
            'description',
            'description_ar',
        ]);

        $this->assertDatabaseCount('restaurants', 0);
    }

    #[Test]
    public function admin_can_view_restaurant_details()
    {
        $this->actingAs($this->admin);

        $restaurant = Restaurant::create([
            'name' => 'Detail Test Restaurant',
            'name_ar' => 'مطعم تفاصيل الاختبار',
            'description' => 'Test restaurant description',
            'description_ar' => 'وصف مطعم تجريبي',
            'cuisine' => 'Arabic',
            'cuisine_ar' => 'عربي',
            'location' => 'Sohar, Oman',
            'location_ar' => 'صحار، عمان',
            'latitude' => 24.3589,
            'longitude' => 56.7085,
            'phone' => '+968 2345 6789',
            'price_range' => '$$$',
            'rating' => 4.8,
            'total_ratings' => 250,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.restaurants.show', $restaurant));

        $response->assertStatus(200);
        $response->assertViewIs('admin.restaurants.show');
        $response->assertSee('مطعم تفاصيل الاختبار');
        $response->assertSee('صحار، عمان');
        $response->assertSee('4.8');
    }

    #[Test]
    public function admin_can_view_edit_restaurant_page()
    {
        $this->actingAs($this->admin);

        $restaurant = Restaurant::create([
            'name' => 'Edit Test Restaurant',
            'name_ar' => 'مطعم تعديل الاختبار',
            'description' => 'Test description',
            'description_ar' => 'وصف تجريبي',
            'cuisine' => 'Asian',
            'cuisine_ar' => 'آسيوي',
            'location' => 'Muscat',
            'location_ar' => 'مسقط',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.restaurants.edit', $restaurant));

        $response->assertStatus(200);
        $response->assertViewIs('admin.restaurants.edit');
        $response->assertSee('مطعم تعديل الاختبار');
        $response->assertSee('تعديل المطعم');
    }

    #[Test]
    public function admin_can_update_restaurant_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurant = Restaurant::create([
            'name' => 'Original Name',
            'name_ar' => 'الاسم الأصلي',
            'description' => 'Original description',
            'description_ar' => 'الوصف الأصلي',
            'cuisine' => 'Original Cuisine',
            'cuisine_ar' => 'المطبخ الأصلي',
            'location' => 'Original Location',
            'location_ar' => 'الموقع الأصلي',
            'phone' => '+968 1111 1111',
            'is_active' => true,
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'name_ar' => 'الاسم المحدث',
            'description' => 'Updated description',
            'description_ar' => 'الوصف المحدث',
            'cuisine' => 'Updated Cuisine',
            'cuisine_ar' => 'المطبخ المحدث',
            'location' => 'Updated Location',
            'location_ar' => 'الموقع المحدث',
            'latitude' => 23.6100,
            'longitude' => 58.5400,
            'phone' => '+968 2222 2222',
            'website' => 'https://updated-example.com',
            'price_range' => '$$$$',
            'rating' => 4.9,
            'total_ratings' => 500,
            'is_open' => 1,
            'is_featured' => 1,
            'is_active' => 1,
        ];

        $response = $this->put(route('admin.restaurants.update', $restaurant), $updatedData);

        $response->assertRedirect(route('admin.restaurants.index'));
        $response->assertSessionHas('success', 'تم تحديث المطعم بنجاح');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'name' => 'Updated Name',
            'name_ar' => 'الاسم المحدث',
            'phone' => '+968 2222 2222',
        ]);
    }

    #[Test]
    public function admin_can_delete_restaurant()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurant = Restaurant::create([
            'name' => 'To Be Deleted',
            'name_ar' => 'سيتم الحذف',
            'description' => 'Test description',
            'description_ar' => 'وصف تجريبي',
            'cuisine' => 'Test',
            'cuisine_ar' => 'اختبار',
            'location' => 'Test',
            'location_ar' => 'اختبار',
            'is_active' => true,
        ]);

        $response = $this->delete(route('admin.restaurants.destroy', $restaurant));

        $response->assertRedirect(route('admin.restaurants.index'));
        $response->assertSessionHas('success', 'تم حذف المطعم بنجاح');

        $this->assertDatabaseMissing('restaurants', [
            'id' => $restaurant->id,
        ]);
    }

    #[Test]
    public function guest_cannot_access_restaurants_pages()
    {
        $response = $this->get(route('admin.restaurants.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.restaurants.create'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function restaurant_name_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => '',
            'name_ar' => 'مطعم تجريبي',
            'description' => 'Test',
            'description_ar' => 'اختبار',
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertSessionHasErrors('name');
    }

    #[Test]
    public function restaurant_name_ar_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => 'Test Restaurant',
            'name_ar' => '',
            'description' => 'Test',
            'description_ar' => 'اختبار',
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertSessionHasErrors('name_ar');
    }

    #[Test]
    public function restaurant_description_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => 'Test Restaurant',
            'name_ar' => 'مطعم تجريبي',
            'description' => '',
            'description_ar' => 'اختبار',
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertSessionHasErrors('description');
    }

    #[Test]
    public function restaurant_description_ar_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => 'Test Restaurant',
            'name_ar' => 'مطعم تجريبي',
            'description' => 'Test description',
            'description_ar' => '',
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertSessionHasErrors('description_ar');
    }

    #[Test]
    public function restaurant_can_have_nullable_optional_fields()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $restaurantData = [
            'name' => 'Minimal Restaurant',
            'name_ar' => 'مطعم بسيط',
            'description' => 'Minimal description',
            'description_ar' => 'وصف بسيط',
            'is_active' => 1,
        ];

        $response = $this->post(route('admin.restaurants.store'), $restaurantData);

        $response->assertRedirect(route('admin.restaurants.index'));

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Minimal Restaurant',
            'name_ar' => 'مطعم بسيط',
        ]);
    }
}
