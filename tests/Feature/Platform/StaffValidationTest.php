<?php

namespace Tests\Feature\Platform;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class StaffValidationTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function ticketIn($tenant, $user): Ticket
    {
        $event = $this->makeEvent($tenant);

        return $this->inTenant($tenant, fn () => Ticket::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'ticket_type' => 'standard', 'status' => 'active',
            'price' => '1.000', 'currency' => 'OMR', 'holder_name' => 'X', 'purchase_date' => now(),
            'booking_date' => now()->format('Y-m-d'),
        ]));
    }

    #[Test]
    public function staff_role_from_seeder_can_validate_tickets_of_its_tenant_only(): void
    {
        $this->seedRoles();
        $other = $this->makeTenant('other');
        $customer = $this->makeUser();
        $mine = $this->ticketIn($this->tenant, $customer);
        $theirs = $this->ticketIn($other, $customer);

        $staff = $this->makeUser();
        $this->grantRole($staff, 'staff');

        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $mine->qr_code], $staff)->assertOk();
        // ticket of another tenant is invisible here
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $theirs->qr_code], $staff)->assertNotFound();
        // and the staff role does not carry over to the other tenant
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $theirs->qr_code], $staff, $other)->assertForbidden();
        // guessing the sequential ticket number is not enough
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $mine->ticket_number], $staff)->assertNotFound();
    }

    #[Test]
    public function the_validate_tickets_permission_alone_grants_access(): void
    {
        $this->seedRoles();
        $user = $this->makeUser();
        $this->inTenant($this->tenant, fn () => $user->givePermissionTo('validate tickets'));
        $ticket = $this->ticketIn($this->tenant, $this->makeUser());

        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $ticket->qr_code], $user)->assertOk();
        $this->api('POST', '/api/v1/tickets/validate', ['qr_code' => $ticket->qr_code], $this->makeUser())->assertForbidden();
    }

    #[Test]
    public function validation_history_route_is_not_shadowed_by_ticket_show(): void
    {
        $this->seedRoles();
        $staff = $this->makeUser();
        $this->grantRole($staff, 'staff');
        $this->api('GET', '/api/v1/tickets/validation-history', [], $staff)->assertOk()->assertJsonPath('success', true);
    }
}
