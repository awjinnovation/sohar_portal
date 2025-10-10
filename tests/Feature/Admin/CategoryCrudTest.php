<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;

class CategoryCrudTest extends TestCase
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
    public function admin_can_view_categories_index_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.categories.index'));

        $response->assertStatus(200);
        $response->assertSee('إدارة الفئات');
    }

    #[Test]
    public function admin_can_view_create_category_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.categories.create'));

        $response->assertStatus(200);
        $response->assertSee('إضافة فئة جديدة');
    }

    #[Test]
    public function admin_can_create_new_category_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $categoryData = [
            'name' => 'Test Category',
            'name_ar' => 'فئة تجريبية',
            'description' => 'Test category description',
            'description_ar' => 'وصف فئة تجريبية',
            'icon_name' => 'bi-star',
            'color_value' => 0xFF5733,
            'image_url' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30',
            'display_order' => 1,
            'is_active' => 1,
        ];

        $response = $this->post(route('admin.categories.store'), $categoryData);

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'name_ar' => 'فئة تجريبية',
            'icon_name' => 'bi-star',
        ]);
    }

    #[Test]
    public function category_creation_fails_with_invalid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $invalidData = [
            'name' => '',
            'name_ar' => '',
            'description' => '',
            'icon_name' => '',
        ];

        $response = $this->post(route('admin.categories.store'), $invalidData);

        $response->assertSessionHasErrors(['name', 'name_ar', 'description', 'icon_name']);
        $this->assertDatabaseCount('categories', 0);
    }

    #[Test]
    public function admin_can_view_category_details()
    {
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'Music',
            'name_ar' => 'موسيقى',
            'description' => 'Music events',
            'description_ar' => 'فعاليات موسيقية',
            'icon_name' => 'bi-music-note',
            'color_value' => 0x4A90E2,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.categories.show', $category));

        $response->assertStatus(200);
        $response->assertSee('Music');
        $response->assertSee('موسيقى');
    }

    #[Test]
    public function admin_can_view_edit_category_page()
    {
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'Sports',
            'name_ar' => 'رياضة',
            'description' => 'Sports events',
            'description_ar' => 'فعاليات رياضية',
            'icon_name' => 'bi-trophy',
            'color_value' => 0xFFA726,
            'display_order' => 2,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.categories.edit', $category));

        $response->assertStatus(200);
        $response->assertSee('تعديل الفئة');
        $response->assertSee('Sports');
    }

    #[Test]
    public function admin_can_update_category_with_valid_data()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'Original Name',
            'name_ar' => 'الاسم الأصلي',
            'description' => 'Original description',
            'description_ar' => 'الوصف الأصلي',
            'icon_name' => 'bi-calendar',
            'color_value' => 0x000000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'name_ar' => 'الاسم المحدث',
            'description' => 'Updated description',
            'description_ar' => 'الوصف المحدث',
            'icon_name' => 'bi-star',
            'color_value' => 0xFFFFFF,
            'display_order' => 5,
            'is_active' => 1,
        ];

        $response = $this->put(route('admin.categories.update', $category), $updatedData);

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('success');
    }

    #[Test]
    public function admin_can_delete_category_without_events()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'To Be Deleted',
            'name_ar' => 'سيتم الحذف',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'icon_name' => 'bi-trash',
            'color_value' => 0xFF0000,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('success');
    }

    #[Test]
    public function guest_cannot_access_categories_pages()
    {
        $response = $this->get(route('admin.categories.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.categories.create'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function category_icon_name_is_required()
    {
        $this->withoutMiddleware();
        $this->actingAs($this->admin);

        $categoryData = [
            'name' => 'Test',
            'name_ar' => 'اختبار',
            'description' => 'Test',
            'description_ar' => 'اختبار',
            'icon_name' => '', // Missing required field
            'color_value' => 0xFF5733,
        ];

        $response = $this->post(route('admin.categories.store'), $categoryData);

        $response->assertSessionHasErrors('icon_name');
    }
}
