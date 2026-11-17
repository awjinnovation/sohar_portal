<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class PaymentPagesTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function gatewayCheckout($user, $event, int $qty = 2)
    {
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => $qty,
        ], $user)->assertCreated();

        return [$res->json('transaction_id'), $res->json('payment_url')];
    }

    private function fakeCompleteUrl(string $paymentUrl): string
    {
        $page = $this->get($paymentUrl)->assertOk();
        preg_match('/action="([^"]+)"/', $page->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    #[Test]
    public function result_pages_receive_error_codes_and_localize_them(): void
    {
        $user = $this->makeUser();
        [$txId] = $this->gatewayCheckout($user, $this->makeEvent(null, ['price' => '1.000']));

        $this->get("/payment/success?transaction_id=$txId&lang=en")->assertOk()
            ->assertViewHas('code', 'not_completed')
            ->assertSee('data-error-code="not_completed"', false)
            ->assertSee('The payment is not completed yet');
        $this->get("/payment/success?transaction_id=$txId&lang=ar")->assertOk()
            ->assertSee('لم تكتمل عملية الدفع بعد');
        $this->get('/payment/success')->assertOk()->assertViewHas('code', 'no_response');
        $this->post('/payment/success', ['encResp' => 'garbage'])->assertOk()->assertViewHas('code', 'verification_failed');
    }

    #[Test]
    public function success_page_shows_issued_tickets_and_points_earned(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent(null, ['price' => '2.000']);
        [$txId, $url] = $this->gatewayCheckout($user, $event, 2);

        $this->post($this->fakeCompleteUrl($url), ['result' => 'success'])->assertRedirect();

        $page = $this->get("/payment/success?transaction_id=$txId&lang=en")->assertOk()
            ->assertViewHas('pointsEarned', 40) // 4.000 OMR × 10
            ->assertSee('Tickets issued')
            ->assertSee('data-tickets-issued="2"', false)
            ->assertSee('data-points-earned="40"', false);
        $tickets = Ticket::where('transaction_id', $txId)->get();
        $this->assertCount(2, $tickets);
        foreach ($tickets as $t) {
            $page->assertSee(route('payment.ticket.single', $t->qr_code), false);
        }
    }

    #[Test]
    public function tickets_page_opens_with_the_language_appended_and_offers_one_pdf(): void
    {
        $user = $this->makeUser();
        [$txId, $url] = $this->gatewayCheckout($user, $this->makeEvent(null, ['price' => '2.000']), 3);
        $this->post($this->fakeCompleteUrl($url), ['result' => 'success'])->assertRedirect();

        // The success page links here with "&lang=…" after the signature.
        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute('payment.download', now()->addDay(), ['transactionId' => $txId]);
        $this->get($signed.'&lang=en')->assertOk()
            ->assertSee('data-ticket-export', false)
            ->assertSee('data-cards=".ticket-mini"', false)
            ->assertSee('Download all tickets as one PDF')
            ->assertSee('Save all tickets as images')
            ->assertSee('js/ticket-export.js', false);
        // Still signed: another transaction id, or no signature, is refused.
        $this->get(str_replace($txId, 'TXN-other', $signed).'&lang=en')->assertForbidden();
        $this->get(route('payment.download', ['transactionId' => $txId]))->assertForbidden();

        // One ticket: a PDF of it, no "all images".
        $ticket = Ticket::where('transaction_id', $txId)->first();
        $this->get('/ticket/'.$ticket->qr_code.'?lang=en')->assertOk()
            ->assertSee('Download ticket as PDF')
            ->assertDontSee('Save all tickets as images');
    }

    #[Test]
    public function public_ticket_page_shows_used_and_cancelled_tickets_with_their_status(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent();
        $make = fn (string $status) => Ticket::create([
            'user_id' => $user->id, 'event_id' => $event->id, 'booking_date' => now()->format('Y-m-d'),
            'quantity' => 1, 'max_check_ins' => 1, 'ticket_type' => 'standard', 'status' => $status,
            'price' => 2.5, 'currency' => 'OMR', 'purchase_date' => now(), 'holder_name' => 'Holder',
            'used_at' => $status === 'used' ? now() : null,
        ]);

        $active = $make('active');
        $used = $make('used');
        $cancelled = $make('cancelled');

        $this->get('/ticket/'.$active->qr_code.'?lang=en')->assertOk()->assertSee('data-ticket-status="active"', false)->assertSee('Valid');
        $this->get('/ticket/'.$used->qr_code.'?lang=en')->assertOk()->assertSee('data-ticket-status="used"', false)->assertSee('This ticket has been used');
        $this->get('/ticket/'.$cancelled->qr_code.'?lang=en')->assertOk()->assertSee('data-ticket-status="cancelled"', false)->assertSee('not valid for entry');
        $this->get('/ticket/TKT-unknown')->assertNotFound();
    }
}
