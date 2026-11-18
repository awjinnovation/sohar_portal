<?php

namespace Tests\Feature\Admin;

use App\Models\BoxOfficeOrder;
use App\Models\Gate;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/**
 * Admin → «البحث برقم الهاتف»: a visitor's tickets, payments and gate orders
 * in this tenant, found by phone number in any format.
 */
class PhoneLookupTest extends GateTestCase
{
    private User $visitor;

    private Ticket $ticket;

    private Gate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->visitor = User::factory()->create(['name' => 'Visitor One', 'phone_number' => '96891234567', 'email' => null]);
        $this->ticket = $this->makeTicket(null, ['user_id' => $this->visitor->id]);
        $this->gate = $this->makeGate();
        $this->inTenant($this->tenant, function () {
            Payment::create([
                'user_id' => $this->visitor->id, 'transaction_id' => 'TX-PHONE-1', 'payment_type' => 'ticket', 'method' => 'gateway',
                'amount' => 2.5, 'amount_minor' => 2500, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now(),
            ]);
            BoxOfficeOrder::create([
                'gate_id' => $this->gate->id, 'buyer_user_id' => $this->visitor->id, 'reference' => 'BO-111111-0001',
                'buyer_phone' => '96891234567', 'booking_date' => now()->toDateString(), 'quantity' => 1,
                'unit_price_minor' => 1000, 'total_minor' => 1000, 'currency' => 'OMR', 'status' => 'paid',
                'public_token' => str_repeat('a', 32), 'expires_at' => now()->addHour(), 'idempotency_key' => 'k1',
            ]);
        });
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $role);

        return $user;
    }

    #[Test]
    public function an_admin_finds_a_visitors_tickets_payments_and_gate_orders_by_phone_in_any_format(): void
    {
        // Someone else, and the same visitor in another organisation: never shown.
        $other = User::factory()->create(['name' => 'Other Visitor', 'phone_number' => '96899998888']);
        $otherTicket = $this->makeTicket(null, ['user_id' => $other->id]);
        $foreignTenant = $this->makeTenant('other-org');
        $foreignTicket = $this->makeTicket(null, ['user_id' => $this->visitor->id], $foreignTenant);

        $this->actingAs($admin = $this->user('admin'));

        foreach (['91234567', '9123 4567', '+968 9123 4567', '0096891234567', '96891234567'] as $typed) {
            $this->get(route('admin.phone-lookup', ['phone' => $typed]))->assertOk()
                ->assertSee('data-lookup-mode="exact"', false)
                ->assertSee('Visitor One')
                ->assertSee('data-lookup-tickets="1"', false)
                ->assertSee($this->ticket->ticket_number)
                ->assertSee('data-lookup-payments="1"', false)
                ->assertSee('TX-PHONE-1')
                ->assertSee('data-lookup-orders="1"', false)
                ->assertSee('BO-111111-0001')
                ->assertDontSee($foreignTicket->ticket_number)
                ->assertDontSee($otherTicket->ticket_number)
                ->assertDontSee('Other Visitor');
        }

        // Part of a number (4+ digits) matches every number containing it.
        $this->get(route('admin.phone-lookup', ['phone' => '4567']))->assertOk()
            ->assertSee('data-lookup-mode="partial"', false)
            ->assertSee('Visitor One')
            ->assertDontSee('Other Visitor');

        // Too short, and unknown numbers.
        $this->get(route('admin.phone-lookup', ['phone' => '12']))->assertOk()->assertSee('أدخل رقم الهاتف كاملًا، أو 4 أرقام منه على الأقل');
        $this->get(route('admin.phone-lookup', ['phone' => '97777777']))->assertOk()->assertSee('لا توجد نتائج لهذا الرقم');
        // No query yet: only the search form.
        $this->get(route('admin.phone-lookup'))->assertOk()->assertSee('name="phone"', false)->assertDontSee('data-lookup-tickets', false);

        // The ticket and payment lists also search by phone, in any format.
        $this->get(route('admin.tickets.index', ['q' => '+968 9123 4567']))->assertOk()
            ->assertSee($this->ticket->ticket_number)->assertDontSee($otherTicket->ticket_number)
            ->assertSee(route('admin.phone-lookup'), false);
        $this->get(route('admin.payments.index', ['q' => '9123 4567']))->assertOk()->assertSee('TX-PHONE-1');
    }

    #[Test]
    public function an_account_with_nothing_in_this_organisation_is_not_shown(): void
    {
        $outsider = User::factory()->create(['name' => 'Elsewhere Only', 'phone_number' => '96895550000']);
        $this->makeTicket(null, ['user_id' => $outsider->id], $this->makeTenant('other-org'));

        $this->actingAs($this->user('admin'))
            ->get(route('admin.phone-lookup', ['phone' => '95550000']))->assertOk()
            ->assertSee('لا توجد نتائج لهذا الرقم')
            ->assertDontSee('Elsewhere Only');
    }

    #[Test]
    public function sections_follow_the_viewers_permissions(): void
    {
        // viewer: payments only.
        $this->actingAs($this->user('viewer'))
            ->get(route('admin.phone-lookup', ['phone' => '91234567']))->assertOk()
            ->assertSee('TX-PHONE-1')
            ->assertDontSee('data-lookup-tickets', false)
            ->assertDontSee('data-lookup-orders', false)
            ->assertDontSee($this->ticket->ticket_number);

        // ticket-manager: tickets, payments and gates (its permissions include all three).
        $this->actingAs($this->user('ticket-manager'))
            ->get(route('admin.phone-lookup', ['phone' => '91234567']))->assertOk()
            ->assertSee($this->ticket->ticket_number)
            ->assertSee('TX-PHONE-1');

        // No tickets, payments or gates permission: refused.
        $this->actingAs($this->user('content-manager'))
            ->get(route('admin.phone-lookup', ['phone' => '91234567']))->assertForbidden();
    }
}
