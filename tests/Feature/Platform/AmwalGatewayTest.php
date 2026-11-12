<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentGatewayCall;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payments\AmwalCheckout;
use App\Services\Payments\Gateway\AmwalConfig;
use App\Services\Payments\Gateway\AmwalGateway;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * Amwal Pay SmartBox (docs/payments_amwal.md): signed SmartBox page,
 * callback and notification verification, API inquiry, reconciliation,
 * late captures, refunds / voids and the admin settings.
 */
class AmwalGatewayTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    // A made-up key (64 hex chars, like Amwal's); never a real one.
    private const KEY = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';

    private const MID = '153400';

    private const TID = '502900';

    /** @var array<string, array<int, array>> fake Amwal transactions by merchant reference */
    private array $txs = [];

    /** @var array<string, array> queued failures per operation: int status | 'timeout' | 'refused' | 'reject' */
    private array $fail = [];

    /** @var array<int, array{op: string, body: array}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        config([
            'payments.driver' => 'amwal',
            'services.amwal.mode' => 'uat',
            'services.amwal.merchant_id' => self::MID,
            'services.amwal.terminal_id' => self::TID,
            'services.amwal.secure_key' => self::KEY,
            'payments.circuit.threshold' => 50,
        ]);

        Http::fake(fn (Request $request) => $this->amwal($request));
    }

    private function amwal(Request $request)
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $op = match (true) {
            str_ends_with($path, '/Transaction/GetByMerchantReference') => 'get_transactions',
            str_ends_with($path, '/Execute/Refund') => 'refund',
            str_ends_with($path, '/Execute/Void') => 'void',
            default => 'unknown',
        };
        $body = $request->data();
        $this->sent[] = ['op' => $op, 'body' => $body];
        $this->assertStringStartsWith('https://test.amwalpg.com:14443/', $request->url());

        // Every request is signed like Amwal checks it.
        $fields = $body;
        unset($fields['secureHashValue']);
        $this->assertSame(AmwalGateway::sign($fields, self::KEY), $body['secureHashValue'] ?? null, "$op hash");

        if ($f = isset($this->fail[$op]) ? array_shift($this->fail[$op]) : null) {
            if ($f === 'timeout' || $f === 'refused') {
                throw new ConnectException('cURL error '.($f === 'timeout' ? 28 : 7).': simulated', new PsrRequest('POST', $request->url()));
            }
            if ($f === 'reject') {
                return Http::response(['success' => false, 'responseCode' => null, 'message' => 'APGEX: abc', 'data' => null, 'errorList' => ['Can not Refund or void an already Refunded Transaction']]);
            }

            return Http::response(['success' => false, 'message' => "Simulated $f"], $f);
        }

        return match ($op) {
            'get_transactions' => Http::response(['success' => true, 'responseCode' => '00', 'message' => 'Success', 'errorList' => [],
                'data' => array_reverse($this->txs[$body['merchantReference']] ?? [])]),
            'refund', 'void' => Http::response(['success' => true, 'responseCode' => '00', 'message' => 'Success', 'errorList' => [],
                'data' => ['transactionId' => 'rev-'.count($this->sent), 'transactionTypeDisplayName' => $op === 'void' ? 'Void' : 'Refund', 'amount' => $body['amount']]]),
            default => Http::response([], 500),
        };
    }

    /** A transaction at Amwal for a reference (approved unless $code says otherwise). */
    private function pay(string $reference, string $amount, string $code = '00', array $actions = ['canVoid' => true, 'canRefund' => false]): string
    {
        $id = (string) Str::uuid();
        $this->txs[$reference][] = [
            'id' => $id, 'idN' => count($this->txs, COUNT_RECURSIVE), 'amount' => (float) $amount, 'currency' => 'OMR', 'currencyId' => 512,
            'responseCode' => $code, 'responseCodeName' => $code === '00' ? 'Approved' : 'Declined', 'transactionType' => 'Purchase',
            'merchantReference' => $reference, 'merchantId' => (int) self::MID, 'isRefunded' => false, 'isCaptured' => true,
            'transactionActions' => $actions,
        ];

        return $id;
    }

    private function sentCount(string $op): int
    {
        return count(array_filter($this->sent, fn ($s) => $s['op'] === $op));
    }

    private function types(Payment $payment): array
    {
        return PaymentEvent::where('payment_id', $payment->id)->orderBy('id')->pluck('type')->all();
    }

    private function startTopup(User $user, int $amount = 10000): array
    {
        return $this->api('POST', '/api/v1/wallet/topups', ['amount_minor' => $amount, 'idempotency_key' => uniqid('k', true)], $user)
            ->assertCreated()->json('data');
    }

    /** What SmartBox's completeCallback sends us (our page builds this query), signed like Amwal does. */
    private function smartBoxCallback(string $reference, string $amount, string $code = '00', ?string $key = self::KEY): array
    {
        $params = [
            'amount' => $amount, 'currencyId' => '512', 'customerId' => '', 'customerTokenId' => '',
            'merchantReference' => $reference, 'responseCode' => $code, 'transactionId' => (string) Str::uuid(),
            'transactionTime' => '2026-11-12T10:15:00.1234567Z',
        ];
        $params['secureHashValue'] = AmwalGateway::sign($params + ['merchantId' => self::MID, 'terminalId' => self::TID], $key);

        return $params;
    }

    private function returnUrl(Payment $payment, array $params, bool $token = true): string
    {
        $tx = $payment->transaction_id;

        return '/payment/success?'.http_build_query(array_filter(['transaction_id' => $tx, 't' => $token ? AmwalCheckout::returnToken($tx) : null]) + $params);
    }

    private function notification(string $reference, string $amount, string $code = '00', ?string $key = self::KEY, array $override = []): array
    {
        $body = array_merge([
            'MerchantId' => self::MID, 'TerminalId' => self::TID, 'AuthorizationDateTime' => '20261112101500', 'DateTimeLocalTrxn' => '20261112141500',
            'ResponseCode' => $code, 'TxnType' => 'Purchase', 'PaidThrough' => 'Card', 'SystemReference' => '723456789012',
            'Message' => $code === '00' ? 'Approved' : 'Declined', 'MerchantReference' => $reference, 'Amount' => $amount, 'CurrencyId' => 512, 'UDF' => '',
        ], $override);
        $fields = array_intersect_key($body, array_flip(AmwalGateway::NOTIFICATION_FIELDS));
        $body['SecureHash'] = AmwalGateway::sign($fields, $key);

        return $body;
    }

    private function postNotification(array $body)
    {
        return $this->call('POST', '/api/v1/payments/webhooks/amwal/'.$this->tenant->slug, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    // ------------------------------------------------------------------ hashing

    #[Test]
    public function the_secure_hash_matches_amwals_own_plugin(): void
    {
        // amwalpay-for-woocommerce: generateString() / generateStringForFilter().
        $string = 'Amount=1.025&CurrencyId=512&MerchantId=153400&MerchantReference=TXN1_1&RequestDateTime=20261112101500&SessionToken=&TerminalId=502900';
        $expected = strtoupper(hash_hmac('sha256', $string, hex2bin(self::KEY)));

        $this->assertSame($expected, AmwalGateway::sign([
            'TerminalId' => '502900', 'SessionToken' => null, 'RequestDateTime' => '20261112101500', 'MerchantReference' => 'TXN1_1',
            'MerchantId' => '153400', 'CurrencyId' => 512, 'Amount' => '1.025',
        ], self::KEY));

        $config = AmwalGateway::smartBoxConfig(AmwalConfig::forTenant($this->tenant), 'TXN1_1', 1025, 'en', \Illuminate\Support\Carbon::parse('2026-11-12 10:15:00', 'UTC'));
        $this->assertSame(['153400', '502900', 512, '1.025', 'TXN1_1', '20261112101500', $expected],
            [$config['MID'], $config['TID'], $config['CurrencyId'], $config['AmountTrxn'], $config['MerchantReference'], $config['TrxDateTime'], $config['SecureHash']]);

        // Callback fields, sorted: customerId before customerTokenId; "null" / "undefined" count as empty.
        $cb = 'amount=1.025&currencyId=512&customerId=&customerTokenId=&merchantId=153400&merchantReference=TXN1_1&responseCode=00&terminalId=502900&transactionId=g&transactionTime=t';
        $this->assertSame(strtoupper(hash_hmac('sha256', $cb, hex2bin(self::KEY))), AmwalGateway::sign([
            'amount' => '1.025', 'currencyId' => '512', 'customerId' => 'null', 'customerTokenId' => 'undefined', 'merchantId' => '153400',
            'merchantReference' => 'TXN1_1', 'responseCode' => '00', 'terminalId' => '502900', 'transactionId' => 'g', 'transactionTime' => 't',
        ], self::KEY));
    }

    // ------------------------------------------------------------------ checkout

    #[Test]
    public function the_payment_link_renders_a_signed_smartbox_page(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);

        $page = $this->get($topup['payment_url'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1', false)
            ->assertSee('"AmountTrxn":"10.000"', false)
            ->assertSee('"MID":"'.self::MID.'"', false)
            ->assertDontSee(self::KEY);

        $payment = Payment::firstOrFail();
        $this->assertSame('amwal', $payment->gateway);
        $this->assertSame($payment->transaction_id.'_1', $payment->gateway_session_id);
        $this->assertNotNull($payment->next_check_at);
        $page->assertSee('"MerchantReference":"'.$payment->transaction_id.'_1"', false)
            ->assertSee('t='.AmwalCheckout::returnToken($payment->transaction_id), false);

        // Each open is a new attempt (Amwal has no session to reuse).
        $this->get($topup['payment_url'])->assertOk()->assertSee($payment->transaction_id.'_2');
        $sessions = PaymentGatewaySession::orderBy('id')->get();
        $this->assertSame([['uat', 'open', 10000], ['uat', 'open', 10000]], $sessions->map(fn ($s) => [$s->mode, $s->status, $s->amount_minor])->all());
        $this->assertNotNull($sessions[0]->expires_at);
        $this->assertArrayNotHasKey('SecureHash', $sessions[0]->data);
        $this->assertSame(0, $this->sentCount('get_transactions'));
    }

    #[Test]
    public function a_payment_page_without_credentials_says_so(): void
    {
        config(['services.amwal.secure_key' => '']);
        $topup = $this->startTopup($this->makeUser());

        $this->get($topup['payment_url'])->assertOk()->assertSee('data-error-code="gateway_not_configured"', false);
        $this->assertSame(0, PaymentGatewaySession::count());
    }

    #[Test]
    public function a_signed_callback_completes_a_topup_once(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $cb = $this->smartBoxCallback($payment->transaction_id.'_1', '10.000');

        $this->get($this->returnUrl($payment, $cb))->assertOk();
        $this->get($this->returnUrl($payment, $cb))->assertOk();
        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertJsonPath('data.status', 'completed');

        $this->assertSame(10000, $this->wallet($user)->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'topup')->count());
        $payment->refresh();
        $this->assertSame(['completed', $cb['transactionId'], $payment->transaction_id.'_1'], [$payment->status, $payment->gateway_payment_id, $payment->gateway_session_id]);
        $this->assertSame('paid', PaymentGatewaySession::firstOrFail()->status);
        $this->assertSame(0, $this->sentCount('get_transactions')); // the signed callback was enough
        $this->assertContains('callback_received', $this->types($payment));
    }

    #[Test]
    public function a_tampered_or_untokened_callback_is_not_trusted(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $ref = $payment->transaction_id.'_1';

        // Signed with another key: Amwal is asked instead, and has nothing.
        $this->get($this->returnUrl($payment, $this->smartBoxCallback($ref, '10.000', key: strrev(self::KEY))))->assertOk()
            ->assertSee('data-error-code="not_completed"', false)
            ->assertSee('/payment/redirect/'.$payment->transaction_id, false); // "try again" reopens SmartBox
        $this->assertSame(1, $this->sentCount('get_transactions'));

        // A valid hash on a URL without our token is only a (throttled) status check.
        $this->travel(10)->seconds();
        $this->get($this->returnUrl($payment, $this->smartBoxCallback($ref, '10.000'), token: false))->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);

        // An approved callback for an amount we did not ask for is never fulfilled.
        $this->get($this->returnUrl($payment, $this->smartBoxCallback($ref, '1.000')))->assertOk();
        $payment->refresh();
        $this->assertSame(['pending', true, 'amount_mismatch'], [$payment->status, $payment->needs_review, $payment->review_reason]);
        $this->assertSame(0, $this->wallet($user)->balance_minor);
    }

    #[Test]
    public function a_declined_callback_asks_amwal_and_the_visitor_can_pay_again(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 2,
        ], $user)->assertCreated();
        $this->get($res->json('payment_url'))->assertOk()->assertSee('"AmountTrxn":"5.000"', false);
        $payment = Payment::firstOrFail();
        $this->pay($payment->transaction_id.'_1', '5.000', '05');

        $this->get($this->returnUrl($payment, $this->smartBoxCallback($payment->transaction_id.'_1', '5.000', '05')))->assertSee('not_completed');
        $this->assertSame('pending', $payment->fresh()->status);

        // Second attempt approved; the app's poll finds it through the API.
        $this->get($res->json('payment_url'))->assertOk();
        $this->pay($payment->transaction_id.'_2', '5.000');
        $this->travel(10)->seconds();
        $this->api('GET', '/api/v1/payments/status/'.$payment->transaction_id, [], $user)->assertJsonPath('data.status', 'completed');

        $this->assertSame(2, Ticket::where('transaction_id', $payment->transaction_id)->count());
        $this->assertSame($payment->transaction_id.'_2', $payment->fresh()->gateway_session_id);
    }

    // ------------------------------------------------------------------ notifications

    #[Test]
    public function a_signed_cloud_notification_completes_the_payment(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $body = $this->notification($payment->transaction_id.'_1', '4');

        $this->postNotification($body)->assertOk()->assertExactJson(['success' => true, 'message' => 'success']);
        $this->postNotification($body)->assertOk();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(4000, $this->wallet($user)->balance_minor);
        $event = PaymentWebhookEvent::sole();
        $this->assertSame(['amwal', 'processed', true, 2, $payment->id, 'Purchase'], [$event->gateway, $event->status, $event->signature_valid, $event->deliveries, $event->payment_id, $event->event_type]);
    }

    #[Test]
    public function forged_notifications_are_rejected_and_recorded(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $ref = $payment->transaction_id.'_1';

        $this->postNotification($this->notification($ref, '4', key: strrev(self::KEY)))->assertStatus(401);
        $this->postNotification($this->notification($ref, '4', override: ['MerchantId' => '999']))->assertStatus(401);
        $tampered = $this->notification($ref, '0.004');
        $tampered['Amount'] = '4';
        $this->postNotification($tampered)->assertStatus(401);
        $this->call('POST', '/api/v1/payments/webhooks/amwal/'.$this->tenant->slug, [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertStatus(400);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(['bad_signature', 'merchant_mismatch', 'bad_signature', 'invalid_json'],
            PaymentWebhookEvent::orderBy('id')->pluck('last_error')->all());
        $this->assertSame(4, PaymentWebhookEvent::where('status', 'rejected')->count());

        // Another tenant's URL for this payment's reference.
        $other = $this->makeTenant('other-org');
        $this->call('POST', '/api/v1/payments/webhooks/amwal/'.$other->slug, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->notification($ref, '4')))->assertStatus(401);
        $this->assertSame('tenant_mismatch', PaymentWebhookEvent::latest('id')->value('last_error'));
    }

    #[Test]
    public function a_declined_notification_does_not_fail_the_payment(): void
    {
        $topup = $this->startTopup($this->makeUser(), 4000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();

        $this->postNotification($this->notification($payment->transaction_id.'_1', '4', '51'))->assertOk();

        $this->assertSame('pending', $payment->fresh()->status); // the visitor may still pay
        $this->assertSame(1, $this->sentCount('get_transactions'));
    }

    // ------------------------------------------------------------------ reconciliation

    #[Test]
    public function the_reconciler_completes_paid_payments_and_cancels_unpaid_ones(): void
    {
        $user = $this->makeUser();
        $paid = $this->startTopup($user, 3000);
        $this->get($paid['payment_url']);
        $unpaid = $this->startTopup($user, 2000);
        $this->get($unpaid['payment_url']);
        $p1 = Payment::where('payable_id', $paid['topup_id'])->firstOrFail();
        $p2 = Payment::where('payable_id', $unpaid['topup_id'])->firstOrFail();
        $this->pay($p1->transaction_id.'_1', '3.000');

        $this->travel(2)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('completed', $p1->fresh()->status);
        $this->assertSame('pending', $p2->fresh()->status);
        $this->assertSame(3000, $this->wallet($user)->balance_minor);

        // Past the give-up time (and the attempt's watch time): cancelled.
        $this->travel(3)->hours();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('cancelled', $p2->fresh()->status);
        $this->assertSame('expired', PaymentGatewaySession::where('payment_id', $p2->id)->value('status'));
        $this->assertSame(0, $this->wallet($user)->balance_minor - 3000);
    }

    #[Test]
    public function a_payment_cancelled_in_the_app_is_completed_if_paid_late(): void
    {
        config(['payments.amwal.session_ttl_minutes' => 240]);
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();

        $this->api('POST', '/api/v1/payments/cancel', ['transaction_id' => $payment->transaction_id], $user)->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(1, $this->sentCount('get_transactions')); // asked first

        // The SmartBox page was still open and the visitor paid.
        $this->pay($payment->transaction_id.'_1', '4.000');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(4000, $this->wallet($user)->balance_minor);
        $this->assertContains('late_capture', $this->types($payment));
    }

    #[Test]
    public function a_second_paid_attempt_is_flagged_as_a_double_charge(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();

        $this->get($this->returnUrl($payment, $this->smartBoxCallback($payment->transaction_id.'_2', '4.000')));
        $this->assertSame('completed', $payment->fresh()->status);

        $this->pay($payment->transaction_id.'_1', '4.000');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $payment->refresh();
        $this->assertSame([true, 'duplicate_capture'], [$payment->needs_review, $payment->review_reason]);
        $this->assertSame(4000, $this->wallet($user)->balance_minor);
    }

    #[Test]
    public function an_unreachable_amwal_never_changes_the_payment(): void
    {
        config(['payments.http.interactive.attempts' => 2]);
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $this->fail['get_transactions'] = ['refused', 'refused'];

        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertSame(2, $this->sentCount('get_transactions'));
        $this->assertSame(['network_error', 'network_error'], PaymentGatewayCall::orderBy('id')->pluck('outcome')->all());
        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ------------------------------------------------------------------ refunds

    private function paidTicket(array $actions = ['canVoid' => true, 'canRefund' => false]): Payment
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
        ], $user)->assertCreated();
        $this->get($res->json('payment_url'));
        $payment = Payment::where('transaction_id', $res->json('transaction_id'))->firstOrFail();
        $this->pay($payment->transaction_id.'_1', '2.500', '00', $actions);

        return app(AmwalCheckout::class)->reconcile($payment, 'system');
    }

    private function admin(): User
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'finance-manager');

        return $admin;
    }

    #[Test]
    public function an_admin_refund_voids_a_same_day_payment(): void
    {
        $payment = $this->paidTicket();
        $guid = $this->txs[$payment->transaction_id.'_1'][0]['id'];
        $this->assertSame($guid, $payment->gateway_payment_id);

        $this->actingAs($this->admin())->post(route('admin.payments.refund', $payment), ['reason' => 'Event moved'])
            ->assertRedirect()->assertSessionHas('success');

        $refund = PaymentRefund::sole();
        $this->assertSame(['succeeded', $guid, 2500, 'void'], [$refund->status, $refund->gateway_payment_id, $refund->amount_minor, $refund->data['operation']]);
        $sent = collect($this->sent)->firstWhere('op', 'void')['body'];
        $this->assertSame(['2.500', 512, (int) self::MID, (int) self::TID, 2, $guid],
            [$sent['amount'], $sent['currencyCode'], $sent['merchantId'], $sent['terminalId'], $sent['transactionIdentifierType'], $sent['transactionIdentifierValue']]);
        $this->assertTrue(Str::isUuid($sent['transactionId']));
        $this->assertSame(0, $this->sentCount('refund'));
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('cancelled', Ticket::where('transaction_id', $payment->transaction_id)->value('status'));
    }

    #[Test]
    public function an_admin_refund_refunds_a_settled_payment(): void
    {
        $payment = $this->paidTicket(['canVoid' => false, 'canRefund' => true]);

        $this->actingAs($this->admin())->post(route('admin.payments.refund', $payment))->assertSessionHas('success');

        $this->assertSame(['succeeded', 'refund'], [PaymentRefund::sole()->status, PaymentRefund::sole()->data['operation']]);
        $this->assertSame([1, 0], [$this->sentCount('refund'), $this->sentCount('void')]);
    }

    #[Test]
    public function a_rejected_or_unclear_refund_goes_to_a_person(): void
    {
        $payment = $this->paidTicket(['canVoid' => false, 'canRefund' => true]);
        $this->fail['refund'] = ['reject'];
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment))->assertRedirect();
        $refund = PaymentRefund::sole();
        $this->assertSame('failed', $refund->status);
        $this->assertStringContainsString('already Refunded', $refund->last_error);
        $this->assertSame([true, 'refund_failed'], [$payment->fresh()->needs_review, $payment->fresh()->review_reason]);

        // Retried; this time the request times out: never resent on its own.
        $this->fail['refund'] = ['timeout'];
        $this->actingAs($admin)->post(route('admin.payments.refund.retry', $payment))->assertSessionHas('success');
        $this->assertSame('needs_review', $refund->fresh()->status);
        $this->assertSame(2, $this->sentCount('refund'));
    }
}
