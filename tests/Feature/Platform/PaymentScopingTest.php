<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class PaymentScopingTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function pendingPaymentFor($user): string
    {
        $event = $this->makeEvent();

        return $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
        ], $user)->assertCreated()->json('transaction_id');
    }

    #[Test]
    public function payment_reads_are_scoped_to_the_owner(): void
    {
        $owner = $this->makeUser();
        $tx = $this->pendingPaymentFor($owner);
        $id = Payment::where('transaction_id', $tx)->value('id');
        $intruder = $this->makeUser();

        $this->api('GET', "/api/v1/payments/status/$tx", [], $intruder)->assertNotFound();
        $this->api('POST', '/api/v1/payments/confirm', ['transaction_id' => $tx], $intruder)->assertNotFound();
        $this->api('GET', "/api/v1/payments/$id", [], $intruder)->assertNotFound();
        $this->api('GET', '/api/v1/payments/history', [], $intruder)->assertOk()->assertJsonCount(0, 'data');

        $this->api('GET', "/api/v1/payments/status/$tx", [], $owner)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->api('GET', "/api/v1/payments/$id", [], $owner)->assertOk();
    }

    #[Test]
    public function only_the_owner_can_cancel_and_the_public_cancel_page_does_not_mutate(): void
    {
        $owner = $this->makeUser();
        $tx = $this->pendingPaymentFor($owner);

        // unauthenticated API cancel is gone
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/payments/cancel', ['transaction_id' => $tx])->assertUnauthorized();
        // anybody hitting the public return URL cannot cancel
        $this->get("/payment/cancel?transaction_id=$tx")->assertOk();
        $this->post("/payment/cancel?transaction_id=$tx")->assertOk();
        // another user cannot cancel
        $this->api('POST', '/api/v1/payments/cancel', ['transaction_id' => $tx], $this->makeUser())->assertNotFound();
        $this->assertSame('pending', Payment::where('transaction_id', $tx)->value('status'));

        $this->api('POST', '/api/v1/payments/cancel', ['transaction_id' => $tx], $owner)->assertOk();
        $this->assertSame('cancelled', Payment::where('transaction_id', $tx)->value('status'));
    }

    #[Test]
    public function success_page_without_gateway_payload_never_completes_a_payment(): void
    {
        $tx = $this->pendingPaymentFor($this->makeUser());
        $this->get("/payment/success?transaction_id=$tx")->assertOk();
        $this->post('/payment/success', ['encResp' => 'deadbeef'])->assertOk();

        $this->assertSame('pending', Payment::where('transaction_id', $tx)->value('status'));
        $this->assertSame(0, Ticket::count());
    }

    #[Test]
    public function internal_errors_are_not_leaked_to_clients(): void
    {
        config(['app.debug' => false]);
        $this->getJson('/api/v1/events/abc/availability?date=bad')->assertStatus(422)->assertJsonPath('success', false);
        $this->api('GET', '/api/v1/payments/status/NOPE', [], $this->makeUser())
            ->assertNotFound()->assertJsonMissingPath('error')->assertJsonPath('success', false);
    }
}
