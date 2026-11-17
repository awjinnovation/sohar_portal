<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * Event lists put the organizer's main events first: featured events (marked
 * once in the admin) lead, then every event by start time. Nothing has to be
 * set again for the order to hold.
 */
class EventDisplayOrderTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    #[Test]
    public function featured_events_lead_every_event_list(): void
    {
        $early = $this->makeEvent(null, ['title' => 'Early show', 'start_time' => now()->subDays(3)->setTime(9, 0)]);
        $same = ['category_id' => $early->category_id];
        $this->makeEvent(null, $same + ['title' => 'Main festival', 'is_featured' => true, 'start_time' => now()->subDay()->setTime(17, 0)]);
        $this->makeEvent(null, $same + ['title' => 'Late show', 'start_time' => now()->subDays(2)->setTime(9, 0)]);

        foreach (['/api/v1/events', '/api/v1/events/today', "/api/v1/events/category/{$early->category_id}"] as $uri) {
            $titles = collect($this->getJson($uri)->assertOk()->json('data'))->pluck('title')->all();
            $this->assertSame(['Main festival', 'Early show', 'Late show'], $titles, $uri);
        }
    }
}
