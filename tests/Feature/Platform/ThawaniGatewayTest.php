<?php

namespace Tests\Feature\Platform;

use App\Jobs\ProcessGatewayRefund;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentGatewayCall;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Payments\ThawaniCheckout;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * Thawani Pay (docs/payments_thawani.md): checkout sessions, return pages,
 * webhooks, polling, reconciliation, retries, circuit breaker, refunds and
 * the records kept for each.
 */
class ThawaniGatewayTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private const SECRET = 'sk_test_secret_value';

    /** @var array<string, array> fake Thawani sessions by id */
    private array $sessions = [];

    /** @var array<string, array> queued failures per operation: int status | 'timeout' | 'refused' */
    private array $fail = [];

    /** @var array<int, array{op: string, body: array}> */
    private array $sent = [];

    private int $refunds = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        config([
            'payments.driver' => 'thawani',
            'services.thawani.mode' => 'uat',
            'services.thawani.secret_key' => self::SECRET,
            'services.thawani.publishable_key' => 'pk_test_public',
            'services.thawani.webhook_secret' => 'whsec_test',
            'payments.circuit.threshold' => 50,
        ]);

        Http::fake(fn (Request $request) => $this->thawani($request));
    }

    private function thawani(Request $request)
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $query = (string) parse_url($request->url(), PHP_URL_QUERY);
        $op = match (true) {
            $request->method() === 'POST' && str_ends_with($path, '/checkout/session') => 'create_session',
            $request->method() === 'POST' && preg_match('#/api/v1/checkout/(?!session/)[^/]+/cancel$#', $path) => 'cancel_session',
            $request->method() === 'GET' && str_contains($path, '/checkout/session/') => 'get_session',
            str_ends_with($path, '/payments') => 'get_payments',
            str_ends_with($path, '/refunds') => 'create_refund',
            default => 'unknown',
        };
        $this->sent[] = ['op' => $op, 'body' => $request->data()];
        $this->assertSame(self::SECRET, $request->header('thawani-api-key')[0] ?? null);
        $this->assertStringStartsWith('https://uatcheckout.thawani.om/api/v1/', $request->url());

        if ($f = isset($this->fail[$op]) ? array_shift($this->fail[$op]) : null) {
            if ($f === 'timeout' || $f === 'refused') {
                $code = $f === 'timeout' ? 28 : 7;
                throw new ConnectException("cURL error $code: simulated", new PsrRequest($request->method(), $request->url()));
            }

            return Http::response(['success' => false, 'code' => $f, 'description' => "Simulated $f"], $f);
        }

        switch ($op) {
            case 'create_session':
                $body = $request->data();
                $id = 'checkout_'.(count($this->sessions) + 1);
                $this->sessions[$id] = [
                    'session_id' => $id,
                    'client_reference_id' => $body['client_reference_id'],
                    'payment_status' => 'unpaid',
                    'total_amount' => array_sum(array_map(fn ($p) => $p['unit_amount'] * $p['quantity'], $body['products'])),
                    'currency' => 'OMR',
                    'invoice' => 'INV'.(count($this->sessions) + 1),
                    'expire_at' => now()->addDay()->toIso8601String(),
                    'metadata' => $body['metadata'],
                ];

                return Http::response(['success' => true, 'code' => 2004, 'description' => 'Session generated', 'data' => $this->sessions[$id]]);
            case 'get_session':
                $id = basename($path);

                return isset($this->sessions[$id])
                    ? Http::response(['success' => true, 'code' => 2000, 'data' => $this->sessions[$id]])
                    : Http::response(['success' => false, 'code' => 4004, 'description' => 'Session not found'], 404);
            case 'cancel_session':
                $id = basename(dirname($path));
                $this->sessions[$id]['payment_status'] = 'cancelled';

                return Http::response(['success' => true, 'data' => $this->sessions[$id]]);
            case 'get_payments':
                parse_str($query, $q);

                return Http::response(['success' => true, 'data' => [['payment_id' => 'pay_'.$q['checkout_invoice'], 'status' => 'successful', 'masked_card' => '4242']]]);
            case 'create_refund':
                $this->refunds++;

                return Http::response(['success' => true, 'data' => ['refund_id' => 'ref_'.$this->refunds, 'status' => 'successful']]);
        }

        return Http::response([], 500);
    }

    private function pay(string $sessionId, ?int $total = null): void
    {
        $this->sessions[$sessionId]['payment_status'] = 'paid';
        if ($total !== null) {
            $this->sessions[$sessionId]['total_amount'] = $total;
        }
    }

    private function sentCount(string $op): int
    {
        return count(array_filter($this->sent, fn ($s) => $s['op'] === $op));
    }

    private function startTopup(User $user, int $amount = 10000): array
    {
        return $this->api('POST', '/api/v1/wallet/topups', ['amount_minor' => $amount, 'idempotency_key' => uniqid('k', true)], $user)
            ->assertCreated()->json('data');
    }

    private function signedWebhook(array $body, ?string $secret = 'whsec_test', ?int $ts = null)
    {
        $raw = json_encode($body);
        $ts ??= now()->timestamp;
        $sig = hash_hmac('sha256', $raw.'-'.$ts, (string) $secret);

        return $this->call('POST', '/api/v1/payments/webhooks/thawani/'.$this->tenant->slug, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_THAWANI_SIGNATURE' => $sig,
            'HTTP_THAWANI_TIMESTAMP' => (string) $ts,
        ], $raw);
    }

    private function types(Payment $payment): array
    {
        return PaymentEvent::where('payment_id', $payment->id)->orderBy('id')->pluck('type')->all();
    }

    // ------------------------------------------------------------------ checkout

    #[Test]
    public function payment_link_opens_a_thawani_session_and_reuses_it(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);

        $this->get($topup['payment_url'])
            ->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_1?key=pk_test_public');
        $this->get($topup['payment_url'])->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_1?key=pk_test_public');
        $this->assertSame(1, $this->sentCount('create_session'));

        $payment = Payment::firstOrFail();
        $this->assertSame('thawani', $payment->gateway);
        $this->assertSame('checkout_1', $payment->gateway_session_id);
        $this->assertNotNull($payment->next_check_at);

        $create = collect($this->sent)->firstWhere('op', 'create_session')['body'];
        $this->assertSame($payment->transaction_id.'-1', $create['client_reference_id']);
        $this->assertSame([['name' => 'Wallet top-up', 'quantity' => 1, 'unit_amount' => 10000]], $create['products']);
        $this->assertStringContainsString('t='.ThawaniCheckout::returnToken($payment->transaction_id), $create['cancel_url']);

        $session = PaymentGatewaySession::firstOrFail();
        $this->assertSame(['uat', 'open', 10000, 'INV1'], [$session->mode, $session->status, $session->amount_minor, $session->invoice]);
        $this->assertSame(['created', 'session_created', 'checkout_opened'], $this->types($payment));

        // Every call is recorded; the secret key never is.
        $call = PaymentGatewayCall::where('operation', 'create_session')->firstOrFail();
        $this->assertSame(['ok', 200, $payment->id], [$call->outcome, $call->response_status, $call->payment_id]);
        $this->assertSame(0, PaymentGatewayCall::where('request_body', 'like', '%'.self::SECRET.'%')->orWhere('url', 'like', '%'.self::SECRET.'%')->count());
    }

    #[Test]
    public function app_status_poll_confirms_a_paid_topup_once(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1');

        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)
            ->assertOk()->assertJsonPath('data.status', 'completed');
        // Polled again and again: still one credit.
        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertOk();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(10000, $this->wallet($user)->balance_minor);
        $this->assertSame(1, WalletTransaction::where('type', 'topup')->count());
        $payment = Payment::firstOrFail();
        $this->assertSame('pay_INV1', $payment->gateway_payment_id);
        $this->assertNull($payment->next_check_at);
        $completed = PaymentEvent::where('payment_id', $payment->id)->where('type', 'completed')->firstOrFail();
        $this->assertSame(['pending', 'completed', 'poll'], [$completed->from_status, $completed->to_status, $completed->source]);
    }

    #[Test]
    public function return_pages_ask_thawani_and_only_a_tokened_cancel_cancels(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user);
        $this->get($topup['payment_url']);
        $payment = Payment::firstOrFail();
        $tx = $payment->transaction_id;

        // Success page before Thawani says paid: "processing", nothing changes.
        $this->get("/payment/success?transaction_id=$tx")->assertOk()->assertSee('data-error-code="not_completed"', false);
        // A cancel URL without Thawani's token is display only.
        $this->get("/payment/cancel?transaction_id=$tx")->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->get("/payment/cancel?transaction_id=$tx&t=".ThawaniCheckout::returnToken($tx))->assertOk();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('cancelled', WalletTopup::first()->status);
        $event = PaymentEvent::where('payment_id', $payment->id)->where('type', 'cancelled')->firstOrFail();
        $this->assertSame(['redirect', 'visitor_cancelled'], [$event->source, $event->data['reason']]);
    }

    #[Test]
    public function success_return_completes_a_ticket_purchase(): void
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 2,
        ], $user)->assertCreated();
        $this->get($res->json('payment_url'))->assertRedirect();
        $create = collect($this->sent)->firstWhere('op', 'create_session')['body'];
        $this->assertSame('Concert x2', $create['products'][0]['name']);
        $this->assertSame(5000, $create['products'][0]['unit_amount']);

        $this->pay('checkout_1');
        $tx = $res->json('transaction_id');
        $this->get("/payment/success?transaction_id=$tx&t=".ThawaniCheckout::returnToken($tx))->assertOk()->assertSee($tx);

        $this->assertSame('completed', Payment::first()->status);
        $this->assertSame(2, Ticket::where('transaction_id', $tx)->count());
    }

    #[Test]
    public function a_paid_amount_that_does_not_match_is_never_fulfilled(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 10000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1', total: 100);

        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertJsonPath('data.status', 'pending');

        $payment = Payment::firstOrFail();
        $this->assertTrue($payment->needs_review);
        $this->assertSame('amount_mismatch', $payment->review_reason);
        $this->assertSame(0, $this->wallet($user)->balance_minor);
        $this->assertContains('amount_mismatch', $this->types($payment));
    }

    // ------------------------------------------------------------------ retries

    #[Test]
    public function create_session_is_retried_on_503_and_refused_connections(): void
    {
        config(['payments.http.interactive.attempts' => 3]);
        $user = $this->makeUser();
        $topup = $this->startTopup($user);
        $this->fail['create_session'] = [503, 'refused'];

        $this->get($topup['payment_url'])->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_1?key=pk_test_public');

        $calls = PaymentGatewayCall::where('operation', 'create_session')->orderBy('id')->get();
        $this->assertSame([1, 2, 3], $calls->pluck('attempt')->all());
        $this->assertSame(['server_error', 'network_error', 'ok'], $calls->pluck('outcome')->all());
        $this->assertCount(1, $calls->pluck('correlation_id')->unique());
    }

    #[Test]
    public function a_timed_out_create_is_not_resent_and_the_visitor_can_try_again(): void
    {
        config(['payments.http.interactive.attempts' => 3]);
        $user = $this->makeUser();
        $topup = $this->startTopup($user);
        $this->fail['create_session'] = ['timeout'];

        $this->get($topup['payment_url'])->assertOk()
            ->assertSee('data-error-code="gateway_unavailable"', false)
            ->assertSee('أعد المحاولة');
        $this->assertSame(1, $this->sentCount('create_session'));

        // The retry link works once Thawani answers.
        $this->get($topup['payment_url'])->assertRedirect();
        $this->assertSame(1, PaymentGatewaySession::count());
    }

    #[Test]
    public function the_circuit_opens_after_repeated_failures(): void
    {
        config(['payments.circuit.threshold' => 2, 'payments.http.interactive.attempts' => 1]);
        $user = $this->makeUser();
        $topup = $this->startTopup($user);
        $this->fail['create_session'] = [502, 502, 502];

        $this->get($topup['payment_url'])->assertSee('gateway_unavailable');
        $this->get($topup['payment_url'])->assertSee('gateway_unavailable');
        $this->get($topup['payment_url'])->assertSee('gateway_unavailable');

        $this->assertSame(2, $this->sentCount('create_session'));
        $this->assertSame(1, PaymentGatewayCall::where('outcome', 'circuit_open')->count());
    }

    // ------------------------------------------------------------------ webhooks

    #[Test]
    public function signed_webhook_completes_the_payment_and_duplicates_are_counted(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1');
        $body = ['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1', 'payment_status' => 'paid', 'client_reference_id' => 'x']];

        $this->signedWebhook($body)->assertOk()->assertJson(['success' => true]);
        $this->signedWebhook($body)->assertOk();

        $hook = PaymentWebhookEvent::sole();
        $this->assertSame(['processed', true, 2], [$hook->status, $hook->signature_valid, $hook->deliveries]);
        $payment = Payment::firstOrFail();
        $this->assertSame($payment->id, $hook->payment_id);
        $this->assertSame('completed', $payment->status);
        $this->assertSame(5000, $this->wallet($user)->balance_minor);
        $this->assertSame('webhook', PaymentEvent::where('payment_id', $payment->id)->where('type', 'completed')->value('source'));
    }

    #[Test]
    public function bad_or_stale_signatures_are_rejected_and_recorded(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1');

        $this->signedWebhook(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1']], 'wrong-secret')->assertStatus(401);
        $this->signedWebhook(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1', 'n' => 2]], ts: now()->subHour()->timestamp)->assertStatus(401);

        $this->assertSame(2, PaymentWebhookEvent::where('status', 'rejected')->where('signature_valid', false)->count());
        $this->assertSame('pending', Payment::first()->status);
        $this->assertSame(0, $this->sentCount('get_session'));
    }

    #[Test]
    public function a_webhook_that_cannot_be_applied_yet_is_retried_and_swept(): void
    {
        config(['payments.http.background.attempts' => 1]);
        Queue::fake();
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1');
        $this->fail['get_session'] = [503];

        $this->signedWebhook(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1']])->assertOk();
        $hook = PaymentWebhookEvent::sole();
        Queue::assertPushedOn('payments', ProcessPaymentWebhook::class, fn ($job) => $job->eventId === $hook->id);

        // First try: Thawani busy. The job throws (the queue retries it later).
        try {
            app()->call([new ProcessPaymentWebhook($hook->id), 'handle']);
            $this->fail('expected a retryable failure');
        } catch (\App\Services\Payments\Gateway\GatewayException $e) {
            $this->assertTrue($e->retryable());
        }
        $this->assertSame(['received', 1], [$hook->fresh()->status, $hook->fresh()->attempts]);
        $this->assertStringContainsString('503', (string) $hook->fresh()->last_error);
        $this->assertSame('pending', Payment::first()->status);

        // A webhook left `received` whose job was lost (queue flushed, so its
        // unique lock is gone too) is queued again by the sweeper.
        (new \Illuminate\Bus\UniqueLock(app('cache')->driver()))->release(new ProcessPaymentWebhook($hook->id));
        Queue::fake();
        $this->travel(6)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        Queue::assertPushed(ProcessPaymentWebhook::class, fn ($job) => $job->eventId === $hook->id);

        app()->call([new ProcessPaymentWebhook($hook->id), 'handle']);
        $this->assertSame('processed', $hook->fresh()->status);
        $this->assertSame('completed', Payment::first()->status);
        $this->assertSame(5000, $this->wallet($user)->balance_minor);
    }

    // ------------------------------------------------------------------ reconciliation

    #[Test]
    public function the_reconciler_completes_paid_payments_without_any_webhook(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 7000);
        $this->get($topup['payment_url']);

        $this->travel(2)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $payment = Payment::firstOrFail();
        $this->assertSame(['pending', 1], [$payment->status, $payment->check_count]);
        $this->assertTrue($payment->next_check_at->isFuture());

        $this->pay('checkout_1');
        $this->travel(5)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(7000, $this->wallet($user)->balance_minor);
        $this->assertSame('reconciler', PaymentEvent::where('payment_id', $payment->id)->where('type', 'completed')->value('source'));
    }

    #[Test]
    public function unpaid_payments_are_cancelled_after_the_give_up_time_and_late_captures_still_complete(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 4000);
        $this->get($topup['payment_url']);
        $neverOpened = $this->startTopup($user, 3000);

        // Thawani's cancel endpoint fails: the session stays open locally.
        $this->fail['cancel_session'] = array_fill(0, 20, 500);
        $this->travel(3)->hours();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $opened = Payment::where('payable_id', $topup['topup_id'])->firstOrFail();
        $this->assertSame('cancelled', $opened->status);
        $this->assertSame('not_paid_in_time', PaymentEvent::where('payment_id', $opened->id)->where('type', 'cancelled')->value('data')['reason']);
        $never = Payment::where('payable_id', $neverOpened['topup_id'])->firstOrFail();
        $this->assertSame('cancelled', $never->status);
        $this->assertSame('payment_page_never_opened', PaymentEvent::where('payment_id', $never->id)->where('type', 'cancelled')->value('data')['reason']);

        // The visitor pays on the still-open Thawani page: picked up later.
        $this->pay('checkout_1');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('completed', $opened->fresh()->status);
        $this->assertSame(4000, $this->wallet($user)->balance_minor);
        $this->assertContains('late_capture', $this->types($opened));
    }

    // ------------------------------------------------------------------ refunds

    private function paidTicket(): Payment
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);
        $user = $this->makeUser();
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
        ], $user)->assertCreated();
        $this->get($res->json('payment_url'));
        $this->pay('checkout_1');
        $payment = Payment::where('transaction_id', $res->json('transaction_id'))->firstOrFail();

        return app(ThawaniCheckout::class)->reconcile($payment, 'system');
    }

    private function admin(): User
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'finance-manager');

        return $admin;
    }

    #[Test]
    public function admin_refund_sends_the_money_back_to_the_card(): void
    {
        $payment = $this->paidTicket();
        $this->assertSame('completed', $payment->status);

        $this->actingAs($this->admin())->post(route('admin.payments.refund', $payment), ['reason' => 'Event moved'])
            ->assertRedirect()->assertSessionHas('success');

        $refund = PaymentRefund::sole();
        $this->assertSame(['succeeded', 'pay_INV1', 'ref_1', 2500, 1], [$refund->status, $refund->gateway_payment_id, $refund->gateway_refund_id, $refund->amount_minor, $refund->attempts]);
        $sent = collect($this->sent)->firstWhere('op', 'create_refund')['body'];
        $this->assertSame(['pay_INV1', 'Event moved'], [$sent['payment_id'], $sent['reason']]);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('cancelled', Ticket::where('transaction_id', $payment->transaction_id)->value('status'));
        $this->assertContains('gateway_refund_succeeded', $this->types($payment));
    }

    #[Test]
    public function an_unclear_refund_is_never_resent_without_a_person(): void
    {
        $payment = $this->paidTicket();
        $this->fail['create_refund'] = ['timeout'];
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment))->assertRedirect();
        $refund = PaymentRefund::sole();
        $this->assertSame('needs_review', $refund->status);
        $this->assertSame(1, $this->sentCount('create_refund'));
        $this->assertTrue($payment->fresh()->needs_review);

        // Retrying needs the admin to confirm the Thawani portal shows no refund.
        $this->actingAs($admin)->post(route('admin.payments.refund.retry', $payment))->assertSessionHasErrors('confirm_checked');
        $this->actingAs($admin)->post(route('admin.payments.refund.retry', $payment), ['confirm_checked' => '1'])->assertSessionHas('success');

        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame(2, $this->sentCount('create_refund'));
    }

    #[Test]
    public function a_refund_is_retried_while_thawani_is_busy(): void
    {
        config(['payments.http.background.attempts' => 1]); // the job-level retry, not the HTTP one
        $payment = $this->paidTicket();
        // 429 = not processed. (A 503 on a refund is treated as unclear: needs review.)
        $this->fail['create_refund'] = [429];
        Queue::fake();

        $this->actingAs($this->admin())->post(route('admin.payments.refund', $payment))->assertRedirect();
        $refund = PaymentRefund::sole();
        Queue::assertPushedOn('payments', ProcessGatewayRefund::class, fn ($job) => $job->refundId === $refund->id);

        // Pending, and the job throws so the queue retries it.
        try {
            app()->call([new ProcessGatewayRefund($refund->id), 'handle']);
            $this->fail('expected a retryable failure');
        } catch (\App\Services\Payments\Gateway\GatewayException $e) {
            $this->assertTrue($e->retryable());
        }
        $this->assertSame('pending', $refund->fresh()->status);
        $this->assertFalse($payment->fresh()->needs_review);

        app()->call([new ProcessGatewayRefund($refund->id), 'handle']);
        $this->assertSame(['succeeded', 2], [$refund->fresh()->status, $refund->fresh()->attempts]);
        $this->assertSame(['gateway_refund_retry', 'gateway_refund_succeeded'], array_values(array_intersect($this->types($payment), ['gateway_refund_retry', 'gateway_refund_succeeded'])));
    }

    #[Test]
    public function a_refund_that_keeps_failing_is_flagged_for_review(): void
    {
        $payment = $this->paidTicket();
        $refund = PaymentRefund::create([
            'tenant_id' => $payment->tenant_id, 'payment_id' => $payment->id, 'gateway' => 'thawani',
            'amount_minor' => 2500, 'status' => 'pending',
        ]);

        (new ProcessGatewayRefund($refund->id))->failed(new \RuntimeException('Simulated 503 after all retries'));

        $this->assertSame('failed', $refund->fresh()->status);
        $this->assertSame([true, 'refund_failed'], [$payment->fresh()->needs_review, $payment->fresh()->review_reason]);
        $this->assertContains('gateway_refund_failed', $this->types($payment));
    }

    #[Test]
    public function a_refund_job_killed_mid_request_is_never_resent_on_its_own(): void
    {
        $payment = $this->paidTicket();
        $refund = PaymentRefund::create([
            'tenant_id' => $payment->tenant_id, 'payment_id' => $payment->id, 'gateway' => 'thawani',
            'amount_minor' => 2500, 'status' => 'processing', 'attempts' => 1, 'data' => ['in_flight' => true],
        ]);

        (new ProcessGatewayRefund($refund->id))->failed(new \RuntimeException('Job timed out'));

        $this->assertSame('needs_review', $refund->fresh()->status);
        $this->assertSame([true, 'refund_needs_review'], [$payment->fresh()->needs_review, $payment->fresh()->review_reason]);
        $this->assertSame(0, $this->sentCount('create_refund'));
    }

    // ------------------------------------------------------------------ review fixes

    #[Test]
    public function cancelling_in_the_app_cancels_the_thawani_session_too(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent(null, ['price' => '5.000']);
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1,
        ], $user)->assertCreated();
        $this->get($res->json('payment_url'));

        $this->api('POST', '/api/v1/payments/cancel', ['transaction_id' => $res->json('transaction_id')], $user)
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, $this->sentCount('get_session'));
        $this->assertSame(1, $this->sentCount('cancel_session'));
        $this->assertSame('cancelled', $this->sessions['checkout_1']['payment_status']);
        $this->assertSame('cancelled', PaymentGatewaySession::sole()->status);
    }

    #[Test]
    public function a_late_capture_takes_back_the_points_given_back_on_cancel(): void
    {
        $event = $this->makeEvent(null, ['price' => '5.000']);
        $user = $this->makeUser();
        $points = app(\App\Services\Wallet\PointsService::class);
        $points->award($points->accountFor($user, $this->tenant), $this->tenant, 300, 'platform.points.earn', null, 'seed');
        $res = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'booking_date' => now()->addDay()->format('Y-m-d'), 'quantity' => 1, 'points_to_redeem' => 200,
        ], $user)->assertCreated();
        $this->assertSame(100, $points->accountFor($user, $this->tenant)->balance);
        $this->get($res->json('payment_url'));

        // Cancelled in the app while Thawani's cancel fails: points come back, the page stays payable.
        $this->fail['cancel_session'] = array_fill(0, 20, 500);
        $this->api('POST', '/api/v1/payments/cancel', ['transaction_id' => $res->json('transaction_id')], $user)->assertOk();
        $this->assertSame(300, $points->accountFor($user, $this->tenant)->fresh()->balance);

        // Paid anyway (3.000 after the points discount): completed, points taken again.
        $this->pay('checkout_1');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $payment = Payment::firstOrFail();
        $this->assertSame('completed', $payment->status);
        $this->assertFalse($payment->needs_review);
        $this->assertContains('points_retaken', $this->types($payment));
        // 300 − 200 retaken + 30 earned on 3.000 OMR.
        $this->assertSame(130, $points->accountFor($user, $this->tenant)->fresh()->balance);

        // A refund gives them back once.
        $this->actingAs($this->admin())->post(route('admin.payments.refund', $payment))->assertRedirect();
        $this->assertSame(300, $points->accountFor($user, $this->tenant)->fresh()->balance);
    }

    #[Test]
    public function a_second_paid_session_is_flagged_as_a_double_charge(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 6000);
        $this->get($topup['payment_url']);
        // Session 1 is about to expire: a new one is opened; Thawani refuses to cancel the old one.
        PaymentGatewaySession::sole()->forceFill(['expires_at' => now()->addMinute()])->save();
        $this->fail['cancel_session'] = array_fill(0, 20, 500);
        $this->get($topup['payment_url'])->assertRedirect('https://uatcheckout.thawani.om/pay/checkout_2?key=pk_test_public');

        $this->pay('checkout_2');
        app(ThawaniCheckout::class)->reconcile(Payment::firstOrFail(), 'system');
        $payment = Payment::firstOrFail();
        $this->assertSame(['completed', 'checkout_2'], [$payment->status, $payment->gateway_session_id]);

        // The old page is paid too.
        $this->pay('checkout_1');
        $this->travel(31)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $payment->refresh();
        $this->assertSame([true, 'duplicate_capture'], [$payment->needs_review, $payment->review_reason]);
        $this->assertContains('duplicate_capture', $this->types($payment));
        $this->assertSame(6000, $this->wallet($user)->balance_minor); // credited once
    }

    #[Test]
    public function webhooks_need_the_payment_tenants_secret(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        $this->pay('checkout_1');
        $other = $this->makeTenant('other-org');
        $body = ['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1']];

        // Signed correctly, but posted to another tenant's URL.
        $raw = json_encode($body);
        $ts = now()->timestamp;
        $this->call('POST', '/api/v1/payments/webhooks/thawani/other-org', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_THAWANI_SIGNATURE' => hash_hmac('sha256', $raw.'-'.$ts, 'whsec_test'),
            'HTTP_THAWANI_TIMESTAMP' => (string) $ts,
        ], $raw)->assertStatus(401);
        $this->assertSame('tenant_mismatch', PaymentWebhookEvent::sole()->last_error);

        // No webhook secret configured: rejected (polls and the reconciler still confirm payments).
        config(['services.thawani.webhook_secret' => null]);
        $this->signedWebhook(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1', 'n' => 2]], 'anything')->assertStatus(401);
        $this->assertSame('no_webhook_secret', PaymentWebhookEvent::latest('id')->first()->last_error);

        $this->assertSame('pending', Payment::first()->status);
        $this->assertSame(0, $this->sentCount('get_session'));
    }

    #[Test]
    public function a_session_is_checked_on_the_host_it_was_created_on(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        // Go-live switch while a UAT payment is open (the fake only answers on uatcheckout).
        $this->tenant->putSetting('payments.thawani.mode', 'production')->save();
        $this->pay('checkout_1');

        $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertJsonPath('data.status', 'completed');
    }

    #[Test]
    public function app_polls_do_not_push_back_the_reconcilers_schedule(): void
    {
        $user = $this->makeUser();
        $topup = $this->startTopup($user, 5000);
        $this->get($topup['payment_url']);
        $nextCheck = Payment::firstOrFail()->next_check_at;

        foreach (range(1, 3) as $i) {
            $this->travel(9)->seconds();
            $this->api('GET', "/api/v1/wallet/topups/{$topup['topup_id']}", [], $user)->assertOk();
        }

        $payment = Payment::firstOrFail();
        $this->assertSame(3, $this->sentCount('get_session'));
        $this->assertSame(0, $payment->check_count);
        $this->assertEquals($nextCheck, $payment->next_check_at);
        $this->assertNotContains('gateway_checked', $this->types($payment));
    }

    // ------------------------------------------------------------------ admin

    #[Test]
    public function admin_pages_show_the_gateway_records(): void
    {
        $payment = $this->paidTicket();
        $this->signedWebhook(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'checkout_1']]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.payments.show', $payment))->assertOk()
            ->assertSee('سجل الدفعة')
            ->assertSee('فُتحت جلسة دفع لدى البوابة')
            ->assertSee('اكتملت الدفعة')
            ->assertSee('create_session')
            ->assertSee('checkout.completed')
            ->assertDontSee(self::SECRET);

        $this->actingAs($admin)->get(route('admin.payments.gateway-log'))->assertOk()->assertSee('checkout.completed');
        $this->actingAs($admin)->get(route('admin.payments.gateway-log', ['tab' => 'calls']))->assertOk()->assertSee('get_session');
        $this->actingAs($admin)->post(route('admin.payments.reconcile', $payment))->assertSessionHas('success');
    }
}
