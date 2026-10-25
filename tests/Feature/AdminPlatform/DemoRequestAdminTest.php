<?php

namespace Tests\Feature\AdminPlatform;

use App\Models\DemoRequest;

class DemoRequestAdminTest extends AdminPlatformTestCase
{
    private function lead(array $attributes = []): DemoRequest
    {
        return DemoRequest::create(array_merge([
            'name' => 'Salim', 'organization' => 'Harbour Lights Festival', 'event_type' => 'festival',
            'expected_visitors' => '5k-25k', 'contact' => 'salim@example.om', 'contact_email' => 'salim@example.om', 'locale' => 'en',
        ], $attributes));
    }

    public function test_super_admin_can_list_search_view_and_update(): void
    {
        $admin = $this->superAdmin();
        $a = $this->lead();
        $b = $this->lead(['organization' => 'City Expo', 'contact' => '+968 9123 4567', 'contact_email' => null, 'status' => 'closed']);

        $this->actingAs($admin)->get(route('admin.demo-requests.index'))
            ->assertOk()->assertSee('Harbour Lights Festival')->assertSee('City Expo');

        $this->actingAs($admin)->get(route('admin.demo-requests.index', ['q' => 'expo']))
            ->assertOk()->assertSee('City Expo')->assertDontSee('Harbour Lights Festival');

        $this->actingAs($admin)->get(route('admin.demo-requests.index', ['status' => 'new']))
            ->assertOk()->assertSee(route('admin.demo-requests.show', $a), false)
            ->assertDontSee(route('admin.demo-requests.show', $b), false);

        $this->actingAs($admin)->get(route('admin.demo-requests.show', $a))->assertOk()->assertSee('salim@example.om');

        $this->actingAs($admin)->post(route('admin.demo-requests.status', $a), ['status' => 'contacted'])
            ->assertRedirect(route('admin.demo-requests.show', $a))->assertSessionHas('success');
        $this->assertSame('contacted', $a->fresh()->status);

        $this->actingAs($admin)->post(route('admin.demo-requests.status', $a), ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    public function test_sidebar_shows_item_with_new_count_to_super_admins_only(): void
    {
        $this->lead();
        $this->lead();

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.demo-requests.index'), false);

        $this->actingAs($this->tenantUser('admin'))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee(route('admin.demo-requests.index'), false);
    }

    public function test_tenant_admin_is_forbidden(): void
    {
        $lead = $this->lead();
        $tenantAdmin = $this->tenantUser('admin');

        $this->actingAs($tenantAdmin)->get(route('admin.demo-requests.index'))->assertForbidden();
        $this->actingAs($tenantAdmin)->get(route('admin.demo-requests.show', $lead))->assertForbidden();
        $this->actingAs($tenantAdmin)->post(route('admin.demo-requests.status', $lead), ['status' => 'closed'])->assertForbidden();
        $this->assertSame('new', $lead->fresh()->status);
    }
}
