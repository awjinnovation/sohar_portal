<?php

namespace Tests\Feature\Gate;

use App\Jobs\SendOtpSms;
use App\Models\BoxOfficeOrder;
use App\Models\BoxOfficeReceipt;
use App\Models\Event;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Gate\CardTerminalService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/** Gate box office paid on the organizer's card terminal (docs/gate_sales_contract.md §9). */
class BoxOfficeCardTerminalTest extends GateTestCase
{
    /** What the fake vision model "reads" from the next photo (null = OpenAI answers 500). */
    private ?array $reads = null;

    private int $aiCalls = 0;

    private int $shade = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        config(['auth.otp.test_code' => '123456', 'services.openai.key' => 'sk-test', 'services.openai.retries' => 0, 'ai.receipt.retries' => 0]);
        $this->tenant->putSetting('gate_sales.enabled', true)->putSetting('gate_sales.pos_enabled', true)->save();

        Http::fake(['*/responses' => function () {
            $this->aiCalls++;
            if ($this->reads === null) {
                return Http::response(['error' => ['message' => 'down']], 500);
            }

            return Http::response([
                'id' => 'resp_1', 'status' => 'completed', 'model' => 'gpt-6.1-sol',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->reads)]]]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 150],
            ]);
        }]);
    }

    // ------------------------------------------------------------------ helpers

    /** A clear, approved slip of $amount OMR printed today (override any field). */
    private function slip(string $amount = '1.000', array $over = []): array
    {
        return array_merge([
            'is_receipt' => true, 'transaction_status' => 'approved', 'amount' => $amount, 'currency' => 'OMR',
            'date' => now('Asia/Muscat')->toDateString(), 'time' => '18:42', 'terminal_id' => '40011234', 'merchant_id' => '000123456789',
            'merchant_name' => 'SUHAR FESTIVAL', 'approval_code' => strtoupper(Str::random(6)), 'rrn' => (string) random_int(100000000000, 999999999999),
            'card_last4' => '4321', 'card_scheme' => 'VISA', 'legibility' => 'clear', 'screen_photo' => false, 'notes' => '',
        ], $over);
    }

    /** A real JPEG, different every call unless $bytes are given. */
    private function photo(?string $bytes = null): UploadedFile
    {
        if ($bytes === null) {
            $img = imagecreatetruecolor(120, 160);
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
            imagestring($img, 5, 8, 8, 'SLIP '.(++$this->shade).' '.Str::random(6), imagecolorallocate($img, 0, 0, 0));
            ob_start();
            imagejpeg($img);
            $bytes = (string) ob_get_clean();
        }

        return UploadedFile::fake()->createWithContent('receipt.jpg', $bytes);
    }

    /** @return array{0: GateDevice, 1: string, 2: Gate} */
    private function sellingDevice(): array
    {
        $gate = $this->makeGate(['can_sell' => true]);
        [$device, $token] = $this->pairDevice($gate);
        $staff = User::where('phone_number', '96890000001')->first() ?? $this->makeUser('96890000001');
        $this->grantRole($staff, 'staff');
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000001'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $token)->assertOk();

        return [$device, $token, $gate];
    }

    private function event(array $attrs = []): Event
    {
        return $this->makeEvent(null, array_merge(['price' => '0.500', 'total_tickets' => 100], $attrs));
    }

    /** POST /orders with payment_method pos (2 × 0.500 = 1.000 by default). */
    private function posOrder(string $token, Event $event, array $extra = [])
    {
        return $this->gate('POST', '/api/v1/gate/sales/orders', array_merge([
            'event_id' => $event->id, 'quantity' => 2, 'buyer_phone' => '99112233', 'buyer_name' => 'Salim',
            'locale' => 'en', 'send_sms' => true, 'payment_method' => 'pos', 'idempotency_key' => (string) Str::uuid(),
        ], $extra), $token);
    }

    private function sendReceipt(string $token, int $orderId, ?UploadedFile $photo = null, ?string $key = null)
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json', 'Accept-Language' => 'en'])
            ->post("/api/v1/gate/sales/orders/$orderId/receipt", ['image' => $photo ?? $this->photo(), 'idempotency_key' => $key ?? (string) Str::uuid()]);
        $this->flushHeaders();
        $this->actingAsTenant($this->tenant);

        return $res;
    }

    private function codes($res): array
    {
        return array_column($res->json('data.receipt.reasons') ?? [], 'code');
    }

    // ------------------------------------------------------------------ tests

    #[Test]
    public function a_card_terminal_order_needs_the_setting_has_no_link_and_sends_no_sms(): void
    {
        Queue::fake();
        [, $token] = $this->sellingDevice();
        $event = $this->event();

        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()
            ->assertJsonPath('data.settings.sales.pos_enabled', true)
            ->assertJsonPath('data.settings.sales.pos_accept_unverified', true)
            ->assertJsonPath('data.settings.sales.pos_max_attempts', CardTerminalService::MAX_ATTEMPTS)
            ->assertJsonMissingPath('data.settings.sales.pos_terminal_ids');

        $res = $this->posOrder($token, $event)->assertCreated()
            ->assertJsonPath('data.order.payment_method', 'pos')
            ->assertJsonPath('data.order.payment_url', null)
            ->assertJsonPath('data.order.qr_payload', null)
            ->assertJsonPath('data.order.sms_count', 0)
            ->assertJsonPath('data.order.receipt', null)
            ->assertJsonPath('data.order.receipt_attempts', 0)
            ->assertJsonPath('data.order.receipt_attempts_max', CardTerminalService::MAX_ATTEMPTS);
        Queue::assertNotPushed(SendOtpSms::class);

        // Nobody can pay it online: the public link does not exist.
        $order = BoxOfficeOrder::findOrFail($res->json('data.order.id'));
        $this->get('/pay/'.$order->public_token)->assertNotFound();
        $this->post('/pay/'.$order->public_token.'/checkout')->assertNotFound();

        $this->tenant->putSetting('gate_sales.pos_enabled', false)->save();
        $this->posOrder($token, $event)->assertForbidden()->assertJsonPath('code', 'POS_DISABLED');
    }

    #[Test]
    public function a_verified_receipt_pays_the_order_and_issues_the_tickets(): void
    {
        [, $token] = $this->sellingDevice();
        $orderId = $this->posOrder($token, $this->event())->json('data.order.id');
        $this->reads = $this->slip('1.000', ['rrn' => '531122334455', 'approval_code' => 'A1B2C3']);
        $key = (string) Str::uuid();

        $res = $this->sendReceipt($token, $orderId, null, $key)->assertCreated()
            ->assertJsonPath('data.receipt.verdict', 'verified')
            ->assertJsonPath('data.receipt.accepted', true)
            ->assertJsonPath('data.receipt.reasons', [])
            ->assertJsonPath('data.receipt.amount_minor', 1000)
            ->assertJsonPath('data.receipt.rrn', '531122334455')
            ->assertJsonPath('data.receipt.card_last4', '4321')
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.needs_review', false)
            ->assertJsonCount(2, 'data.order.tickets');

        $order = BoxOfficeOrder::findOrFail($orderId);
        $payment = Payment::findOrFail($order->payment_id);
        $this->assertSame(Payment::METHOD_POS, $payment->method);
        $this->assertSame('completed', $payment->status);
        $this->assertSame('card_pos', $payment->payment_method);
        $this->assertNull($payment->gateway);
        $buyer = User::where('phone_number', '96899112233')->firstOrFail();
        $this->assertSame(2, Ticket::where('user_id', $buyer->id)->where('transaction_id', $payment->transaction_id)->count());
        $receipt = BoxOfficeReceipt::firstOrFail();
        Storage::disk('local')->assertExists($receipt->path);
        $this->assertSame('rrn:531122334455', $receipt->rrn_key);

        // The model only transcribes: it is never told the amount to find.
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/responses')
            && ! str_contains($r['instructions'], '1.000')
            && $r['text']['format']['type'] === 'json_schema'
            && $r['input'][0]['content'][1]['type'] === 'input_image');

        // Replays answer 200 with the same receipt and no new AI call; a new photo is refused.
        $calls = $this->aiCalls;
        $this->sendReceipt($token, $orderId, null, $key)->assertOk()->assertJsonPath('data.receipt.id', $res->json('data.receipt.id'));
        $this->assertSame($calls, $this->aiCalls);
        $this->sendReceipt($token, $orderId)->assertStatus(409)->assertJsonPath('code', 'ORDER_NOT_PENDING');

        // The tickets can be admitted from the gate as for a link sale.
        $this->gate('POST', "/api/v1/gate/sales/orders/$orderId/admit", [], $token)->assertOk()->assertJsonCount(2, 'data.results');
    }

    #[Test]
    public function a_receipt_that_contradicts_the_order_is_rejected_and_can_be_retaken(): void
    {
        [, $token] = $this->sellingDevice();
        $orderId = $this->posOrder($token, $this->event())->json('data.order.id');

        $this->reads = $this->slip('1.500');
        $res = $this->sendReceipt($token, $orderId)->assertCreated()
            ->assertJsonPath('data.receipt.verdict', 'rejected')
            ->assertJsonPath('data.receipt.accepted', false)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.receipt_attempts', 1);
        $this->assertSame(['amount_mismatch'], $this->codes($res));
        $this->assertSame('The receipt shows 1.500; the order total is 1.000.', $res->json('data.receipt.reasons.0.text'));

        foreach ([
            [['transaction_status' => 'declined'], 'not_approved'],
            [['is_receipt' => false], 'not_a_receipt'],
            [['date' => now('Asia/Muscat')->subDays(3)->toDateString()], 'wrong_date'],
        ] as [$over, $code]) {
            $this->reads = $this->slip('1.000', $over);
            $this->assertContains($code, $this->codes($this->sendReceipt($token, $orderId)->assertJsonPath('data.receipt.verdict', 'rejected')));
        }

        // The fifth and last attempt is a good photo.
        $this->reads = $this->slip('1.000');
        $this->sendReceipt($token, $orderId)->assertCreated()
            ->assertJsonPath('data.receipt.verdict', 'verified')
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.receipt_attempts', CardTerminalService::MAX_ATTEMPTS);

        $this->reads = $this->slip('1.000', ['currency' => 'USD']);
        $other = $this->posOrder($token, $this->event())->json('data.order.id');
        $this->assertSame(['currency_mismatch'], $this->codes($this->sendReceipt($token, $other)));
    }

    #[Test]
    public function the_attempts_per_order_are_limited_and_link_orders_take_no_receipt(): void
    {
        [, $token] = $this->sellingDevice();
        $event = $this->event();
        $orderId = $this->posOrder($token, $event)->json('data.order.id');
        $this->reads = $this->slip('9.000');
        for ($i = 0; $i < CardTerminalService::MAX_ATTEMPTS; $i++) {
            $this->sendReceipt($token, $orderId)->assertCreated();
        }
        $this->sendReceipt($token, $orderId)->assertStatus(429)->assertJsonPath('code', 'RECEIPT_ATTEMPTS');

        $linkId = $this->posOrder($token, $event, ['payment_method' => 'link', 'send_sms' => false])->json('data.order.id');
        $this->sendReceipt($token, $linkId)->assertStatus(409)->assertJsonPath('code', 'WRONG_PAYMENT_METHOD');

        // Not an image.
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->post("/api/v1/gate/sales/orders/$orderId/receipt", ['image' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), 'idempotency_key' => 'k'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_receipt_cannot_pay_two_orders(): void
    {
        [, $token] = $this->sellingDevice();
        $event = $this->event();
        $first = $this->posOrder($token, $event)->json('data.order.id');
        $second = $this->posOrder($token, $event)->json('data.order.id');
        $third = $this->posOrder($token, $event)->json('data.order.id');
        $reference = BoxOfficeOrder::findOrFail($first)->reference;

        $slip = $this->slip('1.000', ['rrn' => '777766665555', 'approval_code' => 'Z9Y8X7']);
        $this->reads = $slip;
        $photo = $this->photo();
        $bytes = (string) file_get_contents($photo->getRealPath());
        $this->sendReceipt($token, $first, $photo)->assertJsonPath('data.order.status', 'paid');

        // Another photo of the same slip (same reference number).
        $res = $this->sendReceipt($token, $second)->assertJsonPath('data.receipt.verdict', 'rejected')->assertJsonPath('data.order.status', 'pending');
        $this->assertSame(['duplicate_receipt'], $this->codes($res));
        $this->assertSame(['order_id' => $first, 'reference' => $reference], $res->json('data.receipt.duplicate_of'));
        $this->assertStringContainsString($reference, $res->json('data.receipt.reasons.0.text'));

        // Same approval code, terminal, amount and date, reference number not readable.
        $this->reads = array_merge($slip, ['rrn' => null]);
        $this->assertContains('duplicate_receipt', $this->codes($this->sendReceipt($token, $second)));

        // The very same image file.
        $this->reads = $this->slip('1.000');
        $res = $this->sendReceipt($token, $third, $this->photo($bytes))->assertJsonPath('data.receipt.verdict', 'rejected');
        $this->assertContains('duplicate_image', $this->codes($res));
        $this->assertSame(1, BoxOfficeOrder::where('status', 'paid')->count());
    }

    #[Test]
    public function an_unchecked_receipt_is_accepted_and_flagged_or_held_for_a_supervisor(): void
    {
        [, $token] = $this->sellingDevice();
        $event = $this->event();

        // The AI is down: accepted, flagged for review (default).
        $this->reads = null;
        $orderId = $this->posOrder($token, $event)->json('data.order.id');
        $res = $this->sendReceipt($token, $orderId)->assertCreated()
            ->assertJsonPath('data.receipt.verdict', 'review')
            ->assertJsonPath('data.receipt.ai', 'unavailable')
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.needs_review', true);
        $this->assertSame(['ai_unavailable'], $this->codes($res));

        // Readable but without a reference number or approval code: reuse cannot be checked.
        $this->reads = $this->slip('1.000', ['rrn' => null, 'approval_code' => null, 'transaction_status' => 'approved']);
        $orderId = $this->posOrder($token, $event)->json('data.order.id');
        $this->assertSame(['reference_unreadable'], $this->codes($this->sendReceipt($token, $orderId)->assertJsonPath('data.order.needs_review', true)));

        // Held instead when the organizer wants a supervisor to decide.
        $this->tenant->putSetting('gate_sales.pos_accept_unverified', false)->save();
        $this->reads = $this->slip('1.000', ['amount' => null]);
        $orderId = $this->posOrder($token, $event)->json('data.order.id');
        $this->sendReceipt($token, $orderId)->assertJsonPath('data.receipt.verdict', 'review')
            ->assertJsonPath('data.receipt.accepted', false)
            ->assertJsonPath('data.order.status', 'pending');
    }

    #[Test]
    public function the_terminal_list_and_an_expired_order_are_handled(): void
    {
        [, $token] = $this->sellingDevice();
        $event = $this->event();
        $this->tenant->putSetting('gate_sales.pos_terminal_ids', '40011234, tid-5000')->save();

        $this->reads = $this->slip('1.000', ['terminal_id' => '99990000']);
        $orderId = $this->posOrder($token, $event)->json('data.order.id');
        $this->assertSame(['unknown_terminal'], $this->codes($this->sendReceipt($token, $orderId)));
        $this->reads = $this->slip('1.000', ['terminal_id' => 'TID 5000']);
        $this->sendReceipt($token, $orderId)->assertJsonPath('data.receipt.verdict', 'verified');

        // The card was charged just before the order expired: still paid while seats are free.
        $this->reads = $this->slip('1.000', ['terminal_id' => '40011234']);
        $late = $this->posOrder($token, $event)->json('data.order.id');
        $this->travel(31)->minutes();
        $this->gate('GET', "/api/v1/gate/sales/orders/$late", [], $token)->assertJsonPath('data.order.status', 'expired');
        $this->sendReceipt($token, $late)->assertCreated()->assertJsonPath('data.order.status', 'paid')->assertJsonCount(2, 'data.order.tickets');
    }

    #[Test]
    public function a_supervisor_approves_reviews_and_voids_card_sales_in_the_admin(): void
    {
        [, $token] = $this->sellingDevice();
        $event = $this->event();
        $admin = User::factory()->create();
        $this->grantRole($admin, 'admin');

        // Rejected (amount misread) → approved by a supervisor.
        $this->reads = $this->slip('1.100');
        $rejectedId = $this->posOrder($token, $event)->json('data.order.id');
        $this->sendReceipt($token, $rejectedId);
        $rejected = BoxOfficeOrder::findOrFail($rejectedId);
        $receipt = $rejected->latestReceipt();

        $this->actingAs($admin)->get(route('admin.gates.sales.show', $rejected))->assertOk()
            ->assertSee('data-verdict="rejected"', false)->assertSee('data-reason="amount_mismatch"', false)
            ->assertSee(route('admin.gates.sales.receipt-approve', [$rejected, $receipt]), false);
        $this->actingAs($admin)->get(route('admin.gates.sales.receipt-image', [$rejected, $receipt]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($admin)->post(route('admin.gates.sales.receipt-approve', [$rejected, $receipt]), ['note' => 'Amount checked on the terminal'])
            ->assertRedirect(route('admin.gates.sales.show', $rejected))->assertSessionHas('success');
        $rejected->refresh();
        $this->assertSame('paid', $rejected->status);
        $this->assertFalse($rejected->needs_review);
        $this->assertSame('approved', $receipt->fresh()->decision);
        $this->assertSame(2, $rejected->issuedTickets()->count());

        // Accepted with review → listed, then cleared.
        $this->reads = null;
        $reviewId = $this->posOrder($token, $event)->json('data.order.id');
        $this->sendReceipt($token, $reviewId);
        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['date' => 'all']))->assertOk()
            ->assertSee('data-review-due="1"', false)->assertSee('data-method="pos"', false);
        $this->actingAs($admin)->get(route('admin.gates.sales.index', ['date' => 'all', 'review' => 1, 'method' => 'pos']))->assertOk()
            ->assertSee(BoxOfficeOrder::findOrFail($reviewId)->reference);
        $this->actingAs($admin)->post(route('admin.gates.sales.review', $reviewId))->assertSessionHas('success');
        $this->assertFalse(BoxOfficeOrder::findOrFail($reviewId)->needs_review);

        // Voided: tickets cancelled, payment and order refunded.
        $this->actingAs($admin)->post(route('admin.gates.sales.void', $reviewId), ['reason' => 'Receipt from another shop'])->assertSessionHas('success');
        $voided = BoxOfficeOrder::findOrFail($reviewId);
        $this->assertSame('refunded', $voided->status);
        $this->assertSame('refunded', Payment::findOrFail($voided->payment_id)->status);
        $this->assertSame(0, Ticket::where('transaction_id', Payment::findOrFail($voided->payment_id)->transaction_id)->where('status', 'active')->count());

        // Settings page shows the card terminal options.
        $this->actingAs($admin)->get(route('admin.branding.edit'))->assertOk()->assertSee('gate_sales][pos_enabled', false)->assertSee('gate_sales][pos_terminal_ids', false);
        $this->actingAs($admin)->put(route('admin.branding.update'), ['s' => ['gate_sales' => ['pos_terminal_ids' => 'bad<script>']]])
            ->assertSessionHasErrors('s.gate_sales.pos_terminal_ids');
    }

    #[Test]
    public function receipt_fields_are_parsed_strictly(): void
    {
        $this->assertSame(1500, CardTerminalService::amountMinor('1.500', 3));
        $this->assertSame(1250000, CardTerminalService::amountMinor('OMR 1,250.000', 3));
        $this->assertSame(2000, CardTerminalService::amountMinor('2', 3));
        $this->assertNull(CardTerminalService::amountMinor('1,500', 3), 'ambiguous separator');
        $this->assertNull(CardTerminalService::amountMinor('1.5000', 3), 'more decimals than the currency has');
        $this->assertNull(CardTerminalService::amountMinor(null, 3));
        $this->assertTrue(CardTerminalService::currencyMatches('R.O.', 'OMR'));
        $this->assertTrue(CardTerminalService::currencyMatches('ر.ع', 'OMR'));
        $this->assertFalse(CardTerminalService::currencyMatches('AED', 'OMR'));
        $this->assertSame('2026-11-05', CardTerminalService::date('2026-11-05'));
        $this->assertNull(CardTerminalService::date('2026-02-30'));
        $this->assertNull(CardTerminalService::date('05/11/26'));
        $this->assertSame('4321', CardTerminalService::last4('**** **** **** 4321'));
        $this->assertSame(['rrn:123456789012', 'auth:-|AB12|1000|2026-11-05'],
            CardTerminalService::keys(['rrn' => '123456789012', 'approval_code' => 'AB12', 'terminal_id' => null, 'amount_minor' => 1000, 'receipt_date' => '2026-11-05']));
    }
}
