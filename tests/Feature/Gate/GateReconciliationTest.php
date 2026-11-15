<?php

namespace Tests\Feature\Gate;

use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Ai\ReceiptReader;
use App\Services\Gate\GateDay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakeCardGateways;

/** Admin → Gates → Morning reconciliation (docs/gate_reconciliation.md). */
class GateReconciliationTest extends GateTestCase
{
    use FakeCardGateways;

    private string $token;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        config(['auth.otp.test_code' => '123456']);
        $this->setUpCardGateways(['payments.driver' => 'thawani']);
        $this->tenant->putSetting('gate_sales.enabled', true)->putSetting('gate_sales.pos_enabled', true)
            ->putSetting('payments.gateways', ['thawani', 'amwal'])->save();

        // The vision model "reads" a clear approved slip of terminal 40011234 for the order total.
        $this->mock(ReceiptReader::class, fn ($m) => $m->shouldReceive('read')->andReturnUsing(fn () => ['status' => 'ok', 'model' => 'test', 'fields' => [
            'is_receipt' => true, 'transaction_status' => 'approved', 'amount' => '2.000', 'currency' => 'OMR',
            'date' => now('Asia/Muscat')->toDateString(), 'time' => '18:42', 'terminal_id' => '40011234', 'merchant_id' => '1',
            'merchant_name' => 'X', 'approval_code' => strtoupper(Str::random(6)), 'rrn' => (string) random_int(100000000000, 999999999999),
            'card_last4' => '4321', 'card_scheme' => 'VISA', 'legibility' => 'clear', 'screen_photo' => false, 'notes' => '',
        ]]));

