<?php

namespace Tests\Feature\Platform;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Notification;
use App\Support\PublicApiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Shared public API response cache (docs/caching.md). */
class PublicApiCacheTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function get_(string $uri, array $headers = [], $tenant = null)
    {
        return $this->getJson($uri, $this->tenantHeaders($tenant, $headers));
    }

    #[Test]
    public function public_responses_are_shared_and_revalidated_with_etags(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Night Concert']);

        $first = $this->get_("/api/v1/events/{$event->id}")->assertOk()->assertHeader('X-Cache', 'MISS');
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Another visitor, signed in: same shared entry.
        $this->actingAs($this->makeUser(), 'sanctum');
        $second = $this->get_("/api/v1/events/{$event->id}")->assertOk()->assertHeader('X-Cache', 'HIT')->assertHeader('ETag', $etag);
        $this->assertSame($first->getContent(), $second->getContent());

        $this->get_("/api/v1/events/{$event->id}", ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertStringContainsString('X-Tenant', (string) $second->headers->get('Vary'));
    }

    #[Test]
    public function an_admin_change_is_visible_on_the_next_request(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Old title']);
        $this->get_("/api/v1/events/{$event->id}")->assertSee('Old title');
        $this->get_("/api/v1/events/{$event->id}")->assertHeader('X-Cache', 'HIT');

        $this->inTenant($this->tenant, fn () => $event->update(['title' => 'New title']));

        $this->get_("/api/v1/events/{$event->id}")->assertHeader('X-Cache', 'MISS')->assertSee('New title');
    }

    #[Test]
    public function a_change_inside_a_transaction_invalidates_after_commit(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Before']);
        $this->get_("/api/v1/events/{$event->id}");

        DB::transaction(function () use ($event) {
            $this->inTenant($this->tenant, fn () => $event->update(['title' => 'After']));
        });

        $this->get_("/api/v1/events/{$event->id}")->assertSee('After');
    }

    #[Test]
    public function entries_are_per_tenant_and_per_language(): void
    {
        $other = $this->makeTenant('other-org', ['tenant' => ['locales' => ['ar', 'en'], 'default_locale' => 'en']]);
        $this->makeEvent(null, ['title' => 'North event', 'title_ar' => 'فعالية الشمال']);
        $this->makeEvent($other, ['title' => 'Other event', 'title_ar' => 'فعالية أخرى']);

        $this->get_('/api/v1/events?lang=en')->assertSee('North event')->assertDontSee('Other event');
        $this->get_('/api/v1/events?lang=en', [], $other)->assertHeader('X-Cache', 'MISS')->assertSee('Other event')->assertDontSee('North event');
        $this->get_('/api/v1/events?lang=ar')->assertHeader('X-Cache', 'MISS');

        // Only the edited tenant is invalidated.
        $this->get_('/api/v1/events?lang=en', [], $other)->assertHeader('X-Cache', 'HIT');
        $this->inTenant($this->tenant, fn () => Event::first()->update(['title' => 'North renamed']));
        $this->get_('/api/v1/events?lang=en', [], $other)->assertHeader('X-Cache', 'HIT');
        $this->get_('/api/v1/events?lang=en')->assertHeader('X-Cache', 'MISS')->assertSee('North renamed');
    }

    #[Test]
    public function query_order_does_not_matter_and_errors_are_not_cached(): void
    {
        $this->makeEvent();
        $this->get_('/api/v1/events?page=1&per_page=5')->assertHeader('X-Cache', 'MISS');
        $this->get_('/api/v1/events?per_page=5&page=1')->assertHeader('X-Cache', 'HIT');

        $this->get_('/api/v1/events/999999')->assertNotFound()->assertHeader('X-Cache', 'BYPASS');
        $this->get_('/api/v1/events/999999')->assertNotFound()->assertHeader('X-Cache', 'BYPASS');
    }

    #[Test]
    public function user_dependent_endpoints_are_not_cached(): void
    {
        $event = $this->makeEvent();
        $this->actingAs($this->makeUser(), 'sanctum');
        $query = http_build_query(['event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1]);

        $this->get_("/api/v1/payments/quote?$query")->assertOk()->assertHeaderMissing('X-Cache');
        $this->get_('/api/v1/wallet')->assertOk()->assertHeaderMissing('X-Cache');
    }

    #[Test]
    public function personal_notifications_do_not_invalidate_but_public_ones_do(): void
    {
        $this->get_('/api/v1/announcements')->assertHeader('X-Cache', 'MISS');
        $user = $this->makeUser();

        $this->inTenant($this->tenant, fn () => Notification::create(['user_id' => $user->id, 'title' => 'Your ticket', 'title_ar' => 'تذكرتك', 'body' => 'b', 'body_ar' => 'b', 'type' => 'ticket']));
        $this->get_('/api/v1/announcements')->assertHeader('X-Cache', 'HIT');

        $this->inTenant($this->tenant, fn () => Notification::create(['title' => 'Gates open', 'title_ar' => 'فُتحت البوابات', 'body' => 'b', 'body_ar' => 'b', 'type' => 'info', 'is_public' => true]));
        $this->get_('/api/v1/announcements')->assertHeader('X-Cache', 'MISS');
    }

    #[Test]
    public function a_new_announcement_shows_at_once(): void
    {
        $this->get_('/api/v1/announcements/active')->assertHeader('X-Cache', 'MISS');
        $admin = $this->makeUser();
        $this->inTenant($this->tenant, fn () => Announcement::create([
            'title' => 'Parking full', 'title_ar' => 'المواقف ممتلئة', 'content' => 'c', 'content_ar' => 'c',
            'type' => 'warning', 'priority' => 1, 'is_active' => true, 'created_by' => $admin->id,
            'start_datetime' => now()->subHour(), 'end_datetime' => now()->addHour(),
        ]));
        $this->get_('/api/v1/announcements/active')->assertHeader('X-Cache', 'MISS')->assertSee('Parking full');
    }

    #[Test]
    public function concurrent_misses_wait_for_the_first_instead_of_hitting_the_database(): void
    {
        config(['api_cache.lock_wait_seconds' => 0]);
        $this->makeEvent();
        $this->get_('/api/v1/categories'); // sets the request language as the API resolves it
        $key = PublicApiCache::key($this->tenant->id, Request::create('/api/v1/events', 'GET'));

        // Another request is computing this entry: this one does not wait (0 s) and answers uncached.
        $lock = Cache::lock($key.':lock', 15);
        $this->assertTrue($lock->get());
        $this->get_('/api/v1/events')->assertOk()->assertHeader('X-Cache', 'BYPASS');
        $lock->release();

        $this->get_('/api/v1/events')->assertHeader('X-Cache', 'MISS');
        $this->get_('/api/v1/events')->assertHeader('X-Cache', 'HIT');
    }

    #[Test]
    public function a_cached_response_needs_no_database_query(): void
    {
        $this->makeEvent();
        $this->get_('/api/v1/events')->assertHeader('X-Cache', 'MISS');

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->get_('/api/v1/events')->assertOk()->assertHeader('X-Cache', 'HIT');
        $this->assertSame(0, $queries, 'tenant lookup and response both come from the cache');
    }

    #[Test]
    public function a_deactivated_tenant_is_refused_on_the_next_request(): void
    {
        $this->get_('/api/v1/categories')->assertOk();

        $this->tenant->update(['status' => 'suspended']);

        $this->get_('/api/v1/categories')->assertNotFound()->assertJsonPath('code', 'TENANT_NOT_FOUND');
    }

    #[Test]
    public function the_cache_can_be_switched_off(): void
    {
        config(['api_cache.enabled' => false]);
        $this->makeEvent();
        $this->get_('/api/v1/events')->assertOk()->assertHeaderMissing('X-Cache');
    }

    #[Test]
    public function token_last_used_at_is_written_at_most_every_five_minutes(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('app')->plainTextToken;
        $headers = $this->tenantHeaders(null, ['Authorization' => "Bearer $token"]);

        $this->getJson('/api/v1/wallet', $headers)->assertOk();
        $first = $user->tokens()->first()->last_used_at;
        $this->assertNotNull($first);

        $this->travel(2)->minutes();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/wallet', $headers)->assertOk();
        $this->assertEquals($first, $user->tokens()->first()->last_used_at);

        $this->travel(4)->minutes();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/wallet', $headers)->assertOk();
        $this->assertTrue($user->tokens()->first()->last_used_at->gt($first));
    }
}
