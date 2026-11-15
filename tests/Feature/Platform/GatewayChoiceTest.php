<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakeCardGateways;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * Gateways switched on per tenant (payments.gateways) and the visitor's
 * choice (docs/payments_amwal.md §5): tenant config, checkout, top-ups,
 * booking pages and the admin form.
 */
class GatewayChoiceTest extends TestCase
{
    use FakeCardGateways, PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCardGateways(['payments.driver' => 'thawani']);
    }

    private function both(string $default = 'thawani'): void
    {
        $this->tenant->putSetting('payments.driver', $default)->putSetting('payments.gateways', ['thawani', 'amwal'])->save();
    }

    private function checkout(User $user, array $extra = [])
    {
        $event = $this->makeEvent(null, ['price' => '2.500']);

        return $this->api('POST', '/api/v1/payments/initialize', array_merge([
            'event_id' => $event->id, 'quantity' => 1, 'method' => 'gateway', 'booking_date' => \App\Support\TenantTime::today($this->tenant),
        ], $extra), $user);
    }

    // ------------------------------------------------------------------ manager

    #[Test]
    public function without_the_setting_only_the_default_is_on(): void
    {
        $m = app(PaymentGatewayManager::class);
        $this->assertSame(['thawani'], $m->enabled($this->tenant));
        $this->assertFalse($m->offersChoice($this->tenant));
        $this->assertSame('thawani', $m->resolve($this->tenant, null));
        $this->assertSame('thawani', $m->resolve($this->tenant, 'THAWANI'));

        $this->tenant->putSetting('payments.driver', 'amwal')->save();
        $this->assertSame(['amwal'], $m->enabled($this->tenant->fresh()));

        $this->both('amwal');
        $tenant = $this->tenant->fresh();
        $this->assertSame(['amwal', 'thawani'], $m->enabled($tenant), 'default first');
        $this->assertSame('thawani', $m->resolve($tenant, 'thawani'));

        // A default outside the list (platform default changed later): the first one on.
        $tenant->putSetting('payments.driver', null)->putSetting('payments.gateways', ['amwal'])->save();
        config(['payments.driver' => 'thawani']);
        $this->assertSame(['amwal'], $m->enabled($tenant->fresh()));
    }

    // ------------------------------------------------------------------ tenant config

    #[Test]
    public function tenant_config_lists_the_gateways_that_are_on_and_changes_at_once(): void
    {
        $first = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk()
            ->assertJsonPath('data.payments', [
                'gateways' => [['id' => 'thawani', 'name' => ['ar' => 'ثواني', 'en' => 'Thawani']]],
                'default_gateway' => 'thawani',
            ]);

        $this->both('amwal');
        $second = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders(null, ['If-None-Match' => $first->headers->get('ETag')]))
            ->assertOk() // not 304: the admin change shows on the next request
            ->assertJsonPath('data.payments', [
                'gateways' => [
                    ['id' => 'amwal', 'name' => ['ar' => 'أموال باي', 'en' => 'Amwal Pay']],
                    ['id' => 'thawani', 'name' => ['ar' => 'ثواني', 'en' => 'Thawani']],
                ],
                'default_gateway' => 'amwal',
            ]);
        $this->assertNotSame($first->headers->get('ETag'), $second->headers->get('ETag'));
        $second->assertJsonMissingPath('data.payments.driver')->assertJsonMissingPath('data.payments.billing');
    }

    // ------------------------------------------------------------------ checkout

    #[Test]
    public function checkout_uses_the_requested_gateway_or_the_default(): void
    {
        $this->both();
        $user = $this->makeUser();

        $this->checkout($user)->assertCreated()
            ->assertJsonPath('gateway', 'thawani')->assertJsonPath('data.gateway', 'thawani');
        $amwal = $this->checkout($user, ['gateway' => 'amwal', 'idempotency_key' => 'k-amwal'])->assertCreated()
            ->assertJsonPath('gateway', 'amwal')->assertJsonPath('data.gateway', 'amwal');
        $this->assertSame('amwal', Payment::where('transaction_id', $amwal->json('transaction_id'))->value('gateway'));

        // The payment page of an Amwal payment is the SmartBox page.
        $this->get($amwal->json('payment_url'))->assertOk()->assertSee('SmartBox.js', false);

        // A retry with the same key returns the original payment, whatever gateway it names.
        $this->checkout($user, ['gateway' => 'thawani', 'idempotency_key' => 'k-amwal'])->assertOk()
            ->assertJsonPath('transaction_id', $amwal->json('transaction_id'))->assertJsonPath('gateway', 'amwal');
        $this->checkout($user, ['gateway' => 'bankmuscat', 'idempotency_key' => 'k-amwal'])->assertOk()
            ->assertJsonPath('transaction_id', $amwal->json('transaction_id'));
    }

    #[Test]
    public function a_gateway_that_is_off_is_refused_with_gateway_not_available(): void
    {
        $user = $this->makeUser();
        $count = Payment::count();

        $this->checkout($user, ['gateway' => 'amwal'])->assertStatus(422)->assertExactJson([
            'success' => false,
            'message' => __('platform.errors.gateway_not_available'),
            'code' => 'GATEWAY_NOT_AVAILABLE',
            'data' => ['gateway' => 'amwal', 'available' => ['thawani']],
        ]);
        $this->checkout($user, ['gateway' => 'nope'])->assertStatus(422)->assertJsonPath('code', 'GATEWAY_NOT_AVAILABLE');
        $this->assertSame($count, Payment::count());

        // Old builds send no gateway: unchanged.
        $this->checkout($user)->assertCreated()->assertJsonPath('gateway', 'thawani')->assertJsonPath('redirect_required', true);
    }

    #[Test]
    public function wallet_checkouts_ignore_the_gateway(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 10000);

        $this->checkout($user, ['method' => 'wallet', 'gateway' => 'amwal', 'idempotency_key' => 'w-1'])->assertCreated()
            ->assertJsonPath('status', 'completed')->assertJsonPath('gateway', null);
    }

    // ------------------------------------------------------------------ top-ups

    #[Test]
    public function topups_take_an_optional_gateway(): void
    {
        $user = $this->makeUser();
        $body = fn (array $extra) => $extra + ['amount_minor' => 5000, 'idempotency_key' => uniqid('t', true)];

        $this->api('POST', '/api/v1/wallet/topups', $body([]), $user)->assertCreated()->assertJsonPath('data.gateway', 'thawani');
        $this->api('POST', '/api/v1/wallet/topups', $body(['gateway' => 'amwal']), $user)->assertStatus(422)->assertJsonPath('code', 'GATEWAY_NOT_AVAILABLE');

        $this->both();
        $res = $this->api('POST', '/api/v1/wallet/topups', $body(['gateway' => 'amwal', 'idempotency_key' => 'top-1']), $user)->assertCreated()
            ->assertJsonPath('data.gateway', 'amwal');
        $this->assertStringStartsWith('TOP', $res->json('data.transaction_id'));
        $this->api('POST', '/api/v1/wallet/topups', $body(['gateway' => 'thawani', 'idempotency_key' => 'top-1']), $user)->assertOk()
            ->assertJsonPath('data.topup_id', $res->json('data.topup_id'))->assertJsonPath('data.gateway', 'amwal');
        $this->getJson('/api/v1/wallet/topups/'.$res->json('data.topup_id'), $this->tenantHeaders())->assertOk()->assertJsonPath('data.gateway', 'amwal');
    }

    // ------------------------------------------------------------------ booking pages

    #[Test]
    public function the_booking_site_api_offers_both_gateways_and_takes_the_choice(): void
    {
        $this->tenant->putSetting('modules.web_booking', true)->save();
        $event = $this->makeEvent(null, ['price' => '1.000']);
        $type = $event->ticketTypes()->firstOrFail();
        $user = $this->makeUser();

        $this->getJson("/api/v1/booking/events/{$event->id}?lang=en")->assertOk()->assertJsonPath('data.payments.gateways', []);

        $this->both();
        $this->getJson("/api/v1/booking/events/{$event->id}?lang=ar")->assertOk()
            ->assertJsonPath('data.payments.gateways', [['id' => 'thawani', 'name' => 'ثواني'], ['id' => 'amwal', 'name' => 'أموال باي']]);

        $order = ['items' => [['ticket_type_id' => $type->id, 'quantity' => 1]], 'form_key' => 'f-1', 'booking_date' => \App\Support\TenantTime::today($this->tenant)];
        $this->actingAs($user, 'sanctum');
        $a = $this->postJson("/api/v1/booking/events/{$event->id}/checkout", $order + ['gateway' => 'amwal'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/v1/booking/events/{$event->id}/checkout", $order)->assertOk();
        $this->assertSame(['amwal', 'thawani'], Payment::orderBy('id')->pluck('gateway')->all(), 'another gateway, same form = another payment');
        $this->postJson("/api/v1/booking/events/{$event->id}/checkout", $order + ['gateway' => 'amwal'])->assertOk()
            ->assertJsonPath('data.payment_url', fn ($u) => str_contains($u, Payment::where('gateway', 'amwal')->value('transaction_id')));
        $this->assertSame(2, Payment::count(), 'a double tap reuses the payment');
        $this->postJson("/api/v1/booking/events/{$event->id}/checkout", $order + ['gateway' => 'bankmuscat'])->assertStatus(422)
            ->assertJsonPath('code', 'GATEWAY_NOT_AVAILABLE');
        $this->assertNotNull($a);
    }

    #[Test]
    public function the_booking_page_shows_one_button_per_gateway(): void
    {
        $this->tenant->putSetting('modules.web_booking', true)->save();
        $event = $this->makeEvent(null, ['price' => '1.000']);
        $type = $event->ticketTypes()->firstOrFail();
        $visitor = $this->makeUser('96899001122');

        $this->actingAs($visitor, 'visitor')->get("/e/{$event->id}?lang=en")->assertOk()
            ->assertSee('Pay by card')->assertDontSee('data-gateway="amwal"', false);

        $this->both();
        $this->actingAs($visitor, 'visitor')->get("/e/{$event->id}?lang=en")->assertOk()
            ->assertSee('data-gateway="thawani"', false)->assertSee('data-gateway="amwal"', false)->assertSee('Pay with Amwal Pay');

        $res = $this->actingAs($visitor, 'visitor')->post("/e/{$event->id}/checkout", [
            'items' => [$type->id => 1], 'form_key' => 'page-1', 'gateway' => 'amwal', 'lang' => 'en', 'booking_date' => \App\Support\TenantTime::today($this->tenant),
        ])->assertRedirect();
        $this->assertStringContainsString('/payment/redirect/', $res->headers->get('Location'));
        $this->assertSame('amwal', Payment::latest('id')->value('gateway'));

        $this->tenant->putSetting('payments.gateways', null)->save();
        $this->actingAs($visitor, 'visitor')->post("/e/{$event->id}/checkout", [
            'items' => [$type->id => 1], 'form_key' => 'page-2', 'gateway' => 'amwal', 'lang' => 'en', 'booking_date' => \App\Support\TenantTime::today($this->tenant),
        ])->assertRedirect()->assertSessionHas('visitor_error', 'gateway_not_available');
    }

    // ------------------------------------------------------------------ admin form

    private function superAdmin(): User
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    private function save(User $admin, Tenant $tenant, array $payments)
    {
        return $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), [
            'status' => 'active',
            'name' => $tenant->name,
        ] + $payments);
    }

    #[Test]
    public function the_admin_switches_gateways_on_and_off_with_a_default(): void
    {
        $admin = $this->superAdmin();
        $tenant = $this->makeTenant('two-gw');

        $this->actingAs($admin)->get(route('admin.tenants.edit', $tenant))->assertOk()
            ->assertSee('name="payments_gateways[]"', false)->assertSee('بوابات الدفع المفعّلة للزوار');

        // Both on, Amwal by default.
        $this->save($admin, $tenant, ['payments_driver' => 'amwal', 'payments_gateways' => ['', 'thawani', 'amwal']])->assertSessionHasNoErrors();
        $tenant = $tenant->fresh();
        $this->assertSame(['thawani', 'amwal'], $tenant->setting('payments.gateways'));
        $this->assertSame(['amwal', 'thawani'], app(PaymentGatewayManager::class)->enabled($tenant));
        $this->actingAs($admin)->get(route('admin.tenants.edit', $tenant))->assertOk()
            ->assertSee('id="pg_thawani" name="payments_gateways[]" value="thawani" checked', false)
            ->assertSee('id="pg_amwal" name="payments_gateways[]" value="amwal" checked', false);

        // The default must be on; at least one must be on.
        $this->save($admin, $tenant, ['payments_driver' => 'amwal', 'payments_gateways' => ['', 'thawani']])->assertSessionHasErrors('payments_driver');
        $this->save($admin, $tenant, ['payments_driver' => 'amwal', 'payments_gateways' => ['']])->assertSessionHasErrors('payments_gateways');
        $this->assertSame(['thawani', 'amwal'], $tenant->fresh()->setting('payments.gateways'), 'refused saves change nothing');

        // One on: stored like before the setting existed (the default alone).
        $this->save($admin, $tenant, ['payments_driver' => 'thawani', 'payments_gateways' => ['', 'thawani']])->assertSessionHasNoErrors();
        $tenant = $tenant->fresh();
        $this->assertNull(data_get($tenant->settings, 'payments.gateways'));
        $this->assertSame(['thawani'], app(PaymentGatewayManager::class)->enabled($tenant));

        // Bank Muscat works alone: the checkboxes do not apply.
        $this->save($admin, $tenant, ['payments_driver' => 'bankmuscat', 'payments_gateways' => ['', 'thawani', 'amwal']])->assertSessionHasNoErrors();
        $this->assertSame(['bankmuscat'], app(PaymentGatewayManager::class)->enabled($tenant->fresh()));
    }

    #[Test]
    public function a_gateway_without_credentials_cannot_be_switched_on(): void
    {
        config(['services.amwal.secure_key' => null, 'services.amwal.merchant_id' => null, 'services.amwal.terminal_id' => null]);
        $admin = $this->superAdmin();
        $tenant = $this->makeTenant('no-amwal');

        $this->save($admin, $tenant, ['payments_driver' => 'thawani', 'payments_gateways' => ['', 'thawani', 'amwal']])
            ->assertSessionHasErrors(['payments_gateways' => 'أدخل رقم التاجر ورقم الطرفية ومفتاح التوقيع لأموال أولًا، ثم فعّل أموال.']);
        $this->assertNull(data_get($tenant->fresh()->settings, 'payments'));

        // The organization's own Amwal keys, entered in the same save, count.
        $this->save($admin, $tenant, ['payments_driver' => 'thawani', 'payments_gateways' => ['', 'thawani', 'amwal'],
            'gateway' => ['amwal' => ['merchant_id' => '777001', 'terminal_id' => '888002', 'secure_key' => str_repeat('ab', 32)]]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['thawani', 'amwal'], app(PaymentGatewayManager::class)->enabled($tenant->fresh()));
    }
}
