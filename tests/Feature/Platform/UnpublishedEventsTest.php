<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\UserFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * An unpublished event (is_active = false) is a draft kept in the admin
 * panel: the public API never lists it, opens it or counts it.
 */
class UnpublishedEventsTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    #[Test]
    public function event_list_shows_published_events_only(): void
    {
        $this->makeEvent(null, ['title' => 'Published show']);
        $this->makeEvent(null, ['title' => 'Draft show', 'is_active' => false]);

        foreach (['/api/v1/events', '/api/v1/events?status=active', '/api/v1/events/search?search=show'] as $uri) {
            $this->getJson($uri)->assertOk()
                ->assertJsonFragment(['title' => 'Published show'])
                ->assertJsonMissing(['title' => 'Draft show'])
                ->assertJsonPath('pagination.total', 1);
        }

        // Asking for unpublished events returns nothing.
        $this->getJson('/api/v1/events?status=inactive')->assertOk()->assertJsonPath('pagination.total', 0);
    }

    #[Test]
    public function an_unpublished_event_cannot_be_opened(): void
    {
        $draft = $this->makeEvent(null, ['title' => 'Draft show', 'is_active' => false]);
        $published = $this->makeEvent(null, ['title' => 'Published show']);
        $date = $draft->start_time->format('Y-m-d');

        $this->getJson("/api/v1/events/{$draft->id}")->assertNotFound();
        $this->getJson("/api/v1/events/{$draft->id}/availability?date={$date}")->assertNotFound();
        $this->getJson("/api/v1/events/{$draft->id}/daily-availability")->assertNotFound();

        $this->getJson("/api/v1/events/{$published->id}")->assertOk()->assertJsonFragment(['title' => 'Published show']);
    }

    #[Test]
    public function category_counts_and_favorites_skip_unpublished_events(): void
    {
        $published = $this->makeEvent(null, ['title' => 'Published show']);
        $draft = $this->makeEvent(null, ['title' => 'Draft show', 'is_active' => false, 'category_id' => $published->category_id]);

        $this->getJson('/api/v1/categories')->assertOk()
            ->assertJsonFragment(['id' => $published->category_id, 'events_count' => 1]);

        $user = $this->makeUser();
        $this->inTenant($this->tenant, function () use ($user, $published, $draft) {
            foreach ([$published, $draft] as $event) {
                UserFavorite::create(['user_id' => $user->id, 'favoritable_id' => $event->id, 'favoritable_type' => Event::class]);
            }
        });

        $this->api('GET', '/api/v1/favorites/events', [], $user)->assertOk()
            ->assertJsonFragment(['title' => 'Published show'])
            ->assertJsonMissing(['title' => 'Draft show']);
        $this->api('POST', '/api/v1/favorites/toggle', ['type' => 'event', 'item_id' => $draft->id], $user)->assertNotFound();
    }
}
