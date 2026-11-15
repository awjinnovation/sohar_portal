<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\PaymentGatewayCall;
use App\Models\PaymentGatewaySession;
use App\Models\User;
use App\Services\Payments\Gateway\AmwalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakeCardGateways;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Amwal paid inside the app with its Flutter SDK: POST /payments/amwal/sdk-session (docs/payments_amwal.md §7). */
class AmwalSdkSessionTest extends TestCase
{
    use FakeCardGateways, PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCardGateways(['payments.driver' => 'amwal']);
        $this->tenant->putSetting('payments.amwal.app_flow', 'sdk')->save();
    }

    private function topup(User $user, int $minor = 2500): array
    {
        return $this->api('POST', '/api/v1/wallet/topups', ['amount_minor' => $minor, 'idempotency_key' => uniqid('k', true)], $user)
            ->assertCreated()->json('data');
    }

    private function sdkSession(User $user, string $tx, string $lang = 'en')
    {
        $this->actingAs($user, 'sanctum');

        return $this->json('POST', '/api/v1/payments/amwal/sdk-session', ['transaction_id' => $tx], $this->tenantHeaders(null, ['Accept-Language' => $lang]));
    }

    #[Test]
    public function tenant_config_says_how_the_app_pays_amwal(): void
    {
        $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk()
            ->assertJsonPath('data.payments.gateways.0', ['id' => 'amwal', 'name' => ['ar' => 'أموال باي', 'en' => 'Amwal Pay'], 'app_flow' => 'sdk']);

        $this->tenant->putSetting('payments.gateways', ['thawani', 'amwal'])->save();
        $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk()
            ->assertJsonPath('data.payments.gateways.1.id', 'thawani')->assertJsonPath('data.payments.gateways.1.app_flow', 'link');
    }

    #[Test]
    public function it_opens_an_attempt_and_returns_what_the_sdk_needs(): void
    {
        $user = $this->makeUser();
        $topup = $this->topup($user);
        $tx = $topup['transaction_id']; // POST /wallet/topups → data.transaction_id

        $res = $this->sdkSession($user, $tx, 'ar')->assertOk()->assertJsonPath('success', true);
        $data = $res->json('data');
        $this->assertSame(['environment', 'session_token', 'merchant_id', 'terminal_id', 'currency', 'amount', 'merchant_reference', 'transaction_id', 'locale'], array_keys($data));
        $this->assertSame(['UAT', 'eyJhbGciOiJkaXIi.sdk-session-token', self::AMWAL_MID, self::AMWAL_TID, 'OMR', '2.500', $tx.'_1', 'ar'],
            [$data['environment'], $data['session_token'], $data['merchant_id'], $data['terminal_id'], $data['currency'], $data['amount'], $data['merchant_reference'], $data['locale']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['transaction_id']);
        $this->assertStringNotContainsString(self::AMWAL_KEY, $res->getContent());

        // The token request Amwal checks: merchantId + requestDateTime (ISO, UTC), signed; no customerId.
        $call = collect($this->gatewayCalls)->firstWhere('op', 'sdk_session');
        $this->assertSame(['merchantId', 'requestDateTime', 'secureHashValue'], array_keys($call['body']));
        $this->assertSame((int) self::AMWAL_MID, $call['body']['merchantId']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $call['body']['requestDateTime']);

        // The attempt is the same kind of row a SmartBox page open makes, so everything else works unchanged.
        $payment = Payment::where('transaction_id', $tx)->firstOrFail();
        $row = PaymentGatewaySession::where('payment_id', $payment->id)->sole();
        $this->assertSame([AmwalGateway::NAME, 'open', $tx.'_1', 2500, 'sdk', $data['transaction_id']],
            [$row->gateway, $row->status, $row->session_id, $row->amount_minor, $row->data['flow'], $row->data['sdk_transaction_id']]);
        $this->assertSame($tx.'_1', $payment->fresh()->gateway_session_id);
        $this->assertNotNull($payment->fresh()->next_check_at);
        $this->assertEqualsWithDelta(now()->addMinutes(120)->timestamp, $row->expires_at->timestamp, 5);

        // The token is not kept in the call log.
        $logged = PaymentGatewayCall::where('operation', 'sdk_session')->sole();
        $this->assertStringNotContainsString('sdk-session-token', (string) $logged->response_body);

        // A second call opens attempt 2 (like reopening the page).
        $this->sdkSession($user, $tx)->assertOk()->assertJsonPath('data.merchant_reference', $tx.'_2')->assertJsonPath('data.locale', 'en');

        // The SDK reports to the app only: polling asks Amwal, which knows the attempt by its merchant reference.
        $this->amwalPays($tx.'_2', '2.500');
        $this->getJson('/api/v1/wallet/topups/'.$topup['topup_id'], $this->tenantHeaders())->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(2500, $this->wallet($user)->balance_minor);
    }

    #[Test]
    public function it_works_for_ticket_payments_too(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent(null, ['price' => '1.250']);
        $tx = $this->api('POST', '/api/v1/payments/initialize', [
            'event_id' => $event->id, 'quantity' => 2, 'method' => 'gateway', 'booking_date' => \App\Support\TenantTime::today($this->tenant),
        ], $user)->assertCreated()->json('transaction_id');

        $this->sdkSession($user, $tx)->assertOk()->assertJsonPath('data.amount', '2.500')->assertJsonPath('data.merchant_reference', $tx.'_1');
    }

    #[Test]
    public function it_refuses_when_the_flow_is_off_or_the_payment_is_not_the_users_or_not_pending(): void
    {
        $user = $this->makeUser();
        $tx = $this->topup($user)['transaction_id'];

        // Flow off (the default): the app opens payment_url instead.
        $this->tenant->putSetting('payments.amwal.app_flow', null)->save();
        $this->sdkSession($user, $tx)->assertStatus(422)->assertJsonPath('code', 'AMWAL_SDK_NOT_AVAILABLE')->assertJsonPath('data.reason', 'flow_off');
        $this->tenant->putSetting('payments.amwal.app_flow', 'sdk')->save();

        // Someone else's payment, or an unknown one.
        $this->sdkSession($this->makeUser(), $tx)->assertNotFound();
        $this->sdkSession($user, 'TOPNOPE')->assertNotFound();

        // Not pending.
        Payment::where('transaction_id', $tx)->update(['status' => 'cancelled']);
        $this->sdkSession($user, $tx)->assertStatus(409)->assertJsonPath('code', 'PAYMENT_NOT_PENDING')->assertJsonPath('data.status', 'cancelled');

        // Another gateway.
        $this->tenant->putSetting('payments.driver', 'thawani')->save();
        $thawani = $this->topup($user)['transaction_id'];
        $this->sdkSession($user, $thawani)->assertStatus(422)->assertJsonPath('data.reason', 'not_amwal');

        $this->assertSame(0, PaymentGatewaySession::count());
        $this->assertSame(0, $this->gatewayCallCount('amwal', 'sdk_session'));
    }

    #[Test]
    public function no_token_or_no_credentials_means_no_attempt(): void
    {
        $user = $this->makeUser();
        $tx = $this->topup($user)['transaction_id'];

        $this->amwalSdkAnswer = null; // Amwal refuses
        $this->sdkSession($user, $tx)->assertStatus(422)->assertJsonPath('code', 'AMWAL_SDK_NOT_AVAILABLE')->assertJsonPath('data.reason', 'no_token');
        $this->amwalSdkAnswer = ['success' => true, 'message' => 'Success', 'data' => []];
        $this->sdkSession($user, $tx)->assertStatus(422)->assertJsonPath('data.reason', 'no_token');
        $this->assertSame(0, PaymentGatewaySession::count());

        config(['services.amwal.secure_key' => null]);
        $this->sdkSession($user, $tx)->assertStatus(422)->assertJsonPath('data.reason', 'not_configured');
        $this->assertSame('pending', Payment::where('transaction_id', $tx)->value('status'), 'the link still works');
    }

    #[Test]
    public function the_admin_sets_the_app_flow_next_to_the_amwal_fields(): void
    {
        $this->seedRoles();
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $tenant = $this->makeTenant('sdk-org');

        $this->actingAs($admin)->get(route('admin.tenants.edit', $tenant))->assertOk()
            ->assertSee('name="amwal_app_flow"', false)->assertSee('حزمة SDK داخل التطبيق');
        $save = fn (string $flow) => $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), ['status' => 'active', 'name' => $tenant->name, 'amwal_app_flow' => $flow]);

        $save('sdk')->assertSessionHasNoErrors();
        $this->assertSame('sdk', $tenant->fresh()->setting('payments.amwal.app_flow'));
        $save('link')->assertSessionHasNoErrors();
        $this->assertNull(data_get($tenant->fresh()->settings, 'payments.amwal.app_flow'), 'link is the default: not stored');
        $save('native')->assertSessionHasErrors('amwal_app_flow');
    }
}
