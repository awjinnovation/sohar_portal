<?php

namespace Tests\Feature\Admin;

use App\Models\EventTicketType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Admin → event → Ticket types (docs/ticket_types_contract.md §3). */
class TicketTypesAdminTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'key' => 'family', 'name_ar' => 'تذكرة عائلية', 'name_en' => 'Family', 'description_ar' => 'لأربعة أشخاص', 'description_en' => 'For 4 people',
            'category' => 'family', 'price' => '4.500', 'admits' => 4, 'capacity' => 20, 'per_order_max' => 5, 'min_age' => null, 'max_age' => null,
            'requires_id' => false, 'sales_start' => null, 'sales_end' => null, 'visibility' => 'public', 'color' => null, 'is_active' => true,
        ], $extra);
    }

    #[Test]
    public function ticket_types_are_managed_from_the_event_form(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.000']);
        $admin = $this->admin('event-manager');
        $base = route('admin.events.ticket-types.store', $event);

        $this->actingAs($admin)->get(route('admin.events.edit', $event))->assertOk()
            ->assertSee('id="ticket-types"', false)->assertSee('id="ttConfig"', false)->assertSee('id="ttModal"', false)
            ->assertSee('أنواع التذاكر')->assertSee('General admission')->assertDontSee('name="price"', false);

        // Create from a template.
        $res = $this->actingAs($admin)->postJson($base, $this->payload())->assertCreated()->assertJsonCount(2, 'data');
        $family = EventTicketType::where('event_id', $event->id)->where('key', 'family')->firstOrFail();
        $this->assertSame([4500, 4, 20, 5, 'family', ['ar' => 'تذكرة عائلية', 'en' => 'Family']],
            [$family->price_minor, $family->admits, $family->capacity, $family->per_order_max, $family->category, $family->name]);
        $this->assertSame('#E5883C', collect($res->json('data'))->firstWhere('key', 'family')['color']);

        // Validation.
        $this->actingAs($admin)->postJson($base, $this->payload())->assertStatus(422)->assertJsonValidationErrors('key');
        $this->actingAs($admin)->postJson($base, $this->payload(['key' => 'Bad Key', 'price' => '-1', 'admits' => 0, 'min_age' => 20, 'max_age' => 10, 'color' => 'red']))
            ->assertStatus(422)->assertJsonValidationErrors(['key', 'price', 'admits', 'max_age', 'color']);
        $this->actingAs($admin)->postJson($base, $this->payload(['key' => 'late', 'sales_start' => '2026-12-10T10:00', 'sales_end' => '2026-12-01T10:00']))
            ->assertStatus(422)->assertJsonValidationErrors('sales_end');

        // Update (price changes apply to new orders; legacy event price follows General).
        $general = EventTicketType::where('event_id', $event->id)->where('key', 'general')->firstOrFail();
        $this->actingAs($admin)->putJson("$base/{$general->id}", $this->payload([
            'key' => 'general', 'name_ar' => 'تذكرة عامة', 'name_en' => 'General', 'category' => 'general', 'price' => '2.750', 'admits' => 1, 'capacity' => null,
            'sales_end' => '2026-12-31T23:00', 'color' => '#112233',
        ]))->assertOk();
        $this->assertSame([2750, '#112233', '2026-12-31 23:00'], [$general->fresh()->price_minor, $general->fresh()->color, $general->fresh()->sales_end->format('Y-m-d H:i')]);
        $this->assertSame('2.750', $event->fresh()->price);

        // Drag sort.
        $this->actingAs($admin)->postJson(route('admin.events.ticket-types.reorder', $event), ['ids' => [$family->id, $general->id]])->assertOk()
            ->assertJsonPath('data.0.key', 'family');
        $this->assertSame([0, 1], [$family->fresh()->sort, $general->fresh()->sort]);

        // A sold type cannot be deleted, only deactivated; a type without sales can be.
        $this->inTenant($this->tenant, fn () => Ticket::create(['event_id' => $event->id, 'user_id' => $this->makeUser()->id, 'ticket_type' => 'family',
            'ticket_type_id' => $family->id, 'status' => 'active', 'price' => '4.500', 'currency' => 'OMR', 'holder_name' => 'H', 'purchase_date' => now(),
            'booking_date' => now()->addDay()->toDateString(), 'quantity' => 1, 'max_check_ins' => 4]));
        $this->actingAs($admin)->deleteJson("$base/{$family->id}")->assertStatus(409);
        $this->actingAs($admin)->putJson("$base/{$family->id}", $this->payload(['key' => 'family_new']))->assertStatus(422);
        $this->actingAs($admin)->putJson("$base/{$family->id}", $this->payload(['is_active' => false]))->assertOk();
        $this->assertFalse($family->fresh()->is_active);
        $this->actingAs($admin)->deleteJson("$base/{$general->id}")->assertOk();
        $this->actingAs($admin)->deleteJson("$base/{$family->id}")->assertStatus(409);
        $this->assertSame(1, EventTicketType::where('event_id', $event->id)->count());

        // Per-type sales summary on the event page.
        $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()
            ->assertSee('مبيعات أنواع التذاكر')->assertSee('data-type="family"', false)->assertSee('4.500');

        // Other tenants' events and missing permission.
        $foreign = $this->makeEvent($this->makeTenant('other-org'));
        $this->actingAs($admin)->postJson(route('admin.events.ticket-types.store', $foreign), $this->payload())->assertNotFound();
        $viewer = $this->admin('viewer');
        $this->actingAs($viewer)->postJson($base, $this->payload(['key' => 'x']))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.events.ticket-types.index', $event))->assertForbidden();
    }

    #[Test]
    public function a_new_event_gets_its_general_type_and_the_event_price_field_updates_it(): void
    {
        $event = $this->makeEvent(null, ['price' => '3.000']);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.events.create'))->assertOk()->assertSee('name="price"', false);

        $this->actingAs($admin)->put(route('admin.events.update', $event), [
            'title' => 'Concert', 'title_ar' => 'حفل', 'description' => 'd', 'description_ar' => 'd', 'category_id' => $event->category_id,
            'start_time' => $event->start_time->format('Y-m-d H:i'), 'end_time' => $event->end_time->format('Y-m-d H:i'), 'location' => 'Hall',
            'price' => '3.250', 'total_tickets' => 100, 'is_active' => '1',
        ])->assertRedirect(route('admin.events.index'));
        $this->assertSame(3250, EventTicketType::where('event_id', $event->id)->where('key', 'general')->value('price_minor'));
        $this->assertSame('3.250', $event->fresh()->price);
    }
}
