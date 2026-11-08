<?php

namespace Tests\Feature\Platform;

use App\Console\Commands\WipeTenantTestData;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BoxOfficeOrder;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GateScan;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\SatisfactionResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

class WipeTenantTestDataTest extends GateTestCase
{
    private Tenant $other;

    private User $admin;

    private User $gateStaff;

    private User $super;

    private User $visitor;

    private User $loadTest;

    private User $both;

    private User $otherVisitor;

    private Event $event;

    private Gate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->other = $this->makeTenant('other-org');

        // Accounts that must survive: the testing guide's admin and gate staff, a super-admin.
        $this->admin = User::factory()->create(['email' => 'suhar-admin@example.com']);
        $this->grantRole($this->admin, 'admin');
        $this->gateStaff = User::factory()->create(['phone_number' => '91110002']);
        $this->grantRole($this->gateStaff, 'staff');
        $this->super = User::factory()->create(['email' => 'super@platform.local']);
        $this->super->forceFill(['is_super_admin' => true])->save();
        $this->admin->createToken('app');

        // Set-up data that must survive.
        $this->event = $this->makeEvent();
        $this->gate = $this->makeGate(['code' => 'A']);

        // A visitor with every kind of test data in the default tenant.
        $this->visitor = $this->makeUser('91234567');
        $this->fund($this->visitor, 5000);
        $ticket = $this->makeTicket($this->event, ['user_id' => $this->visitor->id]);
        $this->visitor->createToken('app');
        [$device] = $this->pairDevice($this->gate);
        $this->inTenant($this->tenant, function () use ($ticket, $device) {
            $payment = Payment::create([
                'user_id' => $this->visitor->id, 'transaction_id' => 'TX-WIPE-1', 'payment_type' => 'ticket', 'method' => 'wallet',
                'amount' => 1, 'amount_minor' => 1000, 'currency' => 'OMR', 'status' => 'completed', 'paid_at' => now(),
            ]);
            $order = BoxOfficeOrder::create([
                'gate_id' => $this->gate->id, 'device_id' => $device->id, 'staff_user_id' => $this->gateStaff->id, 'buyer_user_id' => $this->visitor->id,
                'reference' => 'BO-000000-0001', 'buyer_phone' => '91234567', 'event_id' => $this->event->id, 'booking_date' => now()->toDateString(),
                'quantity' => 1, 'unit_price_minor' => 500, 'total_minor' => 500, 'currency' => 'OMR', 'status' => 'paid', 'payment_id' => $payment->id,
                'public_token' => str_repeat('a', 32), 'expires_at' => now()->addHour(), 'idempotency_key' => 'bo-1',
            ]);
            DB::table('box_office_order_lines')->insert([
                'order_id' => $order->id, 'ticket_type_id' => null, 'key' => 'general', 'name' => json_encode(['en' => 'General', 'ar' => 'عامة']),
                'quantity' => 1, 'unit_price_minor' => 500, 'line_total_minor' => 500, 'admits' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            GateScan::create([
                'gate_id' => $this->gate->id, 'device_id' => $device->id, 'ticket_id' => $ticket->id, 'code_hash' => hash('sha256', 'x'),
                'result' => 'admitted', 'direction' => 'entry', 'source' => 'camera', 'scanned_at' => now(), 'received_at' => now(), 'idempotency_key' => 'scan-1',
            ]);
            SatisfactionResponse::create([
                'user_id' => $this->visitor->id, 'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'score' => 5,
                'locale' => 'ar', 'response_date' => now()->toDateString(), 'dedupe_key' => 'd1',
            ]);
            $conversation = AiConversation::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->visitor->id, 'locale' => 'ar', 'title' => 't']);
            AiMessage::create(['tenant_id' => $this->tenant->id, 'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'hi']);
            Notification::create(['user_id' => $this->visitor->id, 'title' => 'Personal', 'title_ar' => 'شخصي', 'body' => 'b', 'body_ar' => 'ب', 'type' => 'info']);
            Notification::create(['user_id' => null, 'title' => 'Public', 'title_ar' => 'عام', 'body' => 'b', 'body_ar' => 'ب', 'type' => 'info', 'is_public' => true]);
            DB::table('user_favorites')->insert([
                'tenant_id' => $this->tenant->id, 'user_id' => $this->visitor->id, 'favoritable_type' => Event::class,
                'favoritable_id' => $this->event->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        DB::table('otp_verifications')->insert([
            ['phone_number' => '91234567', 'otp_code' => '123456', 'expires_at' => now()->addMinutes(5), 'is_used' => false, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['phone_number' => '91110002', 'otp_code' => '123456', 'expires_at' => now()->addMinutes(5), 'is_used' => false, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['phone_number' => '91110002', 'otp_code' => '111111', 'expires_at' => now()->subMinute(), 'is_used' => true, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // A load-test account (no role, wallet only).
        $this->loadTest = $this->makeUser('96800000001');
        $this->fund($this->loadTest, 1000);

        // A visitor with data in both tenants, and one who only uses the other tenant.
        $this->both = $this->makeUser('92222222');
        $this->fund($this->both, 100);
        $this->fund($this->both, 200, $this->other);
        $this->otherVisitor = $this->makeUser('93333333');
        $this->fund($this->otherVisitor, 300, $this->other);
        $this->makeTicket($this->makeEvent($this->other), ['user_id' => $this->otherVisitor->id], $this->other);
    }

    #[Test]
    public function it_deletes_the_tenants_test_data_and_keeps_its_setup_staff_and_other_tenants(): void
    {
        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--force' => true])
            ->expectsOutputToContain('Done: test data of tenant north-batinah removed')
            ->assertSuccessful();

        $t = $this->tenant->id;
        foreach (['tickets', 'payments', 'wallets', 'wallet_transactions', 'box_office_orders', 'gate_scans', 'gate_devices', 'gate_pairing_codes',
            'satisfaction_responses', 'ai_conversations', 'ai_messages', 'user_favorites', 'points_accounts'] as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $t)->count(), $table);
        }
        $this->assertSame(0, DB::table('box_office_order_lines')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_type', (new GateDevice)->getMorphClass())->count());
        $this->assertSame(0, DB::table('otp_verifications')->where('phone_number', '91234567')->count());
        $this->assertSame(1, DB::table('otp_verifications')->where('phone_number', '91110002')->count(), 'only the expired staff OTP goes');

        // Content and set-up stay.
        $this->assertNotNull(Tenant::find($t));
        $this->assertTrue(Event::withoutTenantScope()->whereKey($this->event->id)->exists());
        $this->assertTrue(Gate::withoutTenantScope()->whereKey($this->gate->id)->exists());
        $this->assertSame(1, Category::withoutTenantScope()->where('tenant_id', $t)->count());
        $this->assertSame(1, Notification::withoutTenantScope()->where('tenant_id', $t)->count());
        $this->assertSame('Public', Notification::withoutTenantScope()->where('tenant_id', $t)->value('title'));

        // Staff, super-admin and their roles / tokens stay; visitors go.
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertTrue($this->gateStaff->fresh()->hasRole('staff'));
        $this->assertTrue($this->super->fresh()->isSuperAdmin());
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertNull(User::find($this->visitor->id));
        $this->assertNull(User::find($this->loadTest->id));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->visitor->id)->where('tokenable_type', (new User)->getMorphClass())->count());

        // Other tenants are untouched; a visitor with data there keeps the account.
        $this->assertNotNull(User::find($this->both->id));
        $this->assertNotNull(User::find($this->otherVisitor->id));
        $this->assertSame(2, Wallet::withoutTenantScope()->where('tenant_id', $this->other->id)->count());
        $this->assertSame(1, Ticket::withoutTenantScope()->where('tenant_id', $this->other->id)->count());
        $this->assertSame(0, Wallet::withoutTenantScope()->where('tenant_id', $t)->where('user_id', $this->both->id)->count());

        // The wipe itself is the only audit entry left.
        $this->assertSame(1, DB::table('audit_logs')->where('tenant_id', $t)->count());
        $this->assertSame(WipeTenantTestData::AUDIT_ACTION, DB::table('audit_logs')->where('tenant_id', $t)->value('action'));
    }

    #[Test]
    public function dry_run_only_reports_and_a_wrong_confirmation_aborts(): void
    {
        $before = DB::table('tickets')->count();

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--dry-run' => true])
            ->expectsOutputToContain('Dry run: nothing deleted.')
            ->assertSuccessful();
        $this->assertSame($before, DB::table('tickets')->count());

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug])
            ->expectsQuestion(WipeTenantTestData::CONFIRM_QUESTION, 'demo')
            ->expectsOutputToContain('Aborted')
            ->assertFailed();
        $this->assertSame($before, DB::table('tickets')->count());
        $this->assertNotNull(User::find($this->visitor->id));

        $this->artisan('tenant:wipe-test-data', ['tenant' => 'no-such-tenant'])->assertFailed();
    }

    #[Test]
    public function keep_event_removes_every_other_event_of_the_tenant_only(): void
    {
        $otherEvent = $this->makeEvent(null, ['title' => 'Old show', 'title_ar' => 'عرض قديم']);
        $this->inTenant($this->tenant, fn () => EventTag::create(['event_id' => $otherEvent->id, 'tag' => 'music', 'tag_ar' => 'موسيقى']));
        $foreignEvents = Event::withoutTenantScope()->where('tenant_id', $this->other->id)->count();
        $this->assertSame(2, DB::table('event_ticket_types')->where('tenant_id', $this->tenant->id)->count(), 'each event gets a default type');

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--force' => true, '--keep-event' => (string) $this->event->id])
            ->expectsOutputToContain('Events kept: #'.$this->event->id.' حفل')
            ->expectsOutputToContain('Events deleted (1): #'.$otherEvent->id.' عرض قديم')
            ->assertSuccessful();

        $this->assertTrue(Event::withoutTenantScope()->whereKey($this->event->id)->exists());
        $this->assertFalse(Event::withoutTenantScope()->whereKey($otherEvent->id)->exists());
        $this->assertSame([$this->event->id], DB::table('event_ticket_types')->where('tenant_id', $this->tenant->id)->pluck('event_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('event_tags')->where('event_id', $otherEvent->id)->count());
        $this->assertSame($foreignEvents, Event::withoutTenantScope()->where('tenant_id', $this->other->id)->count());
        $this->assertNull(User::find($this->visitor->id));
    }

    #[Test]
    public function keep_event_refuses_ids_that_are_not_events_of_the_tenant(): void
    {
        $foreign = $this->makeEvent($this->other);
        $events = Event::withoutTenantScope()->count();

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--force' => true, '--keep-event' => (string) $foreign->id])
            ->expectsOutputToContain('Not an event of this tenant: #'.$foreign->id)
            ->assertFailed();

        $this->assertSame($events, Event::withoutTenantScope()->count());
        $this->assertNotNull(User::find($this->visitor->id));
    }
}
