<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Gate;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/** Admin switch «السماح بالطلبات المجانية» (tenant setting tickets.free_orders, docs/ticket_types_contract.md §9). */
class FreeOrdersTest extends GateTestCase
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

    private function freeOrders(bool $on): void
    {
        $this->tenant->putSetting('tickets.free_orders', $on)->save();
    }

    /** @return array{0: Event, 1: EventTicketType} event with a paid General type (1.000) and a free Child type */
    private function eventWithFreeChild(): array
    {
        $event = $this->makeEvent(null, ['price' => '1.000', 'total_tickets' => 100]);
        $child = $this->addType($event, ['key' => 'child', 'category' => 'child', 'price_minor' => 0]);

        return [$event, $child];
    }

    private function typeKeys($response, string $path): array
    {
        return collect($response->json($path))->pluck('key')->all();
    }

    #[Test]
    public function free_types_are_on_sale_by_default_and_issue_tickets_without_payment(): void
    {
        [$event, $child] = $this->eventWithFreeChild();
        $this->assertTrue($this->tenant->fresh()->freeOrdersAllowed());

        $res = $this->getJson("/api/v1/events/{$event->id}", $this->tenantHeaders())->assertOk();
        $this->assertSame(['general', 'child'], $this->typeKeys($res, 'data.ticket_types'));

        $user = $this->makeUser();
        $this->api('POST', '/api/v1/payments/initialize', ['event_id' => $event->id, 'booking_date' => now()->addDay()->toDateString(),
            'items' => [['ticket_type_id' => $child->id, 'quantity' => 2]], 'idempotency_key' => 'free-1'], $user)
            ->assertSuccessful()->assertJsonPath('data.status', 'completed');
        $this->assertSame(2, Ticket::where('user_id', $user->id)->where('ticket_type_id', $child->id)->count());
    }

    #[Test]
    public function paid_only_hides_free_types_from_the_app_and_booking_site_and_refuses_them(): void
    {
        [$event, $child] = $this->eventWithFreeChild();
        $this->freeOrders(false);

        $app = $this->getJson("/api/v1/events/{$event->id}", $this->tenantHeaders())->assertOk();
        $this->assertSame(['general'], $this->typeKeys($app, 'data.ticket_types'));
        $site = $this->getJson("/api/v1/booking/events/{$event->id}")->assertOk();
        $this->assertSame([$this->general($event)->id], collect($site->json('data.ticket_types'))->pluck('id')->all());

        $user = $this->makeUser();
        $init = fn (array $body) => $this->actingAs($user, 'sanctum')->json('POST', '/api/v1/payments/initialize',
            ['event_id' => $event->id, 'booking_date' => now()->addDay()->toDateString()] + $body, $this->tenantHeaders(null, ['Accept-Language' => 'ar']));

        $init(['items' => [['ticket_type_id' => $child->id, 'quantity' => 1]]])->assertStatus(422)
            ->assertJsonPath('code', 'FREE_ORDERS_OFF')->assertJsonPath('data.ticket_type_id', $child->id)
            ->assertJsonPath('message', 'تذاكر «نوع child» المجانية غير متاحة حاليًا، ويمكن اختيار نوع مدفوع.');
        // With a paid ticket in the same order, and by the legacy type key.
        $init(['items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 1], ['ticket_type_id' => $child->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'FREE_ORDERS_OFF');
        $init(['quantity' => 1, 'ticket_type' => 'child'])->assertStatus(422)->assertJsonPath('code', 'FREE_ORDERS_OFF');
        $this->assertSame(0, Ticket::count());

        // Paid tickets are sold as before.
        $init(['items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 1]], 'idempotency_key' => 'paid-1'])
            ->assertSuccessful()->assertJsonPath('data.status', 'pending');

        // Turned on again (the free day): the type is back.
        $this->freeOrders(true);
        $this->assertSame(['general', 'child'], $this->typeKeys($this->getJson("/api/v1/events/{$event->id}", $this->tenantHeaders()), 'data.ticket_types'));
        $init(['items' => [['ticket_type_id' => $child->id, 'quantity' => 1]], 'idempotency_key' => 'free-2'])
            ->assertSuccessful()->assertJsonPath('data.status', 'completed');
    }

    #[Test]
    public function tickets_issued_while_free_orders_were_on_stay_valid(): void
    {
        [$event, $child] = $this->eventWithFreeChild();
        $user = $this->makeUser();
        $this->api('POST', '/api/v1/payments/initialize', ['event_id' => $event->id, 'booking_date' => now()->toDateString(),
            'items' => [['ticket_type_id' => $child->id, 'quantity' => 1]], 'idempotency_key' => 'free-1'], $user)->assertSuccessful();
        $this->freeOrders(false);

        $ticket = Ticket::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('active', $ticket->status);
        $this->assertSame($child->id, $ticket->ticket_type_id);
        $mine = $this->api('GET', '/api/v1/tickets/my-tickets', [], $user)->assertOk();
        $this->assertSame('child', collect($mine->json('data'))->first()['ticket_type']['key']);
        $this->api('GET', '/api/v1/tickets/'.$ticket->id, [], $user)->assertOk()->assertJsonPath('data.ticket_type.key', 'child');
    }

    #[Test]
    public function the_gate_box_office_follows_the_switch(): void
    {
        $this->seedRoles();
        config(['auth.otp.test_code' => '123456']);
        $this->tenant->putSetting('gate_sales.enabled', true)->save();
        [$event, $child] = $this->eventWithFreeChild();
        $gate = $this->makeGate(['can_sell' => true]);
        [, $token] = $this->pairDevice($gate);
        $staff = $this->makeUser('96890000001');
        $this->grantRole($staff, 'staff');
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000001'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $token)->assertOk();

        $catalog = fn () => $this->typeKeys($this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertOk(), 'data.events.0.ticket_types');
        $this->assertSame(['general', 'child'], $catalog());

        $this->freeOrders(false);
        $this->assertSame(['general'], $catalog());
        $this->gate('POST', '/api/v1/gate/sales/orders', [
            'event_id' => $event->id, 'items' => [['ticket_type_id' => $child->id, 'quantity' => 1]], 'buyer_phone' => '99112233',
            'locale' => 'ar', 'send_sms' => false, 'idempotency_key' => (string) Str::uuid(),
        ], $token)->assertStatus(422)->assertJsonPath('code', 'TYPE_NOT_AVAILABLE');
        $this->assertSame(0, Ticket::count());
    }

    #[Test]
    public function the_admin_switches_free_orders_and_the_ticket_types_card_says_when_they_are_off(): void
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'admin');
        [$event] = $this->eventWithFreeChild();

        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()
            ->assertSee('id="sec-free-orders"', false)->assertSee('name="s[tickets][free_orders]"', false);
        $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk()->assertDontSee('id="ttFreeOff"', false);

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'], 's' => ['tickets' => ['free_orders' => '0']],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->tenant->fresh()->freeOrdersAllowed());
        $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk()->assertSee('id="ttFreeOff"', false);

        $this->actingAs($admin)->put(route('admin.branding.update'), [
            'name' => ['en' => 'North', 'ar' => 'الشمال'], 's' => ['tickets' => ['free_orders' => '1']],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->tenant->fresh()->freeOrdersAllowed());
    }
}
