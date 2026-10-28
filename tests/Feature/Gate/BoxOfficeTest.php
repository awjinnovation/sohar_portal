<?php

namespace Tests\Feature\Gate;

use App\Jobs\SendOtpSms;
use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Gate;
use App\Models\GateScan;
use App\Models\Payment;
use App\Models\PointsTransaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/** Gate box office (docs/gate_sales_contract.md + ticket types). */
class BoxOfficeTest extends GateTestCase
{
    private const BUYER = '96899112233';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['auth.otp.test_code' => '123456']);
        $this->tenant->putSetting('gate_sales.enabled', true)->save();
    }

    /** @return array{0: \App\Models\GateDevice, 1: string, 2: Gate} */
    private function sellingDevice(array $gateAttrs = [], ?string $staffPhone = '96890000001', string $role = 'staff'): array
    {
        $gate = $this->makeGate(['can_sell' => true] + $gateAttrs);
        [$device, $token] = $this->pairDevice($gate);
        if ($staffPhone) {
            $this->staffLogin($token, $staffPhone, $role);
        }

        return [$device, $token, $gate];
    }

    private function staffLogin(string $token, string $phone, ?string $role = 'staff'): User
    {
        $user = User::where('phone_number', $phone)->first() ?? $this->makeUser($phone);
        if ($role) {
            $this->grantRole($user, $role);
        }
        $this->travel(3)->minutes(); // send-otp: 1 per 2 minutes per phone
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => $phone], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => $phone, 'otp' => '123456'], $token)->assertOk();

        return $user;
    }

    private function todayEvent(array $attrs = []): Event
    {
        return $this->makeEvent(null, array_merge(['price' => '1.000', 'total_tickets' => 100], $attrs));
    }

    private function addType(Event $event, array $attrs): EventTicketType
    {
        return $this->inTenant($this->tenant, fn () => EventTicketType::create(array_merge([
            'event_id' => $event->id, 'name' => ['en' => Str::headline($attrs['key']), 'ar' => 'نوع'], 'category' => 'other',
            'price_minor' => 1000, 'admits' => 1, 'per_order_max' => 10, 'visibility' => 'public', 'is_active' => true, 'sort' => 5,
        ], $attrs)));
    }

    private function general(Event $event): EventTicketType
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $event->id)->where('key', 'general')->firstOrFail();
    }

    private function order(string $token, Event $event, array $extra = [])
    {
        return $this->gate('POST', '/api/v1/gate/sales/orders', array_merge([
            'event_id' => $event->id, 'quantity' => 2, 'buyer_phone' => '99112233', 'buyer_name' => 'Salim',
            'locale' => 'ar', 'send_sms' => false, 'idempotency_key' => (string) Str::uuid(),
        ], $extra), $token);
    }

    private function fakeCompleteUrl(string $paymentPage): string
    {
        $page = $this->get($paymentPage)->assertOk();
        preg_match('/action="([^"]+)"/', $page->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    /** Visitor journey: /pay page → checkout → fake gateway success. Returns the final redirect target. */
    private function payOnline(string $paymentUrl, string $result = 'success')
    {
        $path = parse_url($paymentUrl, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('data-pay-button', false);
        $checkout = $this->post($path.'/checkout', ['lang' => 'en'])->assertRedirect();
        $gatewayUrl = $checkout->headers->get('Location');
        $this->assertStringContainsString('/payment/redirect/', $gatewayUrl);

        return $this->post($this->fakeCompleteUrl($gatewayUrl), ['result' => $result]);
    }

    #[Test]
    public function selling_needs_sales_enabled_a_selling_gate_and_a_staff_member_with_the_permission(): void
    {
        $this->tenant->putSetting('gate_sales.enabled', false)->save();
        $gate = $this->makeGate();
        [, $token] = $this->pairDevice($gate);

        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertForbidden()->assertJsonPath('code', 'SALES_DISABLED');
        $this->gate('GET', '/api/v1/gate/session', [], $token)
            ->assertJsonPath('data.settings.sales.enabled', false)
            ->assertJsonPath('data.settings.sales.link_ttl_minutes', 30)
            ->assertJsonPath('data.settings.sales.max_quantity', 10)
            ->assertJsonPath('data.settings.sales.available', false);

        $this->tenant->putSetting('gate_sales.enabled', true)->save();
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertForbidden()->assertJsonPath('code', 'GATE_CANNOT_SELL');

        $gate->update(['can_sell' => true]);
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertForbidden()
            ->assertJsonPath('code', 'STAFF_LOGIN_REQUIRED')->assertJsonPath('data.reason', 'no_staff');

        // Gate staff of the vendor kind may scan but not sell.
        $cashier = $this->makeUser('96890000002');
        $this->grantRole($cashier, 'vendor-staff');
        $this->inTenant($this->tenant, fn () => $cashier->givePermissionTo('validate tickets'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->staffLogin($token, '96890000002', null);
        $this->gate('POST', '/api/v1/gate/sales/quote', ['event_id' => 1, 'quantity' => 1], $token)->assertForbidden()
            ->assertJsonPath('code', 'STAFF_LOGIN_REQUIRED')->assertJsonPath('data.reason', 'no_permission');
        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertJsonPath('data.staff.can_sell', false);

        $this->staffLogin($token, '96890000001', 'staff');
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertOk();
        $this->gate('GET', '/api/v1/gate/session', [], $token)
            ->assertJsonPath('data.staff.can_sell', true)
            ->assertJsonPath('data.gate.can_sell', true)
            ->assertJsonPath('data.settings.sales.available', true);

        // Tenant admins can sell too.
        $this->staffLogin($token, '96890000009', 'admin');
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertOk();

        $this->tenant->putSetting('modules.staff_scanner', false)->save();
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertForbidden()->assertJsonPath('code', 'MODULE_DISABLED');
    }

    #[Test]
    public function catalog_lists_todays_events_with_the_types_this_gate_may_sell(): void
    {
        $today = $this->todayEvent(['title' => 'Concert']);
        $this->addType($today, ['key' => 'vip', 'price_minor' => 5000, 'category' => 'vip']);
        $this->addType($today, ['key' => 'door', 'price_minor' => 1500, 'visibility' => 'box_office']);
        $this->addType($today, ['key' => 'old', 'is_active' => false]);
        $this->makeEvent(null, ['title' => 'Next month', 'start_time' => now()->addMonth(), 'end_time' => now()->addMonth()->addDay()]);
        [, $token] = $this->sellingDevice();

        $res = $this->gate('GET', '/api/v1/gate/sales/catalog', [], $token)->assertOk();
        $this->assertSame(\App\Services\Gate\GateDay::today(), $res->json('data.date'));
        $this->assertSame(['Concert'], array_column($res->json('data.events'), 'title_text'));
        $types = collect($res->json('data.events.0.ticket_types'))->keyBy('key');
        $this->assertSame(['general', 'vip', 'door'], $types->keys()->all()); // box_office types included, inactive not
        $this->assertSame(1000, $types['general']['unit_price_minor']);
        $this->assertSame('5.000', $types['vip']['unit_price']);
        $this->assertSame(100, $types['general']['available']);
        $this->assertSame(100, $res->json('data.events.0.available'));

        // A VIP-only gate sells only VIP.
        [, $vipToken] = $this->sellingDevice(['ticket_types' => ['vip']], '96890000003');
        $vip = $this->gate('GET', '/api/v1/gate/sales/catalog', [], $vipToken)->assertOk();
        $this->assertSame(['vip'], array_column($vip->json('data.events.0.ticket_types'), 'key'));

        // Future dates only with allow_future_dates.
        $this->gate('GET', '/api/v1/gate/sales/catalog?date='.now()->addDays(2)->toDateString(), [], $token)->assertStatus(422)->assertJsonValidationErrors('date');
        $this->tenant->putSetting('gate_sales.allow_future_dates', true)->save();
        $this->gate('GET', '/api/v1/gate/sales/catalog?date='.now()->addDays(2)->toDateString(), [], $token)->assertOk()
            ->assertJsonPath('data.date', now()->addDays(2)->toDateString());
    }

    #[Test]
    public function quote_and_order_are_priced_on_the_server_from_items(): void
    {
        Queue::fake();
        $event = $this->todayEvent();
        $family = $this->addType($event, ['key' => 'family', 'price_minor' => 3500, 'admits' => 4, 'category' => 'family']);
        $general = $this->general($event);
        [, $token] = $this->sellingDevice();
        $items = [['ticket_type_id' => $general->id, 'quantity' => 2], ['ticket_type_id' => $family->id, 'quantity' => 1]];

        $this->gate('POST', '/api/v1/gate/sales/quote', ['event_id' => $event->id, 'items' => $items, 'total_minor' => 1, 'unit_price_minor' => 1], $token)
            ->assertOk()
            ->assertJsonPath('data.total_minor', 5500)
            ->assertJsonPath('data.total', '5.500')
            ->assertJsonPath('data.currency', 'OMR')
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.unit_price_minor', null)
            ->assertJsonPath('data.lines.0.line_total_minor', 2000)
            ->assertJsonPath('data.lines.1.name_text', 'Family')
            ->assertJsonPath('data.lines.1.admits', 4)
            ->assertJsonPath('data.available', 100);

        $res = $this->order($token, $event, ['items' => $items, 'quantity' => null, 'send_sms' => true, 'total_minor' => 10, 'amount' => '0.010'])
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.total_minor', 5500)
            ->assertJsonPath('data.order.quantity', 3)
            ->assertJsonPath('data.order.ticket_type', null)
            ->assertJsonPath('data.order.buyer_phone_masked', '+968 •••• 2233')
            ->assertJsonPath('data.order.sms_sent', true)
            ->assertJsonPath('data.order.sms_count', 1)
            ->assertJsonPath('data.order.seconds_left', 1800)
            ->assertJsonPath('data.order.tickets', []);
        $order = $res->json('data.order');
        $this->assertCount(2, $order['lines']);
        $this->assertMatchesRegularExpression('#^http://localhost/pay/[A-Za-z0-9]{32}$#', $order['payment_url']);
        $this->assertSame($order['payment_url'], $order['qr_payload']);
        $this->assertMatchesRegularExpression('/^BO-\d{6}-0001$/', $order['reference']);

        $model = BoxOfficeOrder::findOrFail($order['id']);
        $this->assertSame(self::BUYER, $model->buyer_phone);
        $this->assertSame('Salim', User::where('phone_number', self::BUYER)->value('name'));

        Queue::assertPushed(SendOtpSms::class, function (SendOtpSms $job) use ($order) {
            return $job->phone === self::BUYER && $job->purpose === 'box_office'
                && str_contains($job->message, 'طلب 3 تذاكر لفعالية «حفل» بقيمة OMR 5.500')
                && str_contains($job->message, 'خلال 30 دقيقة: '.$order['payment_url']);
        });

        // Legacy single type + quantity → the default (General) type; Arabic dual in the SMS.
        $this->order($token, $event, ['quantity' => 2, 'ticket_type' => 'standard', 'send_sms' => true])->assertCreated()
            ->assertJsonPath('data.order.ticket_type', 'general')
            ->assertJsonPath('data.order.total_minor', 2000)
            ->assertJsonPath('data.order.lines.0.quantity', 2);
        Queue::assertPushed(SendOtpSms::class, fn (SendOtpSms $job) => str_contains($job->message, 'طلب تذكرتين لفعالية'));

        // English SMS.
        $this->order($token, $event, ['quantity' => 1, 'locale' => 'en', 'send_sms' => true])->assertCreated();
        Queue::assertPushed(SendOtpSms::class, fn (SendOtpSms $job) => str_contains($job->message, 'order of 1 ticket for Concert, total OMR 1.000. Pay within 30 minutes: http://localhost/pay/'));
    }

    #[Test]
    public function order_input_errors_use_the_contract_codes(): void
    {
        $event = $this->todayEvent();
        $vip = $this->addType($event, ['key' => 'vip', 'per_order_max' => 2]);
        $closed = $this->addType($event, ['key' => 'early', 'sales_end' => now()->subDay()]);
        [, $token] = $this->sellingDevice();

        $this->order($token, $event, ['buyer_phone' => '1234'])->assertStatus(422)->assertJsonPath('code', 'INVALID_PHONE');
        $this->order($token, $event, ['buyer_phone' => '24123456'])->assertStatus(422)->assertJsonPath('code', 'INVALID_PHONE'); // landline
        $this->tenant->putSetting('gate_sales.max_quantity', 4)->save();
        $this->order($token, $event, ['items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 3], ['ticket_type_id' => $vip->id, 'quantity' => 2]]])
            ->assertStatus(422)->assertJsonPath('code', 'PER_ORDER_LIMIT')->assertJsonPath('data.ticket_type_id', null)->assertJsonPath('data.per_order_max', 4);
        $this->tenant->putSetting('gate_sales.max_quantity', 10)->save();
        $this->order($token, $event, ['items' => [['ticket_type_id' => $vip->id, 'quantity' => 3]]])->assertStatus(422)
            ->assertJsonPath('code', 'PER_ORDER_LIMIT')->assertJsonPath('data.ticket_type_id', $vip->id);
        $this->order($token, $event, ['items' => [['ticket_type_id' => $closed->id, 'quantity' => 1]]])->assertStatus(422)->assertJsonPath('code', 'SALES_CLOSED');
        $this->order($token, $event, ['items' => [['ticket_type_id' => 999999, 'quantity' => 1]]])->assertStatus(422)->assertJsonPath('code', 'TYPE_NOT_AVAILABLE');
        $other = $this->makeEvent(null, ['start_time' => now()->addMonth(), 'end_time' => now()->addMonth()->addDay()]);
        $this->order($token, $other)->assertNotFound()->assertJsonPath('code', 'EVENT_NOT_FOUND');
        $this->order($token, $event, ['booking_date' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('booking_date');

        // Accepted formats of the phone number.
        foreach (['+968 9911 2233', '0096899112233', '96899112233', '9911-2233'] as $phone) {
            $this->order($token, $event, ['buyer_phone' => $phone, 'quantity' => 1])->assertCreated();
        }
        $this->assertSame(4, BoxOfficeOrder::where('buyer_phone', self::BUYER)->count());
        $this->assertSame(0, BoxOfficeOrder::where('buyer_phone', '!=', self::BUYER)->count());
    }

    #[Test]
    public function pending_orders_hold_seats_until_they_expire_or_are_cancelled(): void
    {
        // Reads the public availability endpoint after every change; its
        // shared cache (up to 20 s, docs/caching.md) is not under test here.
        config(['api_cache.enabled' => false]);
        $event = $this->todayEvent(['total_tickets' => 3]);
        [, $token] = $this->sellingDevice();
        $available = fn () => $this->getJson("/api/v1/events/{$event->id}/availability?date=".\App\Services\Gate\GateDay::today(), $this->tenantHeaders())->json('data.available_tickets');

        $first = $this->order($token, $event, ['quantity' => 2])->assertCreated()->json('data.order');
        $this->assertSame(1, $available());
        $this->order($token, $event, ['quantity' => 2])->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT')->assertJsonPath('data.available', 1);
        // The app's checkout sees the hold too.
        $this->api('POST', '/api/v1/payments/initialize', ['event_id' => $event->id, 'booking_date' => \App\Services\Gate\GateDay::today(), 'quantity' => 2], $this->makeUser())
            ->assertStatus(422)->assertJsonPath('code', 'SOLD_OUT');

        $this->gate('POST', "/api/v1/gate/sales/orders/{$first['id']}/cancel", [], $token)->assertOk()->assertJsonPath('data.order.status', 'cancelled');
        $this->assertSame(3, $available());
        $this->gate('POST', "/api/v1/gate/sales/orders/{$first['id']}/cancel", [], $token)->assertStatus(409)->assertJsonPath('code', 'ORDER_NOT_PENDING');

        // The visitor opened the gateway, then the link expired: seats and the payment are released.
        $second = $this->order($token, $event, ['quantity' => 3])->assertCreated()->json('data.order');
        $this->assertSame(0, $available());
        $path = parse_url($second['payment_url'], PHP_URL_PATH);
        $this->post($path.'/checkout')->assertRedirect();
        $payment = Payment::where('metadata->box_office_order_id', $second['id'])->sole();
        $this->assertSame('pending', $payment->status);

        $this->travel(31)->minutes();
        $this->artisan('box-office:expire')->expectsOutputToContain('Expired 1 box office orders.')->assertExitCode(0);
        $this->assertSame('expired', BoxOfficeOrder::find($second['id'])->status);
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(3, $available());
        $this->gate('POST', "/api/v1/gate/sales/orders/{$second['id']}/sms", [], $token)->assertStatus(410)->assertJsonPath('code', 'ORDER_EXPIRED');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$second['id']}/cancel", [], $token)->assertStatus(410)->assertJsonPath('code', 'ORDER_EXPIRED');
    }

    #[Test]
    public function an_order_is_expired_lazily_when_read_before_the_scheduler_runs(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $order = $this->order($token, $event)->json('data.order');

        $this->travel(29)->minutes();
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.seconds_left', 60);
        $this->travel(2)->minutes();
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertJsonPath('data.order.status', 'expired')
            ->assertJsonPath('data.order.seconds_left', 0);
    }

    #[Test]
    public function creating_an_order_is_idempotent_per_device_and_key(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();

        $a = $this->order($token, $event, ['idempotency_key' => 'k-1'])->assertCreated()->json('data.order');
        $b = $this->order($token, $event, ['idempotency_key' => 'k-1'])->assertOk()->json('data.order');
        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(1, BoxOfficeOrder::count());
        $this->order($token, $event, ['idempotency_key' => 'k-1', 'buyer_phone' => '99887766'])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        // Another device may use the same key.
        [, $other] = $this->sellingDevice([], '96890000005');
        $this->order($other, $event, ['idempotency_key' => 'k-1'])->assertCreated();
        $this->assertSame(2, BoxOfficeOrder::count());
    }

    #[Test]
    public function the_visitor_pays_through_the_link_and_gets_the_tickets_once(): void
    {
        $event = $this->todayEvent(['price' => '2.000']);
        $family = $this->addType($event, ['key' => 'family', 'price_minor' => 5000, 'admits' => 4, 'category' => 'family']);
        [$device, $token] = $this->sellingDevice();
        $order = $this->order($token, $event, ['items' => [
            ['ticket_type_id' => $this->general($event)->id, 'quantity' => 1], ['ticket_type_id' => $family->id, 'quantity' => 1],
        ], 'quantity' => null])->assertCreated()->json('data.order');

        $path = parse_url($order['payment_url'], PHP_URL_PATH);
        $this->get($path)->assertOk()
            ->assertSee('data-order-status="pending"', false)
            ->assertSee('+968 •••• 2233')
            ->assertSee('7.000')
            ->assertSee('dir="rtl"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($path.'?lang=en')->assertOk()->assertSee('Pay for your tickets')->assertSee('This link expires in');

        $done = $this->payOnline($order['payment_url'])->assertRedirect();
        $this->followRedirects($done)->assertOk()
            ->assertSee('data-order-status="paid"', false)
            ->assertSee('data-tickets-issued="2"', false)
            ->assertSee('Payment complete');

        $model = BoxOfficeOrder::findOrFail($order['id']);
        $this->assertSame('paid', $model->status);
        $buyer = User::where('phone_number', self::BUYER)->firstOrFail();
        $tickets = Ticket::where('user_id', $buyer->id)->orderBy('id')->get();
        $this->assertSame(['general', 'family'], $tickets->pluck('ticket_type')->all());
        $this->assertSame([1, 4], $tickets->map->admits()->all());
        $this->assertSame([2000, 5000], $tickets->map(fn ($t) => $t->tenant->toMinor($t->price))->all());
        $this->assertSame(70, (int) PointsTransaction::where('type', 'earn')->sum('points')); // 7.000 OMR × 10
        $payment = Payment::findOrFail($model->payment_id);
        $this->assertSame($buyer->id, $payment->user_id);

        // A replayed gateway callback does not issue twice.
        app(\App\Services\Payments\PaymentFulfillmentService::class)->complete($payment->fresh(), ['order_status' => 'Success'], 'fake');
        $this->assertSame(2, Ticket::where('user_id', $buyer->id)->count());
        $this->assertSame(70, (int) PointsTransaction::where('type', 'earn')->sum('points'));
        $this->get('/payment/success?transaction_id='.$payment->transaction_id)->assertRedirect(route('box-office.pay', ['token' => $model->public_token, 'lang' => 'en']));

        // The gate sees the paid order with its tickets.
        $this->gate('GET', "/api/v1/gate/sales/orders/{$order['id']}", [], $token)->assertOk()
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonCount(2, 'data.order.tickets')
            ->assertJsonPath('data.order.tickets.1.ticket_type.key', 'family')
            ->assertJsonPath('data.order.tickets.1.admits', 4);
        $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/cancel", [], $token)->assertStatus(409)->assertJsonPath('code', 'ORDER_NOT_PENDING');

        // The buyer signs in with the same phone and finds the tickets.
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => self::BUYER], $this->tenantHeaders())->assertOk();
        $login = $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => self::BUYER, 'otp' => '123456'], $this->tenantHeaders())->assertOk();
        $this->assertSame($buyer->id, $login->json('user.id'));
        $mine = $this->getJson('/api/v1/tickets/my-tickets', $this->tenantHeaders(null, ['Authorization' => 'Bearer '.$login->json('token')]))->assertOk();
        $this->assertEqualsCanonicalizing($tickets->pluck('qr_code')->all(), array_column($mine->json('data'), 'qr_code'));
        $this->assertSame('family', collect($mine->json('data'))->firstWhere('qr_code', $tickets[1]->qr_code)['ticket_type']['key']);

        // Paid page keeps working (and the link cannot start another payment).
        $this->post($path.'/checkout')->assertRedirect();
        $this->assertSame(1, Payment::where('metadata->box_office_order_id', $order['id'])->count());
        $this->assertNotNull($device);
    }

    #[Test]
    public function a_failed_payment_returns_to_the_link_which_can_be_paid_again(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $order = $this->order($token, $event)->json('data.order');

        $back = $this->payOnline($order['payment_url'], 'failure');
        $this->followRedirects($back)->assertOk()->assertSee('data-notice="failed"', false)->assertSee('data-order-status="pending"', false);
        $this->assertSame('pending', BoxOfficeOrder::find($order['id'])->status);

        $this->payOnline($order['payment_url'])->assertRedirect();
        $this->assertSame('paid', BoxOfficeOrder::find($order['id'])->status);
        $this->assertSame(2, Payment::where('metadata->box_office_order_id', $order['id'])->count());
        $this->assertSame(2, Ticket::count());
    }

    #[Test]
    public function expired_and_cancelled_links_show_their_own_pages(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $expired = $this->order($token, $event)->json('data.order');
        $cancelled = $this->order($token, $event)->json('data.order');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$cancelled['id']}/cancel", [], $token)->assertOk();

        $this->get(parse_url($cancelled['payment_url'], PHP_URL_PATH))->assertOk()->assertSee('data-order-status="cancelled"', false)->assertSee('أُلغي هذا الطلب');

        $this->travel(31)->minutes();
        $path = parse_url($expired['payment_url'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('data-order-status="expired"', false)->assertSee('انتهت صلاحية رابط الدفع');
        $this->get($path.'?lang=en')->assertOk()->assertSee('This payment link has expired');
        $this->post($path.'/checkout')->assertRedirect(route('box-office.pay', ['token' => basename($path), 'lang' => 'ar']));
        $this->assertSame(0, Payment::count());

        $this->get('/pay/'.str_repeat('a', 32))->assertNotFound();
    }

    #[Test]
    public function sms_can_be_resent_three_times_per_order_thirty_seconds_apart(): void
    {
        Queue::fake();
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $order = $this->order($token, $event, ['send_sms' => true])->json('data.order');
        $sms = fn () => $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/sms", [], $token);

        $sms()->assertStatus(429)->assertJsonPath('code', 'SMS_LIMIT')->assertJsonPath('data.retry_after', 30);
        $this->travel(31)->seconds();
        $sms()->assertOk()->assertJsonPath('data.order.sms_count', 2)->assertJsonPath('data.order.sms_retry_after', 30);
        $this->travel(31)->seconds();
        $sms()->assertOk()->assertJsonPath('data.order.sms_count', 3)->assertJsonPath('data.order.sms_retry_after', null);
        $this->travel(31)->seconds();
        $sms()->assertStatus(429)->assertJsonPath('code', 'SMS_LIMIT')->assertJsonPath('data.max', 3);
        $this->assertSame(3, Queue::pushed(SendOtpSms::class, fn (SendOtpSms $j) => $j->purpose === 'box_office')->count());

        // Tenant default send_sms applies when the field is omitted.
        $this->tenant->putSetting('gate_sales.send_sms', false)->save();
        $this->gate('POST', '/api/v1/gate/sales/orders', ['event_id' => $event->id, 'quantity' => 1, 'buyer_phone' => '99112233', 'idempotency_key' => 'x'], $token)
            ->assertCreated()->assertJsonPath('data.order.sms_count', 0);
    }

    #[Test]
    public function admit_lets_the_paid_tickets_in_through_the_scan_service(): void
    {
        $event = $this->todayEvent(['price' => '0.000']);
        $family = $this->addType($event, ['key' => 'family', 'price_minor' => 0, 'admits' => 4, 'category' => 'family', 'requires_id' => true]);
        [, $token] = $this->sellingDevice();

        // Free tickets only: paid right away, no link to pay.
        $order = $this->order($token, $event, ['items' => [
            ['ticket_type_id' => $this->general($event)->id, 'quantity' => 1], ['ticket_type_id' => $family->id, 'quantity' => 1],
        ], 'quantity' => null, 'send_sms' => true])->assertCreated()
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.sms_count', 0)
            ->json('data.order');
        $this->assertCount(2, $order['tickets']);

        $res = $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/admit", [], $token)->assertOk();
        $this->assertSame(['valid', 'valid'], array_column($res->json('data.results'), 'result'));
        $this->assertSame([1, 4], array_column($res->json('data.results'), 'entries_admitted'));
        $res->assertJsonPath('data.results.1.entry.used', 4)->assertJsonPath('data.results.1.entry.admits', 4)
            ->assertJsonPath('data.results.1.ticket_type.requires_id', true)
            ->assertJsonPath('data.counts.in_today', 5);
        $this->assertSame(['used', 'used'], Ticket::orderBy('id')->pluck('status')->all());
        $this->assertSame(5, GateScan::where('source', 'box_office')->where('result', 'valid')->count());

        // Nothing left to admit by default; an explicit ticket is reported as already used.
        $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/admit", [], $token)->assertOk()->assertJsonCount(0, 'data.results');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/admit", ['ticket_ids' => [$order['tickets'][0]['id']]], $token)
            ->assertOk()->assertJsonPath('data.results.0.result', 'already_used');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$order['id']}/admit", ['ticket_ids' => [999999]], $token)->assertStatus(422);

        // Unpaid orders cannot be admitted.
        $unpaid = $this->order($token, $this->todayEvent())->json('data.order');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$unpaid['id']}/admit", [], $token)->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
    }

    #[Test]
    public function orders_are_listed_per_device_and_staff_and_isolated_per_tenant(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $mine = $this->order($token, $event, ['quantity' => 1])->json('data.order');
        $this->order($token, $event, ['quantity' => 2, 'items' => [['ticket_type_id' => $this->general($event)->id, 'quantity' => 0]]])->assertStatus(422);

        [, $otherDevice] = $this->sellingDevice([], '96890000006');
        $this->order($otherDevice, $event, ['quantity' => 3])->assertCreated();

        $list = $this->gate('GET', '/api/v1/gate/sales/orders', [], $token)->assertOk();
        $this->assertSame([$mine['id']], array_column($list->json('data.orders'), 'id'));
        $list->assertJsonPath('data.totals.count', 1)->assertJsonPath('data.totals.pending_count', 1)->assertJsonPath('data.totals.paid_minor', 0);
        $this->gate('GET', "/api/v1/gate/sales/orders/{$mine['id']}", [], $otherDevice)->assertNotFound();

        // Another tenant's device never sees it, and its pay page is branded for the order's tenant.
        $other = $this->makeTenant('other-org', ['gate_sales' => ['enabled' => true]]);
        $foreignGate = $this->makeGate(['can_sell' => true], $other);
        [, $foreignToken] = $this->pairDevice($foreignGate, $other);
        $staff = $this->makeUser('96890000007');
        $this->grantRole($staff, 'staff', $other);
        $this->staffLogin($foreignToken, '96890000007', null);
        $this->gate('GET', "/api/v1/gate/sales/orders/{$mine['id']}", [], $foreignToken)->assertNotFound();
        $this->gate('GET', '/api/v1/gate/sales/catalog', [], $foreignToken)->assertOk()->assertJsonPath('data.events', []);
        $this->gate('POST', '/api/v1/gate/sales/quote', ['event_id' => $event->id, 'quantity' => 1], $foreignToken)->assertNotFound();

        $this->get(parse_url($mine['payment_url'], PHP_URL_PATH))->assertOk()->assertSee($this->tenant->localizedName('ar'));
    }

    /** Checkout, then let the link expire; returns the signed fake-gateway "complete" URL captured before expiry. */
    private function openGatewayThenExpire(array $order): string
    {
        $path = parse_url($order['payment_url'], PHP_URL_PATH);
        $gatewayUrl = $this->post($path.'/checkout')->assertRedirect()->headers->get('Location');
        $complete = $this->fakeCompleteUrl($gatewayUrl);
        $this->travel(31)->minutes();
        $this->artisan('box-office:expire')->assertExitCode(0);

        return $complete;
    }

    #[Test]
    public function a_late_capture_with_seats_left_still_issues_the_tickets(): void
    {
        $event = $this->todayEvent(['total_tickets' => 5]);
        [, $token] = $this->sellingDevice();
        $order = $this->order($token, $event, ['quantity' => 2])->json('data.order');
        $complete = $this->openGatewayThenExpire($order);
        $this->assertSame('expired', BoxOfficeOrder::find($order['id'])->status);

        // The bank captures anyway: seats are still free → tickets + paid.
        $this->post($complete, ['result' => 'success'])->assertRedirect();
        $model = BoxOfficeOrder::find($order['id']);
        $this->assertSame(['paid', 'expired'], [$model->status, $model->meta['paid_after'] ?? null]);
        $this->assertSame(2, Ticket::count());
        $this->get(parse_url($order['payment_url'], PHP_URL_PATH))->assertSee('data-order-status="paid"', false);
    }

    #[Test]
    public function a_late_capture_without_seats_issues_nothing_and_is_flagged_for_refund(): void
    {
        $event = $this->todayEvent(['total_tickets' => 3]);
        [, $token] = $this->sellingDevice();
        $late = $this->order($token, $event, ['quantity' => 2])->json('data.order');
        $complete = $this->openGatewayThenExpire($late);

        // Meanwhile the seats were sold to someone else.
        $other = $this->order($token, $event, ['quantity' => 2, 'buyer_phone' => '99887766'])->assertCreated()->json('data.order');
        $this->payOnline($other['payment_url'])->assertRedirect();
        $this->assertSame(2, Ticket::count());

        $this->post($complete, ['result' => 'success'])->assertRedirect();
        $model = BoxOfficeOrder::find($late['id']);
        $this->assertSame('paid_unfulfilled', $model->status);
        $this->assertTrue($model->meta['refund_required']);
        $payment = Payment::findOrFail($model->payment_id);
        $this->assertSame('completed', $payment->status);
        $this->assertTrue($payment->metadata['box_office_refund_required']);
        $this->assertSame(0, Ticket::where('transaction_id', $payment->transaction_id)->count());
        $this->assertSame(2, Ticket::count());
        $this->assertSame(0, (int) PointsTransaction::where('reference_id', $payment->id)->where('type', 'earn')->sum('points'));

        // Replaying the capture changes nothing.
        app(\App\Services\Payments\PaymentFulfillmentService::class)->complete($payment, [], 'fake');
        $this->assertSame(2, Ticket::count());

        // The visitor, the gate and the admin all see it.
        $path = parse_url($late['payment_url'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('data-order-status="paid_unfulfilled"', false)->assertSee('لم تصدر التذاكر');
        $this->get($path.'?lang=en')->assertSee('No tickets were issued');
        $this->gate('GET', "/api/v1/gate/sales/orders/{$late['id']}", [], $token)->assertJsonPath('data.order.status', 'paid_unfulfilled')
            ->assertJsonPath('data.order.tickets', []);
        $this->gate('GET', '/api/v1/gate/sales/orders', [], $token)->assertJsonPath('data.totals.refund_due_count', 1);
        $this->gate('POST', "/api/v1/gate/sales/orders/{$late['id']}/admit", [], $token)->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');

        $admin = User::factory()->create();
        $this->grantRole($admin, 'ticket-manager');
        $this->actingAs($admin)->get(route('admin.gates.sales.index'))->assertOk()->assertSee('data-refund-due="1"', false);
        $this->actingAs($admin)->get(route('admin.gates.sales.show', $late['id']))->assertOk()->assertSee(route('admin.gates.sales.refund', $late['id']));
        $this->actingAs($admin)->post(route('admin.gates.sales.refund', $late['id']))->assertRedirect(route('admin.gates.sales.show', $late['id']));
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('refunded', $model->fresh()->status);
        $this->get($path)->assertSee('data-order-status="refunded"', false)->assertSee('أُعيد المبلغ');
        $this->actingAs($admin)->get(route('admin.gates.sales.index'))->assertDontSee('data-refund-due', false);

        // Without the refund permission the action is refused.
        $gateOnly = User::factory()->create();
        $this->grantRole($gateOnly, 'ticket-manager');
        $this->inTenant($this->tenant, fn () => $gateOnly->roles()->first()->revokePermissionTo('refund payments'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($gateOnly)->post(route('admin.gates.sales.refund', $late['id']))->assertForbidden();
    }

    #[Test]
    public function a_late_capture_is_refused_when_one_ticket_type_has_no_seats_left(): void
    {
        $event = $this->todayEvent(['total_tickets' => 50]);
        $vip = $this->addType($event, ['key' => 'vip', 'capacity' => 1]);
        [, $token] = $this->sellingDevice();
        $late = $this->order($token, $event, ['items' => [['ticket_type_id' => $vip->id, 'quantity' => 1]], 'quantity' => null])->json('data.order');
        $complete = $this->openGatewayThenExpire($late);
        $other = $this->order($token, $event, ['items' => [['ticket_type_id' => $vip->id, 'quantity' => 1]], 'quantity' => null, 'buyer_phone' => '99887766'])->json('data.order');
        $this->payOnline($other['payment_url']);

        $this->post($complete, ['result' => 'success']);
        $this->assertSame('paid_unfulfilled', BoxOfficeOrder::find($late['id'])->status);
        $this->assertSame(1, Ticket::where('ticket_type_id', $vip->id)->count());
    }

    #[Test]
    public function the_gateway_hand_off_refuses_expired_and_cancelled_orders(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();

        // A signed gateway link opened before expiry no longer reaches the gateway afterwards.
        $order = $this->order($token, $event)->json('data.order');
        $path = parse_url($order['payment_url'], PHP_URL_PATH);
        $gatewayUrl = $this->post($path.'/checkout')->assertRedirect()->headers->get('Location');
        $this->get($gatewayUrl)->assertOk()->assertSee('Simulate success');
        $this->travel(31)->minutes();
        $this->get($gatewayUrl)->assertRedirect(route('box-office.pay', ['token' => basename($path), 'lang' => 'ar']));
        $this->assertSame('expired', BoxOfficeOrder::find($order['id'])->status);
        $this->assertSame('cancelled', Payment::where('metadata->box_office_order_id', $order['id'])->sole()->status);
        $this->post($path.'/checkout')->assertRedirect(route('box-office.pay', ['token' => basename($path), 'lang' => 'ar']));
        $this->assertSame(1, Payment::where('metadata->box_office_order_id', $order['id'])->count());

        // Same for a cancelled order.
        $this->travelBack();
        $cancelled = $this->order($token, $event)->json('data.order');
        $cPath = parse_url($cancelled['payment_url'], PHP_URL_PATH);
        $cGateway = $this->post($cPath.'/checkout')->headers->get('Location');
        $this->gate('POST', "/api/v1/gate/sales/orders/{$cancelled['id']}/cancel", [], $token)->assertOk();
        $this->get($cGateway)->assertRedirect(route('box-office.pay', ['token' => basename($cPath), 'lang' => 'ar']));
        $this->post($cPath.'/checkout')->assertRedirect();
        $this->assertSame(1, Payment::where('metadata->box_office_order_id', $cancelled['id'])->count());
    }

    #[Test]
    public function a_second_payment_for_an_already_paid_order_issues_nothing(): void
    {
        $event = $this->todayEvent();
        [, $token] = $this->sellingDevice();
        $order = $this->order($token, $event, ['quantity' => 1])->json('data.order');
        $model = BoxOfficeOrder::find($order['id']);
        // Two gateway payments exist (e.g. a stale one captured late).
        $first = app(\App\Services\Gate\BoxOfficeService::class)->startPayment($model);
        $first->update(['status' => 'failed']);
        $second = app(\App\Services\Gate\BoxOfficeService::class)->startPayment($model->fresh());
        $fulfillment = app(\App\Services\Payments\PaymentFulfillmentService::class);
        $fulfillment->complete($second, [], 'fake');
        $fulfillment->complete($first->fresh(), [], 'fake');

        $this->assertSame(1, Ticket::count());
        $this->assertSame('paid', $model->fresh()->status);
        $this->assertSame($second->id, $model->fresh()->payment_id);
        $this->assertTrue($first->fresh()->metadata['box_office_refund_required']);
        $this->assertSame([$first->id], $model->fresh()->meta['refund_payment_ids']);
    }
}
