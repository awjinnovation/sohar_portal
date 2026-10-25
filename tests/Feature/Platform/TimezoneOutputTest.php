<?php

namespace Tests\Feature\Platform;

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * API datetimes are ISO 8601 with the tenant offset (Asia/Muscat = +04:00);
 * event / announcement times typed by organizers are tenant wall-clock.
 */
class TimezoneOutputTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    #[Test]
    public function event_times_typed_in_the_admin_come_back_with_the_tenant_offset(): void
    {
        $event = $this->makeEvent(null, ['start_time' => '2026-11-01 18:00', 'end_time' => '2026-11-01 22:30']);
        $this->assertSame('2026-11-01 18:00:00', $event->getRawOriginal('start_time'));

        $res = $this->api('GET', "/api/v1/events/{$event->id}")->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-01T18:00:00+04:00')
            ->assertJsonPath('data.end_date', '2026-11-01T22:30:00+04:00');
        $this->assertStringEndsWith('+04:00', $res->json('data.created_at'));

        // Same instant whatever the client parses it as (18:00 Muscat = 14:00 UTC).
        $this->assertSame('2026-11-01 14:00', \Carbon\Carbon::parse($res->json('data.start_date'))->utc()->format('Y-m-d H:i'));

        // Carbon input (UTC) is converted to tenant wall-clock.
        $event->update(['start_time' => \Carbon\Carbon::parse('2026-11-02 10:00', 'UTC')]);
        $this->assertSame('2026-11-02 14:00:00', $event->fresh()->getRawOriginal('start_time'));
    }

    #[Test]
    public function the_admin_calendar_feed_carries_the_offset(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser();
        $this->grantRole($admin, 'admin');
        $this->makeEvent(null, ['start_time' => '2026-11-01 18:00', 'end_time' => '2026-11-01 22:00']);

        $this->actingAs($admin)->get(route('admin.events.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('2026-11-01T18:00:00+04:00', false);
    }

    #[Test]
    public function instants_and_models_serialized_directly_use_the_tenant_zone(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 5000);
        $tx = $this->api('GET', '/api/v1/wallet/transactions', [], $user)->assertOk()->json('data.0');
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+04:00$/', $tx['created_at']);

        Announcement::create([
            'title' => 'Hi', 'title_ar' => 'مرحبا', 'content' => 'c', 'content_ar' => 'c', 'type' => 'info',
            'priority' => 1, 'is_active' => true, 'start_datetime' => now()->subHour(), 'end_datetime' => now()->addDay(), 'created_by' => $user->id,
        ]);
        $row = $this->api('GET', '/api/v1/announcements/active')->assertOk()->json('data.0');
        $this->assertStringEndsWith('+04:00', $row['start_datetime']);
        $this->assertStringEndsWith('+04:00', $row['created_at']);
    }

    #[Test]
    public function upcoming_compares_in_tenant_local_time(): void
    {
        // Starts in 2 hours local time — must count as upcoming even though
        // the stored wall-clock value is ahead of UTC.
        $soon = now('Asia/Muscat')->addHours(2);
        $past = now('Asia/Muscat')->subHours(2);
        $this->makeEvent(null, ['title' => 'Soon', 'start_time' => $soon, 'end_time' => $soon->copy()->addHour()]);
        $this->makeEvent(null, ['title' => 'Past', 'start_time' => $past, 'end_time' => $past->copy()->addHour()]);

        $titles = collect($this->api('GET', '/api/v1/events/upcoming')->assertOk()->json('data'))->pluck('title');
        $this->assertContains('Soon', $titles);
        $this->assertNotContains('Past', $titles);
        $this->assertSame(2, Event::count());
    }
}