        $gate = $this->makeGate(['can_sell' => true, 'code' => 'GA']);
        [, $this->token] = $this->pairDevice($gate);
        $staff = $this->makeUser('96890000001');
        $staff->forceFill(['name' => 'Salim Staff'])->save();
        $this->grantRole($staff, 'staff');
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000001'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $this->token)->assertOk();
        $this->event = $this->makeEvent(null, ['price' => '1.000', 'total_tickets' => 100]);
    }

    private function order(array $extra = []): array
    {
        return $this->gate('POST', '/api/v1/gate/sales/orders', array_merge([
            'event_id' => $this->event->id, 'quantity' => 2, 'buyer_phone' => '99112233', 'locale' => 'en', 'send_sms' => false,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra), $this->token)->assertCreated()->json('data.order');
    }

    private function open(array $order, string $gateway): Payment
    {
        $path = parse_url($order['payment_url'], PHP_URL_PATH);
        $this->get($this->post($path.'/checkout', ['lang' => 'en', 'gateway' => $gateway])->headers->get('Location'));

        return Payment::findOrFail(BoxOfficeOrder::findOrFail($order['id'])->payment_id);
    }

    private function photo(): UploadedFile
    {
        $img = imagecreatetruecolor(40, 40);
        imagestring($img, 2, 2, 2, Str::random(4), imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img);

        return UploadedFile::fake()->createWithContent('r.jpg', (string) ob_get_clean());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, 'admin');

        return $user;
    }

    /** A day of sales with one of every exception. */
    private function day(): array
    {
        $o = [];
        // Paid through Amwal and through Thawani.
        $o['amwal'] = $this->order();
        $p = $this->open($o['amwal'], 'amwal');
        $this->get($this->amwalReturnUrl($p, $p->transaction_id.'_1', '2.000'));
        $o['thawani'] = $this->order();
        $this->open($o['thawani'], 'thawani');
        $this->thawaniPays('checkout_1');
        $this->get('/payment/success?transaction_id='.BoxOfficeOrder::find($o['thawani']['id'])->payment->transaction_id);
        // Card terminal, verified receipt.
        $o['pos'] = $this->order(['payment_method' => 'pos']);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'])
            ->post("/api/v1/gate/sales/orders/{$o['pos']['id']}/receipt", ['image' => $this->photo(), 'idempotency_key' => 'r1'])->assertCreated();
        $this->flushHeaders();
        $this->actingAsTenant($this->tenant);
        // Card terminal order cancelled without a receipt.
        $o['pos_none'] = $this->order(['payment_method' => 'pos']);
        $this->gate('POST', "/api/v1/gate/sales/orders/{$o['pos_none']['id']}/cancel", [], $this->token)->assertOk();
        // Amwal paid a different amount (needs review).
        $o['mismatch'] = $this->order();
        $p = $this->open($o['mismatch'], 'amwal');
        $this->get($this->amwalReturnUrl($p, $p->transaction_id.'_1', '0.500'));
        // An Amwal page still open, nothing answered yet.
        $o['open'] = $this->order();
        $this->open($o['open'], 'amwal');

        foreach (['amwal', 'thawani', 'pos'] as $k) {
            $this->assertSame('paid', BoxOfficeOrder::find($o[$k]['id'])->status, $k);
        }

        return $o;
    }

    #[Test]
    public function the_morning_page_totals_the_day_by_gate_staff_and_channel_and_lists_the_exceptions(): void
    {
        $o = $this->day();
        $admin = $this->admin();
        $today = GateDay::today($this->tenant);

        // Default: yesterday's gate day (nothing then).
        $this->actingAs($admin)->get(route('admin.gates.reconciliation'))->assertOk()
            ->assertSee('value="'.\Carbon\Carbon::parse($today)->subDay()->toDateString().'"', false)
            ->assertSee('data-exceptions="0"', false);

        $page = $this->actingAs($admin)->get(route('admin.gates.reconciliation', ['date' => $today]))->assertOk();
        $html = $page->getContent();
        foreach (['link_amwal', 'link_thawani', 'pos'] as $ch) {
            $this->assertMatchesRegularExpression('/data-total="per_gate-'.$ch.'">\s*<span[^>]*>.*?2\.000/s', $html, $ch);
        }
        $page->assertSee('GA · ')->assertSee('Salim Staff')
            ->assertSee('data-terminal="40011234"', false)
            ->assertSee('data-exception="payment_review"', false)
            ->assertSee('data-exception="open_attempt"', false)
            ->assertSee('data-exception="pos_no_receipt"', false)
            ->assertSee(BoxOfficeOrder::find($o['mismatch']['id'])->reference)
            ->assertSee('data-check-count="1"', false);

        $report = app(\App\Services\Gate\GateReconciliation::class)->report($this->tenant, $today);
        $this->assertSame(['link_thawani' => 2000, 'link_amwal' => 2000, 'pos' => 2000, 'other' => 0], $report['totals']['amounts']);
        $this->assertSame(['captured_count' => 1, 'captured_minor' => 2000, 'refunded_count' => 0, 'refunded_minor' => 0], $report['portal']['thawani']);
        $this->assertSame(2, $report['portal']['amwal']['captured_count'], 'the mismatched attempt was captured too');
        $this->assertSame([['terminal_id' => '40011234', 'count' => 1, 'minor' => 2000, 'refunded_count' => 0, 'refunded_minor' => 0]], $report['terminals']);
        $this->assertSame(['payment_review', 'open_attempt', 'pos_no_receipt'], array_column($report['exceptions'], 'kind'));

        // CSV: every order of the day, with its channel and exceptions.
        $csv = $this->actingAs($admin)->get(route('admin.gates.reconciliation.export', ['date' => $today]))->assertOk()->streamedContent();
        $this->assertStringContainsString('reference,created_at,gate,staff,order_status,channel,gateway,transaction_id', $csv);
        $this->assertSame(6 + 1, substr_count(trim($csv), "\n") + 1);
        $this->assertMatchesRegularExpression('/'.BoxOfficeOrder::find($o['pos']['id'])->reference.',.*,paid,pos,.*,40011234,/', $csv);
        $this->assertStringContainsString('payment_review', $csv);

        // Mark the cancelled card order checked: it leaves the list.
        $this->actingAs($admin)->post(route('admin.gates.reconciliation.checked', BoxOfficeOrder::find($o['pos_none']['id'])), ['date' => $today, 'note' => 'Not charged'])
            ->assertRedirect(route('admin.gates.reconciliation', ['date' => $today]));
        $this->assertNotContains('pos_no_receipt', array_column(app(\App\Services\Gate\GateReconciliation::class)->report($this->tenant, $today)['exceptions'], 'kind'));
    }

    #[Test]
    public function check_now_asks_the_gateway_about_the_days_open_attempts(): void
    {
        $o = $this->day();
        $open = BoxOfficeOrder::find($o['open']['id'])->payment;
        $this->amwalPays($open->transaction_id.'_1', '2.000');
        $calls = $this->gatewayCallCount('amwal', 'get_transactions');

        $this->actingAs($this->admin())->post(route('admin.gates.reconciliation.check', ['date' => GateDay::today($this->tenant)]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertGreaterThan($calls, $this->gatewayCallCount('amwal', 'get_transactions'));
        $this->assertSame('completed', $open->fresh()->status);
        $this->assertSame('paid', BoxOfficeOrder::find($o['open']['id'])->status);
        $this->assertTrue(PaymentEvent::where('payment_id', $open->id)->where('type', 'check_requested')->exists());
    }

    #[Test]
    public function gate_managers_and_finance_open_it_others_do_not(): void
    {
        $this->day();
        $content = User::factory()->create();
        $this->grantRole($content, 'content-manager');
        $this->actingAs($content)->get(route('admin.gates.reconciliation'))->assertForbidden();
        $this->actingAs($content)->post(route('admin.gates.reconciliation.check'))->assertForbidden();
        $this->actingAs($content)->get(route('admin.gates.reconciliation.export'))->assertForbidden();

        // Finance (view payments, not manage gates): the page with payment links, no order links.
        $finance = User::factory()->create();
        $this->grantRole($finance, 'finance-manager');
        $this->actingAs($finance)->get(route('admin.gates.reconciliation', ['date' => GateDay::today($this->tenant)]))->assertOk()
            ->assertSee('/admin/payments/', false)->assertDontSee('/admin/gates/sales/', false)
            ->assertSee('مطابقة مبيعات البوابات');
    }
}
