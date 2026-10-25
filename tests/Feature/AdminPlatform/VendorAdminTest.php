<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VendorAdminTest extends AdminPlatformTestCase
{
    public function test_create_vendor_and_manage_staff(): void
    {
        Storage::fake('public');
        $admin = $this->tenantUser();

        $response = $this->actingAs($admin)->post(route('admin.vendors.store'), [
            'name_ar' => 'كشك القهوة',
            'name_en' => 'Coffee Stall',
            'description_ar' => 'قهوة عمانية',
            'category' => 'beverages',
            'is_active' => '1',
            'logo' => UploadedFile::fake()->image('v.png'),
        ]);

        $vendor = Vendor::firstOrFail();
        $response->assertRedirect(route('admin.vendors.show', $vendor));
        $this->assertSameJson(['en' => 'Coffee Stall', 'ar' => 'كشك القهوة'], $vendor->name);
        $this->assertSame($this->tenant->id, $vendor->tenant_id);
        $this->assertTrue($vendor->is_active);
        $this->assertStringStartsWith('tenants/north-batinah/vendors/', $vendor->logo_path);
        Storage::disk('public')->assertExists($vendor->logo_path);

        // Attach by phone and by email.
        $byPhone = User::factory()->create(['phone_number' => '96899998888']);
        $byEmail = User::factory()->create(['email' => 'cashier@example.test']);

        $this->actingAs($admin)->post(route('admin.vendors.staff.attach', $vendor), ['identifier' => '+968 9999 8888', 'role' => 'manager'])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.vendors.staff.attach', $vendor), ['identifier' => 'cashier@example.test', 'role' => 'staff'])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.vendors.staff.attach', $vendor), ['identifier' => 'nobody@example.test', 'role' => 'staff'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('vendor_user', ['vendor_id' => $vendor->id, 'user_id' => $byPhone->id, 'role' => 'manager']);
        $this->assertDatabaseHas('vendor_user', ['vendor_id' => $vendor->id, 'user_id' => $byEmail->id, 'role' => 'staff']);
        $this->assertTrue($byPhone->fresh()->hasRole('vendor-staff'));
        $this->assertTrue($byEmail->fresh()->hasRole('vendor-staff'));

        $this->actingAs($admin)->get(route('admin.vendors.show', $vendor))->assertOk()->assertSee('كشك القهوة')->assertSee('cashier@example.test');

        $this->actingAs($admin)->delete(route('admin.vendors.staff.detach', [$vendor, $byEmail]))->assertSessionHas('success');
        $this->assertDatabaseMissing('vendor_user', ['vendor_id' => $vendor->id, 'user_id' => $byEmail->id]);
        $this->assertFalse($byEmail->fresh()->hasRole('vendor-staff'));
    }

    public function test_update_and_destroy_vendor(): void
    {
        $admin = $this->tenantUser();
        $vendor = Vendor::create(['name' => ['en' => 'Old', 'ar' => 'قديم'], 'is_active' => true]);
        $restaurant = Restaurant::create($this->restaurantAttributes(['name' => 'Grill', 'name_ar' => 'مشويات']));

        $this->actingAs($admin)->put(route('admin.vendors.update', $vendor), [
            'name_ar' => 'جديد', 'name_en' => 'New', 'restaurant_id' => $restaurant->id, 'is_active' => '0',
        ])->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('New', $vendor->name['en']);
        $this->assertSame($restaurant->id, $vendor->restaurant_id);
        $this->assertFalse($vendor->is_active);

        // Restaurants of another tenant cannot be linked.
        $b = $this->otherTenant();
        $foreign = app(TenantContext::class)->run($b, fn () => Restaurant::create($this->restaurantAttributes(['name' => 'X'])));
        $this->actingAs($admin)->put(route('admin.vendors.update', $vendor), [
            'name_ar' => 'جديد', 'name_en' => 'New', 'restaurant_id' => $foreign->id,
        ])->assertSessionHasErrors('restaurant_id');

        $this->actingAs($admin)->delete(route('admin.vendors.destroy', $vendor))->assertRedirect(route('admin.vendors.index'));
        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }

    private function restaurantAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'R', 'name_ar' => 'ر', 'description' => 'd', 'description_ar' => 'د',
            'cuisine' => 'omani', 'cuisine_ar' => 'عماني', 'location' => 'Park', 'location_ar' => 'الحديقة',
            'latitude' => 24.3, 'longitude' => 56.7, 'price_range' => '$$',
        ], $overrides);
    }
}
