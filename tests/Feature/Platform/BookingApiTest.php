<?php

namespace Tests\Feature\Platform;

use App\Http\Controllers\Web\VisitorBookingController;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\TenantTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** API of the booking site booking-web/ (Api\BookingController, /api/v1/booking/events/{event}/*). */
class BookingApiTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private const PHONE = '96899112233';

    private const SITE = 'https://book.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['auth.otp.test_code' => '123456', 'booking.web_url' => self::SITE]);
    }

    private function url(Event $event, string $path = ''): string
    {
        return "/api/v1/booking/events/{$event->id}".$path;
    }

    private function general(Event $event): EventTicketType
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $event->id)->where('key', 'general')->firstOrFail();
    }

    /** Phone as typed → code → token. */
    private function signIn(Event $event): string
    {
        $this->postJson($this->url($event, '/otp'), ['phone_number' => '9911 2233', 'lang' => 'en'])->assertOk()
            ->assertJsonPath('data.phone', self::PHONE)->assertJsonPath('data.phone_masked', "\u{2066}•••• 2233\u{2069}");

        return $this->postJson($this->url($event, '/verify'), ['phone_number' => self::PHONE, 'otp' => '123456', 'lang' => 'en'])
            ->assertOk()->json('data.token');
    }

    private function book(Event $event, string $token, int $quantity, array $extra = [])
    {
        return $this->withToken($token)->postJson($this->url($event, '/checkout'), array_merge([
            'lang' => 'en', 'form_key' => 'form-1', 'booking_date' => TenantTime::today($this->tenant),
            'items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => $quantity]],
        ], $extra));
    }

    private function fakeCompleteUrl(string $paymentUrl): string
    {
        $page = $this->get($paymentUrl)->assertOk();
        preg_match('/action="([^"]+)"/', $page->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    #[Test]
    public function the_event_comes_from_the_url_alone_with_the_tenant_branding_and_cdn_cache_headers(): void
    {
        $other = $this->makeTenant('other-fest', ['tenant' => ['default_locale' => 'en']]);
        $event = $this->makeEvent($other, ['price' => '2.500']);

        // No X-Tenant (and a wrong one is ignored): the event decides the tenant.
        $response = $this->getJson($this->url($event).'?lang=en', ['X-Tenant' => 'no-such-tenant'])->assertOk()
            ->assertJsonPath('data.lang', 'en')
            ->assertJsonPath('data.tenant.slug', 'other-fest')
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.event.ended', false)
            ->assertJsonPath('data.ticket_types.0.price_minor', 2500)
            ->assertJsonPath('data.ticket_types.0.sales_open', true)
            ->assertJsonStructure(['data' => ['tenant' => ['name', 'theme' => ['root', 'light', 'dark', 'fonts_url', 'logo_chip']], 'dates', 'today']]);
        $this->assertSame('#', substr((string) $response->json('data.tenant.theme.light.--brand'), 0, 1));
        $this->assertStringContainsString('s-maxage=15', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Vary'));
        $this->assertNotNull($etag = $response->headers->get('ETag'));

        // Browsers revalidate: 304 on the ETag, still cacheable for the CDN.
        $this->getJson($this->url($event).'?lang=en', ['If-None-Match' => $etag])->assertStatus(304)
            ->assertHeader('X-Cache', 'HIT');

        // Without ?lang the tenant's default language.
        $this->getJson($this->url($event))->assertOk()->assertJsonPath('data.lang', 'en');
    }

    #[Test]
    public function only_active_events_of_tenants_with_web_booking_on_are_offered(): void
    {
        $event = $this->makeEvent();
        $this->getJson($this->url($event))->assertOk();

        $event->update(['is_active' => false]);
        $this->getJson($this->url($event).'?x=1')->assertNotFound()->assertJsonPath('code', 'EVENT_NOT_FOUND')
            ->assertHeader('Cache-Control', 'no-store, private');

        $event->update(['is_active' => true]);
        $this->tenant->putSetting('modules.web_booking', false)->save();
        $this->getJson($this->url($event).'?x=2')->assertNotFound();
        $this->postJson($this->url($event, '/otp'), ['phone_number' => '99112233'])->assertNotFound();
        $this->getJson('/api/v1/booking/events/999999')->assertNotFound();
    }

    #[Test]
    public function a_visitor_signs_in_pays_by_card_returns_to_the_booking_site_and_sees_the_tickets(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $token = $this->signIn($event);

        $this->withToken($token)->getJson($this->url($event, '/tickets'))->assertOk()
            ->assertJsonPath('data.tickets', [])->assertHeader('Cache-Control', 'no-store, private');

        $paymentUrl = $this->book($event, $token, 2)->assertOk()->assertJsonPath('data.status', 'pending')->json('data.payment_url');
        $this->assertStringContainsString('/payment/redirect/', $paymentUrl);
        // A double tap of the same order reuses its payment.
        $this->book($event, $token, 2)->assertOk()->assertJsonPath('data.payment_url', $paymentUrl);
        $this->assertSame(1, Payment::withoutTenantScope()->count());
        $payment = Payment::withoutTenantScope()->firstOrFail();
        $this->assertSame(5000, (int) $payment->amount_minor);
        $this->assertTrue($payment->metadata['web_booking']);

        // Gateway → /payment/success → back on the booking site.
        $success = $this->post($this->fakeCompleteUrl($paymentUrl), ['result' => 'success'])->assertRedirect()->headers->get('Location');
        $this->get($success)->assertRedirect(self::SITE."/e/{$event->id}?lang=en&payment=success");

        $tickets = $this->withToken($token)->getJson($this->url($event, '/tickets').'?lang=en')->assertOk()
            ->assertJsonCount(2, 'data.tickets')
            ->assertJsonPath('data.tickets.0.status', 'active')
            ->assertJsonPath('data.phone_masked', "\u{2066}•••• 2233\u{2069}")
            ->json('data.tickets');
        $this->assertNotEmpty($tickets[0]['qr_code']);
        $this->assertNotEmpty($tickets[0]['when_label']);

        // Another visitor's or another event's tickets are not listed.
        $otherEvent = $this->makeEvent();
        $this->withToken($token)->getJson($this->url($otherEvent, '/tickets'))->assertOk()->assertJsonPath('data.tickets', []);
    }

    #[Test]
    public function a_failed_payment_returns_to_the_booking_site_with_a_notice_and_no_tickets(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.000']);
        $paymentUrl = $this->book($event, $this->signIn($event), 1)->json('data.payment_url');

        $cancel = $this->post($this->fakeCompleteUrl($paymentUrl), ['result' => 'failure'])->assertRedirect()->headers->get('Location');
        $this->get($cancel)->assertRedirect(self::SITE."/e/{$event->id}?lang=en&payment=failed");
        $this->assertSame(0, Ticket::withoutTenantScope()->count());
    }

    #[Test]
    public function free_tickets_are_issued_at_once(): void
    {
        $event = $this->makeEvent(null, ['price' => '0.000']);
        $token = $this->signIn($event);

        $this->book($event, $token, 3)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->withToken($token)->getJson($this->url($event, '/tickets'))->assertJsonCount(3, 'data.tickets');
    }

    #[Test]
    public function wrong_codes_numbers_and_orders_come_back_with_a_code(): void
    {
        $event = $this->makeEvent(null, ['total_tickets' => 1, 'available_tickets' => 1]);
        $this->postJson($this->url($event, '/otp'), ['phone_number' => '1234'])->assertStatus(422)->assertJsonPath('code', 'INVALID_PHONE');

        $this->postJson($this->url($event, '/otp'), ['phone_number' => '+968 9911 2233'])->assertOk();
        $this->postJson($this->url($event, '/verify'), ['phone_number' => self::PHONE, 'otp' => '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID')->assertJsonPath('data.attempts_remaining', 4);
        $this->postJson($this->url($event, '/verify'), ['phone_number' => '96899998888', 'otp' => '123456'])
            ->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
        $token = $this->postJson($this->url($event, '/verify'), ['phone_number' => '99112233', 'otp' => '123456'])->assertOk()->json('data.token');

        $this->book($event, $token, 0)->assertStatus(422)->assertJsonPath('code', 'ITEMS_REQUIRED');
        $this->book($event, $token, 2)->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.available', 1);
        $this->book($event, $token, 1, ['booking_date' => '2000-01-01'])->assertStatus(422)->assertJsonPath('code', 'DATE_INVALID');
        $this->assertSame(0, Payment::withoutTenantScope()->count());

        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url($event, '/tickets'))->assertUnauthorized();
        $this->postJson($this->url($event, '/checkout'), [])->assertUnauthorized();
    }

    #[Test]
    public function the_token_lasts_thirty_days_and_sign_out_revokes_it(): void
    {
        $event = $this->makeEvent();
        $verify = $this->postJson($this->url($event, '/otp'), ['phone_number' => '99112233'])->assertOk();
        $verify = $this->postJson($this->url($event, '/verify'), ['phone_number' => self::PHONE, 'otp' => '123456'])->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), strtotime($verify->json('data.expires_at')), 120);
        $token = $verify->json('data.token');

        $this->withToken($token)->postJson($this->url($event, '/logout'))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson($this->url($event, '/tickets'))->assertUnauthorized();
    }

    #[Test]
    public function with_the_booking_site_configured_the_qr_and_the_old_page_point_there(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(self::SITE."/e/{$event->id}", VisitorBookingController::publicUrl($event));
        $this->get("/e/{$event->id}?lang=en&payment=success&x=1")->assertRedirect(self::SITE."/e/{$event->id}?lang=en&payment=success");

        config(['booking.web_url' => '']);
        $this->assertStringEndsWith("/e/{$event->id}", VisitorBookingController::publicUrl($event));
        $this->assertStringStartsNotWith(self::SITE, VisitorBookingController::publicUrl($event));
        $this->get("/e/{$event->id}")->assertOk();
    }
}
