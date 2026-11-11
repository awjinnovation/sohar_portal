<?php

namespace Tests\Feature\Platform;

use App\Console\Commands\WipeTenantTestData;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Announcement;
use App\Models\BoxOfficeOrder;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GateScan;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Place;
use App\Models\Restaurant;
use App\Models\SatisfactionResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Database\Seeders\PlacesContentSeeder;
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
            \Illuminate\Support\Facades\Storage::fake('local')->put('box-office-receipts/r1.jpg', 'jpeg');
            DB::table('box_office_receipts')->insert([
                'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'idempotency_key' => 'r1', 'disk' => 'local', 'path' => 'box-office-receipts/r1.jpg',
                'sha256' => str_repeat('a', 64), 'mime' => 'image/jpeg', 'size' => 4, 'verdict' => 'verified', 'ai_status' => 'ok', 'accepted' => true,
                'created_at' => now(), 'updated_at' => now(),
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
        $this->assertSame(0, DB::table('box_office_receipts')->count());
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing('box-office-receipts/r1.jpg');
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

    /** Seeded Discover content plus one of everything else; the festival is linked to one place and one map location. */
    private function seedContent(): array
    {
        $t = $this->tenant->id;
        $this->inTenant($this->tenant, fn () => $this->seed(PlacesContentSeeder::class));
        $venue = Place::withoutTenantScope()->where('tenant_id', $t)->where('slug', 'sohar-entertainment-centre')->firstOrFail();
        DB::table('event_place')->insert(['event_id' => $this->event->id, 'place_id' => $venue->id]);

        return $this->inTenant($this->tenant, function () use ($venue) {
            $grounds = MapLocation::create(['type' => 'venue', 'name' => 'Festival grounds', 'name_ar' => 'أرض المهرجان', 'description' => 'd', 'description_ar' => 'د', 'icon' => 'stadium', 'color' => 0xFF314F99, 'latitude' => 24.34, 'longitude' => 56.68, 'is_active' => true]);
            MapLocation::create(['type' => 'parking', 'name' => 'P1', 'name_ar' => 'موقف', 'description' => 'd', 'description_ar' => 'د', 'icon' => 'local_parking', 'color' => 0xFF607D8B, 'latitude' => 24, 'longitude' => 56, 'is_active' => true]);
            $this->event->forceFill(['map_location_id' => $grounds->id])->save();
            Announcement::create(['title' => 'Parking full', 'title_ar' => 'المواقف ممتلئة', 'content' => 'c', 'content_ar' => 'ج', 'type' => 'warning', 'priority' => 1, 'is_active' => true, 'created_by' => $this->admin->id, 'start_datetime' => now()->subHour(), 'end_datetime' => now()->addHour()]);
            Restaurant::create(['name' => 'Karak House', 'name_ar' => 'بيت الكرك', 'description' => 'd', 'description_ar' => 'د', 'cuisine' => 'Cafe', 'cuisine_ar' => 'مقهى', 'location' => 'Sohar', 'location_ar' => 'صحار', 'latitude' => 24.35, 'longitude' => 56.7, 'price_range' => '$$', 'rating' => 4.5, 'total_ratings' => 10, 'is_active' => true]);
            Vendor::create(['name' => ['en' => 'Karak House', 'ar' => 'بيت الكرك'], 'is_active' => true]);

            return ['venue' => $venue, 'grounds' => $grounds];
        });
    }

    #[Test]
    public function keep_event_removes_every_other_event_and_the_content_they_do_not_use(): void
    {
        $otherEvent = $this->makeEvent(null, ['title' => 'Old show', 'title_ar' => 'عرض قديم']);
        $this->inTenant($this->tenant, fn () => EventTag::create(['event_id' => $otherEvent->id, 'tag' => 'music', 'tag_ar' => 'موسيقى']));
        ['venue' => $venue, 'grounds' => $grounds] = $this->seedContent();
        $foreignEvents = Event::withoutTenantScope()->where('tenant_id', $this->other->id)->count();
        $t = $this->tenant->id;
        $this->assertSame(2, DB::table('event_ticket_types')->where('tenant_id', $t)->count(), 'each event gets a default type');
        $this->assertSame(2, DB::table('categories')->where('tenant_id', $t)->count(), 'one category per event');
        $this->assertGreaterThan(5, DB::table('places')->where('tenant_id', $t)->count(), 'seeded places');
        $this->assertGreaterThan(0, DB::table('content_items')->where('tenant_id', $t)->count());

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--force' => true, '--keep-event' => (string) $this->event->id])
            ->expectsOutputToContain('Events kept: #'.$this->event->id.' حفل')
            ->expectsOutputToContain('Events deleted (1): #'.$otherEvent->id.' عرض قديم')
            ->expectsOutputToContain('Content: only what the kept events use stays')
            ->assertSuccessful();

        // Events: only the kept one, with its ticket types.
        $this->assertTrue(Event::withoutTenantScope()->whereKey($this->event->id)->exists());
        $this->assertFalse(Event::withoutTenantScope()->whereKey($otherEvent->id)->exists());
        $this->assertSame([$this->event->id], DB::table('event_ticket_types')->where('tenant_id', $t)->pluck('event_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('event_tags')->where('event_id', $otherEvent->id)->count());

        // Content: only what the kept event uses.
        $this->assertSame([$this->event->fresh()->category_id], DB::table('categories')->where('tenant_id', $t)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$grounds->id], DB::table('map_locations')->where('tenant_id', $t)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$venue->id], DB::table('places')->where('tenant_id', $t)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$venue->category_id], DB::table('place_categories')->where('tenant_id', $t)->pluck('id')->map(fn ($id) => (int) $id)->all());
        foreach (['content_items', 'content_collections', 'content_pages', 'announcements', 'restaurants', 'vendors'] as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $t)->count(), $table);
        }
        $this->assertSame(0, DB::table('notifications')->where('tenant_id', $t)->count(), 'public notifications go with the content');
        $this->assertSame(0, DB::table('media')->where('model_type', (new Place)->getMorphClass())->where('model_id', '!=', $venue->id)->count());

        // Everything else as before.
        $this->assertSame($foreignEvents, Event::withoutTenantScope()->where('tenant_id', $this->other->id)->count());
        $this->assertTrue(Gate::withoutTenantScope()->whereKey($this->gate->id)->exists());
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertNull(User::find($this->visitor->id));
    }

    #[Test]
    public function keep_content_leaves_the_content_alone(): void
    {
        $this->makeEvent(null, ['title' => 'Old show', 'title_ar' => 'عرض قديم']);
        $this->seedContent();
        $t = $this->tenant->id;
        $before = collect(['categories', 'map_locations', 'places', 'place_categories', 'content_items', 'content_collections', 'content_pages', 'announcements', 'restaurants', 'vendors'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->where('tenant_id', $t)->count()]);

        $this->artisan('tenant:wipe-test-data', ['tenant' => $this->tenant->slug, '--force' => true, '--keep-event' => (string) $this->event->id, '--keep-content' => true])
            ->expectsOutputToContain('Content: kept (--keep-content).')
            ->assertSuccessful();

        $this->assertSame(1, Event::withoutTenantScope()->where('tenant_id', $t)->count());
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->where('tenant_id', $t)->count(), $table);
        }
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
