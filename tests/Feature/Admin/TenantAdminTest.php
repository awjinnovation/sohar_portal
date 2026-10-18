<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Event;
use App\Models\PrivacyPolicy;
use App\Models\Tenant;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tenant correctness of the existing admin CRUD screens (white-label).
 */
class TenantAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@nb.test', 'password' => bcrypt('secret-pass')]);
        $this->admin->assignRole('admin'); // in north-batinah (current tenant)

        $this->other = Tenant::create(['slug' => 'b', 'name' => ['en' => 'B', 'ar' => 'B'], 'status' => 'active']);
    }

    private function inOther(callable $callback): mixed
    {
        return app(TenantContext::class)->run($this->other, $callback);
    }

    private function makeCategory(string $name = 'Cat'): Category
    {
        return Category::create([
            'name' => $name, 'name_ar' => $name, 'description' => 'd', 'description_ar' => 'd',
            'icon_name' => 'star', 'color_value' => 4283215696, 'is_active' => true,
        ]);
    }

    private function makeEvent(Category $category, string $title = 'Event'): Event
    {
        return Event::create([
            'title' => $title, 'title_ar' => $title, 'description' => 'd', 'description_ar' => 'd',
            'category_id' => $category->id,
            'start_time' => now()->addDay(), 'end_time' => now()->addDays(2),
            'location' => 'Hall', 'price' => 1, 'is_active' => true,
        ]);
    }

    private function eventPayload(int $categoryId): array
    {
        return [
            'title' => 'New Event', 'title_ar' => 'فعالية جديدة',
            'description' => 'd', 'description_ar' => 'd',
            'category_id' => $categoryId,
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'location' => 'Hall', 'price' => 2,
        ];
    }

    // (a) ---------------------------------------------------------------

    #[Test]
    public function admin_gets_404_for_event_and_category_of_another_tenant()
    {
        [$foreignCategory, $foreignEvent] = $this->inOther(function () {
            $category = $this->makeCategory('Foreign');

            return [$category, $this->makeEvent($category, 'Foreign Event')];
        });

        $this->actingAs($this->admin);

        $this->get(route('admin.events.show', $foreignEvent))->assertNotFound();
        $this->get(route('admin.events.edit', $foreignEvent))->assertNotFound();
        $this->get(route('admin.categories.edit', $foreignCategory))->assertNotFound();
        $this->delete(route('admin.categories.destroy', $foreignCategory))->assertNotFound();
        $this->assertDatabaseHas('categories', ['id' => $foreignCategory->id]);

        // Own rows still work, and lists never show the other tenant.
        $own = $this->makeEvent($this->makeCategory('Own'), 'Own Event');
        $this->get(route('admin.events.show', $own))->assertOk();
        $this->get(route('admin.events.index'))->assertOk()->assertSee('Own Event')->assertDontSee('Foreign Event');
    }

    // (b) ---------------------------------------------------------------

    #[Test]
    public function event_manager_can_open_events_but_not_users()
    {
        $manager = User::factory()->create();
        $manager->assignRole('event-manager');

        $this->actingAs($manager);

        $this->get(route('admin.events.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    // (c) ---------------------------------------------------------------

    #[Test]
    public function event_store_rejects_a_category_of_another_tenant()
    {
        $foreignCategory = $this->inOther(fn () => $this->makeCategory('Foreign'));

        $this->actingAs($this->admin);

        $this->post(route('admin.events.store'), $this->eventPayload($foreignCategory->id))
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('events', 0);

        // A category of the current tenant is accepted; tenant_id is never taken from input.
        $own = $this->makeCategory('Own');
        $this->post(route('admin.events.store'), $this->eventPayload($own->id) + ['tenant_id' => $this->other->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.events.index'));
        $this->assertDatabaseHas('events', ['title' => 'New Event', 'tenant_id' => $this->tenant->id, 'currency' => 'OMR']);
    }

    // (d) ---------------------------------------------------------------

    #[Test]
    public function login_page_is_platform_branded_and_lists_tenants()
    {
        $response = $this->get(route('admin.login.form'));

        $response->assertOk();
        $response->assertSee(config('app.name'));
        $response->assertSee('images/platform-logo.svg', false);
        $response->assertSee('name="tenant"', false);
        $response->assertSee('value="north-batinah"', false);
        $response->assertSee('value="b"', false);
        foreach (['Sohar', 'صحار', 'soharfestival', 'sohar_fastival_logo'] as $hardCoded) {
            $response->assertDontSee($hardCoded, false);
        }
    }

    #[Test]
    public function tenant_admin_can_log_in_and_lands_on_the_dashboard()
    {
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@nb.test',
            'password' => 'secret-pass',
            'tenant' => 'north-batinah',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
        $response->assertSessionHas(\App\Http\Middleware\AdminMiddleware::SESSION_KEY, $this->tenant->id);

        $this->get('/admin/dashboard')->assertOk();
    }

    #[Test]
    public function tenant_admin_cannot_log_in_to_another_tenant()
    {
        $this->post(route('admin.login'), [
            'email' => 'admin@nb.test',
            'password' => 'secret-pass',
            'tenant' => 'b',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // (e) ---------------------------------------------------------------

    #[Test]
    public function public_privacy_page_shows_the_tenant_organization()
    {
        $this->tenant->putSetting('tenant.organization.en', 'Acme Organizers LLC')
            ->putSetting('tenant.organization.ar', 'شركة أكمي للتنظيم')
            ->putSetting('tenant.support.email', 'help@acme.test')
            ->save();

        PrivacyPolicy::create([
            'title' => 'Privacy Policy', 'title_ar' => 'سياسة الخصوصية',
            'content' => '<p>We respect your privacy.</p>', 'content_ar' => '<p>نحترم خصوصيتك.</p>',
            'is_active' => true,
        ]);

        $response = $this->get('/privacy-policy?tenant=north-batinah&lang=en');

        $response->assertOk();
        $response->assertSee('Acme Organizers LLC');
        $response->assertSee('We respect your privacy.', false);
        $response->assertSee('help@acme.test');
        $response->assertSee($this->tenant->localizedName('en'));
        foreach (['Sohar', 'صحار', 'Governor', 'محافظ'] as $hardCoded) {
            $response->assertDontSee($hardCoded, false);
        }

        $this->get('/privacy-policy?tenant=north-batinah&lang=ar')
            ->assertOk()
            ->assertSee('شركة أكمي للتنظيم')
            ->assertSee('نحترم خصوصيتك.', false);

        // Another tenant without a policy: 404, never the north-batinah policy.
        $this->get('/privacy-policy?tenant=b')->assertNotFound();
    }

    #[Test]
    public function public_terms_page_is_tenant_scoped()
    {
        $this->inOther(function () {
            $this->other->putSetting('tenant.organization.en', 'B Org')->save();
            TermsAndCondition::create([
                'title' => 'B Terms', 'title_ar' => 'شروط ب', 'content' => 'B terms body', 'content_ar' => 'نص',
                'is_active' => true,
            ]);
        });

        $this->get('/terms-and-conditions?tenant=b&lang=en')->assertOk()->assertSee('B Org')->assertSee('B terms body');
        $this->get('/terms-and-conditions?tenant=north-batinah')->assertNotFound();
    }

    // Users & roles -----------------------------------------------------

    #[Test]
    public function users_index_lists_only_members_of_the_current_tenant()
    {
        $member = User::factory()->create(['name' => 'Tenant Member']);
        $member->assignRole('viewer');
        $outsider = User::factory()->create(['name' => 'Other Tenant Staff']);
        $this->inOther(fn () => $outsider->assignRole('admin'));
        $phoneOnly = User::factory()->create(['name' => 'Phone Only', 'email' => null, 'phone_number' => '96891234567']);
        $phoneOnly->assignRole('viewer');

        $this->actingAs($this->admin);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Tenant Member')
            ->assertSee('96891234567')
            ->assertDontSee('Other Tenant Staff');

        $this->get(route('admin.users.edit', $outsider))->assertNotFound();
        $this->put(route('admin.users.update', $outsider), ['name' => 'x', 'roles' => ['viewer']])->assertNotFound();
        $this->delete(route('admin.users.destroy', $outsider))->assertNotFound();

        // Super-admins may list every platform user.
        $super = User::factory()->create(['name' => 'Platform Owner']);
        $super->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($super)
            ->get(route('admin.users.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee('Other Tenant Staff');
    }

    #[Test]
    public function user_roles_are_assigned_in_the_current_tenant_only_and_super_admin_flag_is_protected()
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.users.store'), [
            'name' => 'New Staff',
            'email' => 'staff@nb.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['event-manager'],
            'is_super_admin' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $staff = User::where('email', 'staff@nb.test')->firstOrFail();
        $this->assertFalse($staff->isSuperAdmin());
        $this->assertTrue($staff->hasRole('event-manager'));
        $this->assertSame([$this->tenant->id], $staff->tenantIdsWithRoles());
        $this->assertFalse($this->inOther(fn () => $staff->fresh()->hasRole('event-manager')));

        // Updating roles in this tenant leaves the user's roles elsewhere untouched.
        $this->inOther(fn () => $staff->assignRole('viewer'));
        $this->put(route('admin.users.update', $staff), [
            'name' => 'Renamed', 'email' => 'staff@nb.test', 'roles' => ['ticket-manager'], 'is_super_admin' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $staff->refresh();
        $this->assertFalse($staff->isSuperAdmin());
        // Shared with another tenant: identity fields are not editable by a tenant admin.
        $this->assertSame('New Staff', $staff->name);
        $this->assertTrue($staff->hasRole('ticket-manager'));
        $this->assertFalse($staff->hasRole('event-manager'));
        $this->assertTrue($this->inOther(fn () => $staff->fresh()->hasRole('viewer')));

        // Unknown roles are rejected.
        $this->put(route('admin.users.update', $staff), ['roles' => ['does-not-exist']])
            ->assertSessionHasErrors('roles.0');

        // "Delete" by a tenant admin only revokes access to this tenant.
        $this->delete(route('admin.users.destroy', $staff))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertSame([$this->other->id], $staff->fresh()->tenantIdsWithRoles());
    }

    #[Test]
    public function role_definitions_can_only_be_changed_by_super_admins()
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.roles.index'))->assertOk()->assertSee('event-manager');
        $viewer = Role::where('name', 'viewer')->whereNull('team_id')->firstOrFail();
        $this->get(route('admin.roles.show', $viewer))->assertOk();

        $this->get(route('admin.roles.create'))->assertForbidden();
        $this->post(route('admin.roles.store'), ['name' => 'auditor'])->assertForbidden();
        $this->get(route('admin.roles.edit', $viewer))->assertForbidden();
        $this->put(route('admin.roles.update', $viewer), ['name' => 'hacked'])->assertForbidden();
        $this->delete(route('admin.roles.destroy', $viewer))->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'auditor']);

        $super = User::factory()->create();
        $super->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($super)
            ->post(route('admin.roles.store'), ['name' => 'auditor'])
            ->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'auditor', 'team_id' => null]);
    }

    // Settings, legal, media ---------------------------------------------

    #[Test]
    public function app_setting_keys_are_unique_per_tenant()
    {
        $this->inOther(fn () => AppSetting::create(['setting_key' => 'shared_key', 'setting_value' => 'b', 'setting_type' => 'string']));

        $this->actingAs($this->admin);

        $payload = ['setting_key' => 'shared_key', 'setting_value' => 'nb', 'setting_type' => 'string'];
        $this->post(route('admin.app-settings.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('app_settings', ['setting_key' => 'shared_key', 'tenant_id' => $this->tenant->id]);

        $this->post(route('admin.app-settings.store'), $payload)->assertSessionHasErrors('setting_key');
    }

    #[Test]
    public function legal_pages_can_be_managed_with_bilingual_fields()
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.privacy-policies.create'))->assertOk();

        $this->post(route('admin.privacy-policies.store'), [
            'title' => 'Privacy', 'title_ar' => 'الخصوصية', 'content' => 'EN body', 'content_ar' => 'نص', 'is_active' => 1,
        ])->assertRedirect(route('admin.privacy-policies.index'));
        $this->assertDatabaseHas('privacy_policies', ['title_ar' => 'الخصوصية', 'tenant_id' => $this->tenant->id]);

        $this->post(route('admin.terms-and-conditions.store'), [
            'title' => 'Terms', 'title_ar' => 'الشروط', 'content' => 'EN body', 'content_ar' => 'نص', 'is_active' => 1,
        ])->assertRedirect(route('admin.terms-and-conditions.index'));
        $terms = TermsAndCondition::firstOrFail();

        $this->get(route('admin.privacy-policies.index'))->assertOk()->assertSee('الخصوصية');
        $this->get(route('admin.terms-and-conditions.show', $terms))->assertOk()->assertSee('الشروط');
        $this->get(route('admin.terms-and-conditions.edit', $terms))->assertOk();
    }

    #[Test]
    public function notifications_can_be_created_for_tenant_users_only()
    {
        $outsider = User::factory()->create();
        $this->inOther(fn () => $outsider->assignRole('viewer'));
        $member = User::factory()->create();
        $member->assignRole('viewer');

        $this->actingAs($this->admin);
        $this->get(route('admin.notifications.create'))->assertOk();

        $payload = ['title' => 'Hi', 'title_ar' => 'مرحبا', 'body' => 'Body', 'body_ar' => 'نص'];

        $this->post(route('admin.notifications.store'), $payload + ['user_id' => $outsider->id])
            ->assertSessionHasErrors('user_id');

        $this->post(route('admin.notifications.store'), $payload + ['user_id' => $member->id])
            ->assertRedirect(route('admin.notifications.index'));
        $this->post(route('admin.notifications.store'), $payload)
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseHas('notifications', ['user_id' => $member->id, 'tenant_id' => $this->tenant->id, 'type' => 'general']);
        $this->assertDatabaseHas('notifications', ['user_id' => null, 'is_public' => true]);
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee('مرحبا');
    }

    #[Test]
    public function media_is_stored_per_tenant_and_other_tenants_files_cannot_be_deleted()
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $upload = $this->post(route('admin.media.upload'), ['file' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()
            ->json();
        $this->assertStringStartsWith('tenants/'.$this->tenant->id.'/', $upload['path']);

        Storage::disk('public')->put('tenants/'.$this->other->id.'/events/x.jpg', 'x');
        Storage::disk('public')->put('events/legacy.jpg', 'x');

        $this->deleteJson(route('admin.media.delete'), ['path' => 'tenants/'.$this->other->id.'/events/x.jpg'])->assertNotFound();
        $this->deleteJson(route('admin.media.delete'), ['path' => 'events/legacy.jpg'])->assertNotFound();
        $this->deleteJson(route('admin.media.delete'), ['path' => '../.env'])->assertNotFound();
        Storage::disk('public')->assertExists('tenants/'.$this->other->id.'/events/x.jpg');
        Storage::disk('public')->assertExists('events/legacy.jpg');

        $this->deleteJson(route('admin.media.delete'), ['path' => $upload['path']])->assertOk();
        Storage::disk('public')->assertMissing($upload['path']);
    }
}
