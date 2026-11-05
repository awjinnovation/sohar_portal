<?php

namespace Tests\Feature\Platform;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Event booking page /e/{event} (Web\VisitorBookingController). */
class VisitorBookingTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private const PHONE = '96899112233';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['auth.otp.test_code' => '123456']);
    }

    private function page(Event $event, array $query = []): string
    {
        return route('visitor.event', ['event' => $event->id, 'lang' => 'en'] + $query);
    }

    private function signIn(Event $event, string $typed = '9911 2233')
    {
        $this->post("/e/{$event->id}/otp", ['phone_number' => $typed, 'lang' => 'en'])
            ->assertRedirect($this->page($event))->assertSessionHas('visitor_notice', 'otp_sent');

        return $this->post("/e/{$event->id}/verify", ['phone_number' => self::PHONE, 'otp' => '123456', 'lang' => 'en'])
            ->assertRedirect($this->page($event))->assertSessionHas('visitor_notice', 'signed_in');
    }

    private function general(Event $event): EventTicketType
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $event->id)->where('key', 'general')->firstOrFail();
    }

    private function book(Event $event, int $quantity, array $extra = [])
    {
        return $this->post("/e/{$event->id}/checkout", array_merge([
            'lang' => 'en', 'form_key' => 'form-1', 'booking_date' => TenantTime::today($this->tenant),
            'items' => [$this->general($event)->id => $quantity],
        ], $extra));
    }

    private function fakeCompleteUrl(string $paymentUrl): string
    {
        $page = $this->get($paymentUrl)->assertOk();
        preg_match('/action="([^"]+)"/', $page->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    #[Test]
    public function the_page_asks_for_a_phone_and_only_opens_for_active_events_with_web_booking_on(): void
    {
        $event = $this->makeEvent();

        $this->get($this->page($event))->assertOk()
            ->assertSee('Concert')->assertSee('data-visitor-step="phone"', false)->assertDontSee('id="vbBook"', false)
            ->assertHeader('X-Robots-Tag', 'noindex');

        $event->update(['is_active' => false]);
        $this->get($this->page($event))->assertNotFound();

        $event->update(['is_active' => true]);
        $this->tenant->putSetting('modules.web_booking', false)->save();
        $this->get($this->page($event))->assertNotFound();
    }

    #[Test]
    public function a_visitor_signs_in_pays_by_card_and_finds_the_tickets_here_and_in_the_app(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $this->signIn($event);
        $this->assertTrue(Auth::guard('visitor')->check());
        $this->assertSame(self::PHONE, Auth::guard('visitor')->user()->phone_number);

        $this->get($this->page($event))->assertOk()->assertSee('id="vbBook"', false)->assertSee('•••• 2233');

        $gatewayUrl = $this->book($event, 2)->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/payment/redirect/', $gatewayUrl);
        // A double tap of the same order reuses its payment.
        $this->book($event, 2)->assertRedirect();
        $this->assertSame(1, Payment::withoutTenantScope()->count());

        $payment = Payment::withoutTenantScope()->firstOrFail();
        $this->assertSame(5000, (int) $payment->amount_minor);
        $this->assertTrue($payment->metadata['web_booking']);
        $this->assertSame(self::PHONE, User::find($payment->user_id)->phone_number);

        // Gateway → /payment/success → back to the event page with the tickets.
        $success = $this->post($this->fakeCompleteUrl($gatewayUrl), ['result' => 'success'])->assertRedirect()->headers->get('Location');
        $this->get($success)->assertRedirect($this->page($event, ['payment' => 'success']));
        $this->get($this->page($event, ['payment' => 'success']))->assertOk()
            ->assertSee('Payment complete')->assertSee('data-visitor-tickets="2"', false)->assertSee('data-save-ticket', false);
        $this->assertSame(2, Ticket::withoutTenantScope()->where('user_id', $payment->user_id)->where('status', 'active')->count());

        // The same number signs in to the app: same account, so the tickets are there.
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => self::PHONE], $this->tenantHeaders())->assertOk();
        $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => self::PHONE, 'otp' => '123456'], $this->tenantHeaders())
            ->assertOk()->assertJsonPath('user.id', $payment->user_id);
    }

    #[Test]
    public function a_failed_payment_returns_to_the_page_with_a_notice(): void
    {
        $event = $this->makeEvent(null, ['price' => '1.000']);
        $this->signIn($event);
        $gatewayUrl = $this->book($event, 1)->headers->get('Location');

        $cancel = $this->post($this->fakeCompleteUrl($gatewayUrl), ['result' => 'failure'])->assertRedirect()->headers->get('Location');
        $this->get($cancel)->assertRedirect($this->page($event, ['payment' => 'failed']));
        $this->get($this->page($event, ['payment' => 'failed']))->assertOk()->assertSee('nothing was charged')->assertSee('id="vbBook"', false);
        $this->assertSame(0, Ticket::withoutTenantScope()->count());
    }

    #[Test]
    public function free_tickets_are_issued_at_once(): void
    {
        $event = $this->makeEvent(null, ['price' => '0.000']);
        $this->signIn($event);

        $this->book($event, 3)->assertRedirect($this->page($event, ['payment' => 'success']));
        $this->assertSame(3, Ticket::withoutTenantScope()->where('event_id', $event->id)->count());
    }

    #[Test]
    public function the_web_sign_in_lasts_thirty_days_and_never_opens_the_admin_panel(): void
    {
        $this->seedRoles();
        $admin = $this->makeUser(self::PHONE);
        $this->grantRole($admin, 'admin');
        $event = $this->makeEvent();

        $verify = $this->signIn($event);
        $cookie = collect($verify->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_visitor_'));
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 120);
        $this->assertSame($admin->id, Auth::guard('visitor')->id());

        $this->assertFalse(Auth::guard('web')->check());
        $this->get('/admin/dashboard')->assertRedirect();
        $this->assertFalse(Auth::guard('web')->check());

        $this->post("/e/{$event->id}/logout", ['lang' => 'en'])->assertRedirect($this->page($event));
        $this->assertFalse(Auth::guard('visitor')->check());
        $this->get($this->page($event))->assertSee('data-visitor-step="phone"', false);
    }

    #[Test]
    public function the_code_only_works_for_the_number_it_was_sent_to_and_wrong_codes_are_counted(): void
    {
        $event = $this->makeEvent();
        $this->post("/e/{$event->id}/otp", ['phone_number' => '9911 2233', 'lang' => 'en'])->assertRedirect();
        $this->get($this->page($event))->assertSee('data-visitor-step="otp"', false)->assertSee('•••• 2233');

        $this->post("/e/{$event->id}/verify", ['phone_number' => self::PHONE, 'otp' => '000000', 'lang' => 'en'])
            ->assertSessionHas('visitor_error', 'otp_invalid')->assertSessionHas('visitor_error_data', ['remaining' => 4]);
        $this->get($this->page($event))->assertSee('Attempts left: 4');

        // Another number in the form than the one in the session is refused, even with the right code.
        $this->post("/e/{$event->id}/verify", ['phone_number' => '96899998888', 'otp' => '123456', 'lang' => 'en'])
            ->assertSessionHas('visitor_error', 'otp_expired');
        $this->assertFalse(Auth::guard('visitor')->check());
        $this->get($this->page($event))->assertSee('data-visitor-step="phone"', false);
    }

    #[Test]
    public function the_page_and_the_app_share_the_per_phone_otp_limit(): void
    {
        $event = $this->makeEvent();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => self::PHONE], $this->tenantHeaders())->assertOk();

        $this->post("/e/{$event->id}/otp", ['phone_number' => '9911 2233', 'lang' => 'en'])
            ->assertRedirect($this->page($event))->assertSessionHas('visitor_error', 'throttled');
        $this->get($this->page($event))->assertSee('Too many attempts');
    }

    #[Test]
    public function invalid_numbers_and_orders_come_back_with_a_message(): void
    {
        $event = $this->makeEvent(null, ['total_tickets' => 1, 'available_tickets' => 1]);
        $this->post("/e/{$event->id}/otp", ['phone_number' => '1234', 'lang' => 'en'])->assertSessionHas('visitor_error', 'invalid_phone');

        $this->signIn($event);
        $this->book($event, 0)->assertSessionHas('visitor_error', 'items_required');
        $this->book($event, 2)->assertSessionHas('visitor_error', 'sold_out');
        $this->get($this->page($event))->assertSee('Not enough tickets are left')->assertSee('value="2"', false);
        $this->book($event, 1, ['booking_date' => '2000-01-01'])->assertSessionHas('visitor_error', 'date_invalid');
        $this->assertSame(0, Payment::withoutTenantScope()->count());
    }

    #[Test]
    public function admins_print_the_booking_qr_of_an_event(): void
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'admin');
        $event = $this->makeEvent();
        $url = \App\Http\Controllers\Web\VisitorBookingController::publicUrl($event);
        $this->assertStringEndsWith("/e/{$event->id}", $url);

        $this->actingAs($admin)->get(route('admin.events.show', $event))->assertOk()->assertSee('data-booking-qr', false)->assertSee($url);
        $this->actingAs($admin)->get(route('admin.events.booking-qr', $event))->assertOk()->assertSee('Scan to book your ticket')->assertSee($url);
        $this->actingAs($admin)->get(route('admin.events.booking-qr', ['event' => $event, 'format' => 'svg']))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
}
