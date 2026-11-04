<?php

namespace Tests\Feature\Satisfaction;

use App\Models\SatisfactionResponse;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** Visitor survey API «راضي» (docs/radi_satisfaction_contract.md §3). */
class SatisfactionApiTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private const DEVICE = 'device-1234-abcd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant->putSetting('modules.radi', true)->save();
        $this->tenant->refresh();
    }

    private function answer(array $data, ?User $user = null)
    {
        return $this->api('POST', '/api/v1/satisfaction/responses', $data, $user);
    }

    private function ticket(User $user, $event): Ticket
    {
        return $this->inTenant($this->tenant, fn () => Ticket::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'ticket_type' => 'standard', 'status' => 'used',
            'price' => '1.000', 'currency' => 'OMR', 'holder_name' => 'X', 'purchase_date' => now(),
            'booking_date' => now()->format('Y-m-d'), 'check_in_count' => 1,
        ]));
    }

    #[Test]
    public function the_module_flag_guards_both_endpoints(): void
    {
        $this->tenant->putSetting('modules.radi', false)->save();

        $this->getJson('/api/v1/satisfaction/survey', $this->tenantHeaders())
            ->assertStatus(403)->assertJsonPath('code', 'MODULE_DISABLED');
        $this->answer(['score' => 5, 'device_id' => self::DEVICE])
            ->assertStatus(403)->assertJsonPath('code', 'MODULE_DISABLED');
        $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertJsonPath('data.modules.radi', false);
    }

    #[Test]
    public function tenant_config_exposes_the_radi_block_with_defaults(): void
    {
        $this->tenant->putSetting('radi.title', ['en' => 'Radi', 'ar' => 'راضي'])
            ->putSetting('radi.external_url', 'javascript:alert(1)')->save();

        $res = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())->assertOk()
            ->assertJsonPath('data.modules.radi', true)
            ->assertJsonPath('data.radi.title.ar', 'راضي')
            ->assertJsonPath('data.radi.logo_url', '')
            ->assertJsonPath('data.radi.external_url', '')
            ->assertJsonPath('data.radi.aspects.0.key', 'organization')
            ->assertJsonPath('data.radi.aspects.0.label.ar', 'التنظيم');
        $this->assertCount(6, $res->json('data.radi.aspects'));

        $this->tenant->putSetting('radi.external_url', 'https://radi.example.om/survey')
            ->putSetting('radi.logo_url', '/tenants/north-batinah/radi.png')->save();
        $res = $this->getJson('/api/v1/tenant/config', $this->tenantHeaders())
            ->assertJsonPath('data.radi.external_url', 'https://radi.example.om/survey');
        $this->assertStringStartsWith('http', $res->json('data.radi.logo_url'));
    }

    #[Test]
    public function a_guest_answers_once_per_event_per_day(): void
    {
        $event = $this->makeEvent();

        $this->answer([
            'score' => 4, 'aspects' => ['cleanliness' => 5, 'app' => 3], 'comment' => '  Great show  ',
            'event_id' => $event->id, 'locale' => 'en', 'platform' => 'ios', 'app_version' => '8.0.0', 'device_id' => self::DEVICE,
            'contact_ok' => true,
        ])->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.score', 4)->assertJsonPath('data.event_id', $event->id);

        $row = SatisfactionResponse::firstOrFail();
        $this->assertSame('Great show', $row->comment);
        $this->assertSameJson(['cleanliness' => 5, 'app' => 3], $row->aspects);
        $this->assertFalse($row->contact_ok, 'guests cannot be contacted');
        $this->assertNull($row->user_id);
        $this->assertSame(SatisfactionResponse::hashDevice(self::DEVICE), $row->device_id);
        $this->assertNotSame(self::DEVICE, $row->device_id);

        $this->answer(['score' => 2, 'event_id' => $event->id, 'device_id' => self::DEVICE])
            ->assertStatus(409)->assertJsonPath('code', 'ALREADY_ANSWERED');
        // Another event, and the general survey, are separate answers.
        $this->answer(['score' => 3, 'device_id' => self::DEVICE])->assertCreated();
        $this->answer(['score' => 3, 'event_id' => $this->makeEvent()->id, 'device_id' => self::DEVICE])->assertCreated();
        // Another device is another visitor.
        $this->answer(['score' => 5, 'event_id' => $event->id, 'device_id' => 'other-device-9999'])->assertCreated();

        $this->getJson('/api/v1/satisfaction/survey?event_id='.$event->id.'&device_id='.self::DEVICE, $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.answered', true)
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.scale.max', 5)
            ->assertJsonPath('data.comment_max', 1000)
            ->assertJsonPath('data.signed_in', false);
        $this->getJson('/api/v1/satisfaction/survey?device_id=fresh-device-0000', $this->tenantHeaders())
            ->assertOk()->assertJsonPath('data.answered', false)->assertJsonPath('data.last_response_at', null);

        // The next tenant-local day is a new day.
        $this->travel(1)->days();
        $this->answer(['score' => 5, 'event_id' => $event->id, 'device_id' => self::DEVICE])->assertCreated();
    }

    #[Test]
    public function validation_errors(): void
    {
        $this->answer(['score' => 5])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('device_id');
        $this->answer(['score' => 6, 'device_id' => self::DEVICE])->assertStatus(422)->assertJsonValidationErrors('score');
        $this->answer(['device_id' => self::DEVICE])->assertStatus(422)->assertJsonValidationErrors('score');
        $this->answer(['score' => 4, 'aspects' => ['parking' => 4], 'device_id' => self::DEVICE])->assertStatus(422)->assertJsonValidationErrors('aspects');
        $this->answer(['score' => 4, 'aspects' => ['cleanliness' => 9], 'device_id' => self::DEVICE])->assertStatus(422)->assertJsonValidationErrors('aspects.cleanliness');
        $this->answer(['score' => 4, 'comment' => str_repeat('a', 1001), 'device_id' => self::DEVICE])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->answer(['score' => 4, 'device_id' => 'bad id!'])->assertStatus(422)->assertJsonValidationErrors('device_id');
        $this->answer(['score' => 4, 'event_id' => 999999, 'device_id' => self::DEVICE])->assertStatus(422)->assertJsonPath('code', 'EVENT_NOT_FOUND');
        $this->assertSame(0, SatisfactionResponse::count());
    }

    #[Test]
    public function a_signed_in_visitor_links_a_used_ticket_and_may_allow_contact(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent();
        $ticket = $this->ticket($user, $event);
        $other = $this->ticket($this->makeUser(), $event);

        $this->answer(['score' => 5, 'ticket_id' => $other->id], $user)->assertStatus(422)->assertJsonPath('code', 'TICKET_NOT_FOUND');

        $this->answer(['score' => 5, 'ticket_id' => $ticket->id, 'contact_ok' => true, 'comment' => 'Loved it'], $user)->assertCreated()
            ->assertJsonPath('data.event_id', $event->id);
        $row = SatisfactionResponse::firstOrFail();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($ticket->id, $row->ticket_id);
        $this->assertTrue($row->contact_ok);

        // Same user, same event, same day — from another device too.
        $this->answer(['score' => 1, 'event_id' => $event->id, 'device_id' => 'second-phone-01'], $user)->assertStatus(409);

        $this->getJson('/api/v1/satisfaction/survey?event_id='.$event->id, $this->tenantHeaders())
            ->assertJsonPath('data.answered', true)->assertJsonPath('data.signed_in', true);
    }

    #[Test]
    public function a_guest_answer_counts_for_the_account_signed_in_later_on_that_device(): void
    {
        $this->answer(['score' => 4, 'device_id' => self::DEVICE])->assertCreated();
        $this->answer(['score' => 4, 'device_id' => self::DEVICE], $this->makeUser())->assertStatus(409);
    }

    #[Test]
    public function answers_are_scoped_to_the_tenant(): void
    {
        $other = $this->makeTenant('other-org', ['modules' => ['radi' => true]]);

        $this->answer(['score' => 4, 'device_id' => self::DEVICE])->assertCreated();
        $this->json('POST', '/api/v1/satisfaction/responses', ['score' => 2, 'device_id' => self::DEVICE], $this->tenantHeaders($other))->assertCreated();

        $this->assertSame(1, SatisfactionResponse::count(), 'scoped to the default tenant');
        $this->assertSame(2, SatisfactionResponse::withoutTenantScope()->count());
    }

    #[Test]
    public function answers_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->answer(['score' => 4, 'device_id' => self::DEVICE]);
        }
        $this->answer(['score' => 4, 'device_id' => self::DEVICE])->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_REQUESTS');
    }
}
