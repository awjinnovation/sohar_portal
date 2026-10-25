<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Ticket;
use App\Services\Gate\GateDay;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/** Ticket types per event (docs/ticket_types_contract.md §2). */
class TicketTypesTest extends GateTestCase
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

    private function date(): string
    {
        return now()->addDay()->toDateString();
    }

    #[Test]
    public function every_event_gets_a_general_type_at_its_price_and_legacy_pricing_tiers(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000', 'pricing' => [['type' => 'standard', 'price' => '2.000'], ['type' => 'vip', 'price' => '7.250']]]);

        $types = EventTicketType::where('event_id', $event->id)->orderBy('sort')->get();
        $this->assertSame(['general', 'vip'], $types->pluck('key')->all());
        $this->assertSame([2000, 7250], $types->pluck('price_minor')->all());
        $this->assertSame(['General admission', 'تذكرة عامة'], [$types[0]->name['en'], $types[0]->name['ar']]);
        $this->assertSame(EventTicketType::VISIBILITY_PUBLIC, $types[0]->visibility);
    }

    #[Test]
    public function event_resources_list_public_types_with_price_from_and_availability(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000', 'total_tickets' => 50]);
        $this->addType($event, ['key' => 'family', 'category' => 'family', 'price_minor' => 6000, 'admits' => 4, 'capacity' => 5, 'min_age' => null]);
        $this->addType($event, ['key' => 'child', 'category' => 'child', 'price_minor' => 500, 'max_age' => 11, 'requires_id' => true, 'color' => '#123456']);
        $this->addType($event, ['key' => 'door', 'visibility' => 'box_office', 'price_minor' => 100]);
        $this->addType($event, ['key' => 'off', 'is_active' => false, 'price_minor' => 50]);

        $res = $this->getJson("/api/v1/events/{$event->id}", $this->tenantHeaders(null, ['Accept-Language' => 'en']))->assertOk();
        $types = collect($res->json('data.ticket_types'))->keyBy('key');
        $this->assertSame(['general', 'family', 'child'], $types->keys()->all());
        $res->assertJsonPath('data.price_from_minor', 500)->assertJsonPath('data.price_from', '0.500')
            ->assertJsonPath('data.price', 2); // legacy price = the General type
        $this->assertSame(['id', 'key', 'name', 'name_text', 'description_text', 'category', 'icon', 'price_minor', 'price', 'is_free', 'admits', 'per_order_max',
            'min_age', 'max_age', 'requires_id', 'color', 'available', 'sales_open', 'sales_start', 'sales_end', 'visibility'], array_keys($types['child']));
        $this->assertSame([4, 5, '6.000'], [$types['family']['admits'], $types['family']['available'], $types['family']['price']]);
        $this->assertSame([11, true, '#123456', 'Child'], [$types['child']['max_age'], $types['child']['requires_id'], $types['child']['color'], $types['child']['name_text']]);
        $this->assertSame(50, $types['general']['available']);

        $list = $this->getJson('/api/v1/events', $this->tenantHeaders())->assertOk();
        $this->assertCount(3, $list->json('data.0.ticket_types'));

        $avail = $this->getJson("/api/v1/events/{$event->id}/availability?date={$this->date()}", $this->tenantHeaders())->assertOk();
        $this->assertSame([50, 5, 50], array_column($avail->json('data.ticket_types'), 'available'));
    }

    #[Test]
    public function quote_accepts_items_and_returns_lines_while_the_old_quantity_still_works(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000']);
        $family = $this->addType($event, ['key' => 'family', 'price_minor' => 6000, 'admits' => 4]);
        $general = $this->general($event);
        $q = fn (array $params) => $this->getJson('/api/v1/payments/quote?'.http_build_query(['event_id' => $event->id, 'booking_date' => $this->date()] + $params), $this->tenantHeaders(null, ['Accept-Language' => 'en']));

        $q(['items' => [['ticket_type_id' => $general->id, 'quantity' => 2], ['ticket_type_id' => $family->id, 'quantity' => 1], ['ticket_type_id' => $general->id, 'quantity' => 0]]])
            ->assertOk()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.subtotal_minor', 10000)
            ->assertJsonPath('data.total_minor', 10000)
            ->assertJsonPath('data.unit_price_minor', null)
            ->assertJsonPath('data.lines.0', ['ticket_type_id' => $general->id, 'key' => 'general', 'name_text' => 'General admission', 'quantity' => 2,
                'unit_price_minor' => 2000, 'unit_price' => '2.000', 'line_total_minor' => 4000, 'line_total' => '4.000', 'admits' => 1])
            ->assertJsonPath('data.lines.1.line_total_minor', 6000);

        $q(['quantity' => 2])->assertOk()->assertJsonPath('data.ticket_type', 'general')->assertJsonPath('data.unit_price_minor', 2000)
            ->assertJsonPath('data.total_minor', 4000)->assertJsonCount(1, 'data.lines');
        $q(['quantity' => 1, 'ticket_type' => 'family'])->assertOk()->assertJsonPath('data.total_minor', 6000);
        $q(['items' => [['ticket_type_id' => $general->id, 'quantity' => 0]]])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    #[Test]
    public function per_type_rules_give_the_contract_error_codes(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.000', 'total_tickets' => 10]);
        $door = $this->addType($event, ['key' => 'door', 'visibility' => 'box_office']);
        $off = $this->addType($event, ['key' => 'off', 'is_active' => false]);
        $later = $this->addType($event, ['key' => 'later', 'sales_start' => now()->addDays(3)]);
        $vip = $this->addType($event, ['key' => 'vip', 'capacity' => 2, 'per_order_max' => 3]);
        $foreign = $this->addType($this->makeEvent(), ['key' => 'x']);
        $user = $this->makeUser();
        $init = fn (array $items) => $this->api('POST', '/api/v1/payments/initialize', ['event_id' => $event->id, 'booking_date' => $this->date(), 'items' => $items], $user);

        foreach ([$door, $off, $foreign] as $type) {
            $init([['ticket_type_id' => $type->id, 'quantity' => 1]])->assertStatus(422)->assertJsonPath('code', 'TYPE_NOT_AVAILABLE')->assertJsonPath('data.ticket_type_id', $type->id);
        }
        $init([['ticket_type_id' => $later->id, 'quantity' => 1]])->assertStatus(422)->assertJsonPath('code', 'SALES_CLOSED')->assertJsonPath('data.ticket_type_id', $later->id);
        $init([['ticket_type_id' => $vip->id, 'quantity' => 4]])->assertStatus(422)->assertJsonPath('code', 'PER_ORDER_LIMIT')->assertJsonPath('data.per_order_max', 3);
        $init([['ticket_type_id' => $vip->id, 'quantity' => 3]])->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT')
            ->assertJsonPath('data.ticket_type_id', $vip->id)->assertJsonPath('data.available', 2);
        $init([['ticket_type_id' => $this->general($event)->id, 'quantity' => 11]])->assertStatus(422)->assertJsonPath('code', 'PER_ORDER_LIMIT');

        // Event capacity is shared by all types.
        $this->fund($user, 100000);
        $this->api('POST', '/api/v1/payments/initialize', ['event_id' => $event->id, 'booking_date' => $this->date(), 'method' => 'wallet', 'idempotency_key' => 'cap-1',
            'items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 9]]], $user)->assertCreated();
        $init([['ticket_type_id' => $vip->id, 'quantity' => 2]])->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.available', 1);
    }

    #[Test]
    public function checkout_issues_tickets_per_type_and_ticket_resources_carry_the_type(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000']);
        $family = $this->addType($event, ['key' => 'family', 'category' => 'family', 'price_minor' => 6000, 'admits' => 4, 'color' => '#E5883C']);
        $user = $this->makeUser();
        $this->fund($user, 20000);

        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => $this->date(), 'method' => 'wallet', 'idempotency_key' => 'tt-1',
            'items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 1], ['ticket_type_id' => $family->id, 'quantity' => 2]],
        ], $user)->assertCreated()->assertJsonPath('status', 'completed')->assertJsonCount(3, 'data.tickets');
        $this->assertSame(14000, $res->json('amount_minor'));
        $res->assertJsonPath('data.tickets.1.ticket_type.key', 'family')->assertJsonPath('data.tickets.1.admits', 4)->assertJsonPath('data.tickets.1.entries_used', 0)
            ->assertJsonPath('data.lines.1.quantity', 2);

        $tickets = Ticket::orderBy('id')->get();
        $this->assertSame(['general', 'family', 'family'], $tickets->pluck('ticket_type')->all());
        $this->assertSame([$this->general($event)->id, $family->id, $family->id], $tickets->pluck('ticket_type_id')->all());
        $this->assertSame(['2.000', '6.000', '6.000'], $tickets->pluck('price')->all());
        $this->assertSame(6000, $this->wallet($user)->balance_minor);

        $mine = $this->api('GET', '/api/v1/tickets/my-tickets', [], $user)->assertOk();
        $fam = collect($mine->json('data'))->firstWhere('ticket.type', 'family');
        $this->assertSame(['id' => $family->id, 'key' => 'family', 'name_text' => 'Family', 'category' => 'family', 'color' => '#E5883C', 'admits' => 4], $fam['ticket_type']);
        $this->assertSame([4, 0], [$fam['admits'], $fam['entries_used']]);
        $this->api('GET', '/api/v1/tickets/'.$tickets[1]->id, [], $user)->assertOk()->assertJsonPath('data.ticket_type.key', 'family');
    }

    #[Test]
    public function a_family_ticket_is_scanned_once_per_person_and_scans_show_the_type(): void
    {
        $event = $this->makeEvent();
        $family = $this->addType($event, ['key' => 'family', 'category' => 'family', 'admits' => 4, 'requires_id' => true, 'min_age' => 18]);
        $ticket = $this->makeTicket($event, ['ticket_type' => 'family', 'ticket_type_id' => $family->id, 'max_check_ins' => 4]);
        [, $token] = $this->pairDevice();

        foreach ([1, 2, 3, 4] as $n) {
            $this->scan($token, $ticket->qr_code)->assertOk()
                ->assertJsonPath('data.result', 'valid')
                ->assertJsonPath('data.entry', ['used' => $n, 'admits' => 4])
                ->assertJsonPath('data.ticket_type.category', 'family')
                ->assertJsonPath('data.ticket_type.requires_id', true)
                ->assertJsonPath('data.ticket_type.min_age', 18);
        }
        $this->scan($token, $ticket->qr_code)->assertJsonPath('data.result', 'already_used')->assertJsonPath('data.entry.used', 4);
        $this->assertSame('used', $ticket->fresh()->status);

        // Gates restricted by type keys.
        $vipGate = $this->makeGate(['ticket_types' => ['vip']]);
        [, $vipToken] = $this->pairDevice($vipGate, null, 'VIP lane');
        $this->scan($vipToken, $this->makeTicket($event, ['ticket_type' => 'family', 'ticket_type_id' => $family->id, 'max_check_ins' => 4])->qr_code)
            ->assertJsonPath('data.result', 'wrong_gate');

        // Offline manifest carries admits / entries used.
        $manifest = $this->gate('GET', '/api/v1/gate/manifest', [], $token)->assertOk();
        $entry = collect($manifest->json('data.tickets'))->firstWhere('h', hash('sha256', $manifest->json('data.salt').$ticket->qr_code));
        $this->assertSame([4, 4, 'used'], [$entry['a'], $entry['u'], $entry['s']]);
        $this->assertSame(GateDay::today(), $entry['d']);
    }
}
