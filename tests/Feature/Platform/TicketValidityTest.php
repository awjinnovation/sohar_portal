<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Ticket;
use App\Services\Gate\GateDay;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/** Validity per ticket type: date | any_day | all_days (docs/ticket_types_contract.md §7). */
class TicketValidityTest extends GateTestCase
{
    private function addType(Event $event, array $attrs): EventTicketType
    {
        return $this->inTenant($this->tenant, fn () => EventTicketType::create(array_merge([
            'event_id' => $event->id, 'name' => ['en' => Str::headline($attrs['key']), 'ar' => 'نوع '.$attrs['key']], 'category' => 'other',
            'price_minor' => 1000, 'admits' => 1, 'per_order_max' => 10, 'visibility' => 'public', 'is_active' => true, 'sort' => 5,
        ], $attrs)));
    }

    private function general(Event $event): EventTicketType
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $event->id)->where('key', 'general')->firstOrFail();
    }

    private function quote(Event $event, array $params)
    {
        return $this->getJson('/api/v1/payments/quote?'.http_build_query(['event_id' => $event->id] + $params), $this->tenantHeaders(null, ['Accept-Language' => 'en']));
    }

    private function buy(Event $event, array $items, ?string $date = null, ?\App\Models\User $user = null)
    {
        $user ??= $this->makeUser();
        $this->fund($user, 100000);

        return $this->api('POST', '/api/v1/payments/initialize', array_filter([
            'event_id' => $event->id, 'booking_date' => $date, 'method' => 'wallet', 'idempotency_key' => (string) Str::uuid(), 'items' => $items,
        ], fn ($v) => $v !== null), $user);
    }

    #[Test]
    public function undated_types_are_quoted_and_bought_without_a_booking_date(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000']);
        $any = $this->addType($event, ['key' => 'any', 'validity' => EventTicketType::VALIDITY_ANY_DAY, 'price_minor' => 1500]);

        // Type objects carry the validity; an undated type's `available` needs no date.
        $types = collect($this->getJson("/api/v1/events/{$event->id}", $this->tenantHeaders())->assertOk()->json('data.ticket_types'))->keyBy('key');
        $this->assertSame(['date', 'any_day'], [$types['general']['validity'], $types['any']['validity']]);

        $this->quote($event, ['items' => [['ticket_type_id' => $any->id, 'quantity' => 2]]])->assertOk()
            ->assertJsonPath('data.booking_date', null)
            ->assertJsonPath('data.total_minor', 3000)
            ->assertJsonPath('data.lines.0.validity', 'any_day');
        // A dated type still needs the date (same validation envelope as before).
        $this->quote($event, ['items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('booking_date');
        $this->quote($event, ['quantity' => 1])->assertStatus(422)->assertJsonValidationErrors('booking_date');

        $res = $this->buy($event, [['ticket_type_id' => $any->id, 'quantity' => 1]])->assertCreated()
            ->assertJsonPath('status', 'completed')->assertJsonPath('lines.0.validity', 'any_day');
        $ticket = $res->json('data.tickets.0');
        $this->assertSame([null, 'any_day', $event->start_time->toDateString(), $event->end_time->toDateString(), 'One visit, any day'],
            [$ticket['booking_date'], $ticket['validity'], $ticket['valid_from'], $ticket['valid_until'], $ticket['validity_text']]);
        $this->assertSame('any_day', $ticket['ticket_type']['validity']);
        $model = Ticket::findOrFail($ticket['id']);
        $this->assertNull($model->booking_date);
        $this->assertSame('any_day', $model->validity);

        // Later edits of the type never change issued tickets.
        $any->update(['validity' => EventTicketType::VALIDITY_DATE]);
        $this->assertSame('any_day', $model->fresh()->validity);
        $mine = $this->api('GET', '/api/v1/tickets/my-tickets', [], $model->user)->assertOk();
        $this->assertSame(['any_day', null], [$mine->json('data.0.validity'), $mine->json('data.0.booking_date')]);
    }

    #[Test]
    public function mixed_orders_date_only_the_dated_lines_and_undated_lines_skip_the_daily_capacity(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000', 'total_tickets' => 2]);
        $pass = $this->addType($event, ['key' => 'pass', 'validity' => EventTicketType::VALIDITY_ALL_DAYS, 'price_minor' => 5000, 'admits' => 2]);
        $date = now()->addDay()->toDateString();

        $res = $this->buy($event, [['ticket_type_id' => $this->general($event)->id, 'quantity' => 2], ['ticket_type_id' => $pass->id, 'quantity' => 3]], $date)
            ->assertCreated()->assertJsonCount(5, 'data.tickets');
        $tickets = collect($res->json('data.tickets'))->groupBy('validity');
        $this->assertSame([$date, $date], $tickets['date']->pluck('booking_date')->all());
        $this->assertSame([null, null, null], $tickets['all_days']->pluck('booking_date')->all());
        $this->assertSame(2, $tickets['all_days'][0]['admits']);

        // The day is full for dated tickets; passes are still on sale (no type capacity = unlimited).
        $avail = $this->getJson("/api/v1/events/{$event->id}/availability?date={$date}", $this->tenantHeaders())->assertOk();
        $this->assertSame([0, null], array_column($avail->json('data.ticket_types'), 'available'));
        $this->quote($event, ['booking_date' => $date, 'items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.ticket_type_id', null);
        $this->quote($event, ['booking_date' => $date, 'items' => [['ticket_type_id' => $pass->id, 'quantity' => 4]]])->assertOk()
            ->assertJsonPath('data.booking_date', null);
    }

    #[Test]
    public function an_undated_type_capacity_is_a_total_and_ended_events_are_refused(): void
    {
        $event = $this->makeEvent(null, ['total_tickets' => 1]);
        $any = $this->addType($event, ['key' => 'any', 'validity' => EventTicketType::VALIDITY_ANY_DAY, 'capacity' => 3]);

        $this->buy($event, [['ticket_type_id' => $any->id, 'quantity' => 2]])->assertCreated();
        $this->quote($event, ['items' => [['ticket_type_id' => $any->id, 'quantity' => 2]]])->assertStatus(422)
            ->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.ticket_type_id', $any->id)->assertJsonPath('data.available', 1)
            ->assertJsonPath('message', 'No Any tickets are left.');
        // Whatever date is sent, the total counts.
        $this->quote($event, ['booking_date' => now()->addDays(3)->toDateString(), 'items' => [['ticket_type_id' => $any->id, 'quantity' => 2]]])
            ->assertStatus(422)->assertJsonPath('data.available', 1);
        $this->buy($event, [['ticket_type_id' => $any->id, 'quantity' => 1]])->assertCreated();
        $this->assertSame(0, $any->fresh()->availableTotal());

        $ended = $this->makeEvent(null, ['title' => 'Past', 'start_time' => now()->subDays(5)->setTime(9, 0), 'end_time' => now()->subDay()->setTime(22, 0)]);
        $old = $this->addType($ended, ['key' => 'any', 'validity' => EventTicketType::VALIDITY_ANY_DAY]);
        $this->quote($ended, ['items' => [['ticket_type_id' => $old->id, 'quantity' => 1]]])->assertStatus(422)
            ->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.ticket_type_id', null)->assertJsonPath('data.available', 0);
    }

    #[Test]
    public function an_any_day_ticket_admits_once_on_any_running_day(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice();
        $ticket = $this->makeTicket($event, ['booking_date' => null, 'validity' => EventTicketType::VALIDITY_ANY_DAY]);

        $this->scan($token, $ticket->qr_code)->assertOk()
            ->assertJsonPath('data.result', 'valid')
            ->assertJsonPath('data.ticket.validity', 'any_day')
            ->assertJsonPath('data.ticket.valid_date', null)
            ->assertJsonPath('data.ticket.valid_until', $event->end_time->toDateString())
            ->assertJsonPath('data.ticket_type.validity', 'any_day');
        $this->assertSame('used', $ticket->fresh()->status);

        $this->travel(1)->days();
        $res = $this->scan($token, $ticket->qr_code)->assertOk()->assertJsonPath('data.result', 'already_used');
        $this->assertStringContainsString('used on '.GateDay::dateFor(now()->subDay()), $res->json('data.message.en'));
        $this->assertStringContainsString('استُخدمت يوم', $res->json('data.message.ar'));
    }

    #[Test]
    public function a_season_pass_admits_its_entries_again_every_day(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice();
        $pass = $this->makeTicket($event, ['booking_date' => null, 'validity' => EventTicketType::VALIDITY_ALL_DAYS, 'max_check_ins' => 2]);

        $this->scan($token, $pass->qr_code)->assertJsonPath('data.result', 'valid')->assertJsonPath('data.entry', ['used' => 1, 'admits' => 2])
            ->assertJsonPath('data.ticket.validity_text', 'Valid every day of the event');
        $this->scan($token, $pass->qr_code)->assertJsonPath('data.result', 'valid')->assertJsonPath('data.entry.used', 2);
        $this->scan($token, $pass->qr_code)->assertJsonPath('data.result', 'already_used');
        $this->assertSame('active', $pass->fresh()->status, 'a pass is never marked used');

        // Offline devices: today's manifest says used up, tomorrow's lists it active again.
        $today = collect($this->gate('GET', '/api/v1/gate/manifest', [], $token)->json('data.tickets'))->firstWhere('v', 'all_days');
        $this->assertSame(['used', 2, 2], [$today['s'], $today['u'], $today['a']]);

        $this->travel(1)->days();
        $tomorrow = collect($this->gate('GET', '/api/v1/gate/manifest', [], $token)->json('data.tickets'))->firstWhere('v', 'all_days');
        $this->assertSame(['active', 0, GateDay::today()], [$tomorrow['s'], $tomorrow['u'], $tomorrow['d']]);
        $this->scan($token, $pass->qr_code)->assertJsonPath('data.result', 'valid')->assertJsonPath('data.entry.used', 1);
        $this->assertSame(3, $pass->fresh()->check_in_count, 'check_in_count stays the lifetime total');

        // The visitor's ticket shows today's entries.
        $mine = $this->api('GET', '/api/v1/tickets/my-tickets', [], $pass->user)->assertOk();
        $this->assertSame(['all_days', 1, 2], [$mine->json('data.0.validity'), $mine->json('data.0.entries_used'), $mine->json('data.0.admits')]);

        // After the event, it no longer admits.
        $this->travel(10)->days();
        $this->scan($token, $pass->qr_code)->assertJsonPath('data.result', 'wrong_date');
    }

    #[Test]
    public function undated_tickets_are_in_the_manifest_of_every_running_day(): void
    {
        $event = $this->makeEvent();
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $any = $this->makeTicket($event, ['booking_date' => null, 'validity' => EventTicketType::VALIDITY_ANY_DAY]);
        $pass = $this->makeTicket($event, ['booking_date' => null, 'validity' => EventTicketType::VALIDITY_ALL_DAYS]);
        $dated = $this->makeTicket($event);

        foreach ([GateDay::today(), GateDay::dateFor(now()->addDays(3))] as $day) {
            $data = $this->gate('GET', '/api/v1/gate/manifest?date='.$day, [], $token)->assertOk()->json('data');
            $byHash = collect($data['tickets'])->keyBy('h');
            $h = fn ($t) => hash('sha256', $data['salt'].$t->qr_code);
            $this->assertSame(['any_day', $day], [$byHash[$h($any)]['v'], $byHash[$h($any)]['d']]);
            $this->assertSame(['all_days', $day], [$byHash[$h($pass)]['v'], $byHash[$h($pass)]['d']]);
            $this->assertSame($day === GateDay::today(), $byHash->has($h($dated)));
            $this->assertArrayNotHasKey('v', $byHash[$h($dated)] ?? []);
        }
        // Not after the event's last day.
        $after = $this->gate('GET', '/api/v1/gate/manifest?date='.GateDay::dateFor(now()->addDays(8)), [], $token)->json('data');
        $this->assertSame(0, $after['count']);
    }
}
