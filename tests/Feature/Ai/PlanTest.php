<?php

namespace Tests\Feature\Ai;

use App\Models\AiPlan;
use App\Models\AiRequest;
use PHPUnit\Framework\Attributes\Test;

class PlanTest extends AiTestCase
{
    #[Test]
    public function plan_output_is_validated_against_real_tenant_data(): void
    {
        $date = now()->addDays(2)->toDateString();
        $loc = $this->makeLocation(null, ['type' => 'food', 'name' => 'Food Court', 'name_ar' => 'ساحة الطعام']);
        // Multi-day event open daily 16:00–22:00, 1.500 per person.
        $show = $this->makeEvent(null, ['title' => 'Carnival', 'title_ar' => 'كرنفال', 'price' => '1.500',
            'start_time' => now()->subDay()->format('Y-m-d').' 16:00:00', 'end_time' => now()->addDays(5)->format('Y-m-d').' 22:00:00']);
        // One-day event on another date.
        $elsewhere = $this->makeEvent(null, ['title' => 'Other Day', 'start_time' => now()->addDays(4)->format('Y-m-d').' 10:00:00', 'end_time' => now()->addDays(4)->format('Y-m-d').' 12:00:00']);
        $otherTenant = $this->makeTenant('other-org');
        $foreign = $this->makeEvent($otherTenant, ['title' => 'Foreign', 'start_time' => now()->subDay(), 'end_time' => now()->addDays(9)]);

        $this->fakeOpenAi([[
            'id' => 'resp_plan', 'status' => 'completed', 'model' => 'gpt-test-smart', 'usage' => $this->usage(1200, 400),
            'output' => [['type' => 'message', 'id' => 'm', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'title' => 'Family evening',
                'summary' => 'Shows and dinner.',
                'items' => [
                    ['start' => '15:00', 'end' => '17:00', 'kind' => 'event', 'event_id' => $show->id, 'location_id' => null, 'title' => 'Carnival', 'note' => 'Arrive early', 'cost_minor' => 1],          // clipped to 16:00
                    ['start' => '18:00', 'end' => '19:00', 'kind' => 'food', 'event_id' => null, 'location_id' => $loc->id, 'title' => 'Dinner', 'note' => '', 'cost_minor' => 999999],                // cost unknown → null
                    ['start' => '19:00', 'end' => '20:00', 'kind' => 'event', 'event_id' => $foreign->id, 'location_id' => null, 'title' => 'Foreign', 'note' => '', 'cost_minor' => 0],              // other tenant → dropped
                    ['start' => '19:00', 'end' => '20:00', 'kind' => 'event', 'event_id' => 99999, 'location_id' => null, 'title' => 'Invented', 'note' => '', 'cost_minor' => 0],                   // invented → dropped
                    ['start' => '10:00', 'end' => '11:00', 'kind' => 'event', 'event_id' => $elsewhere->id, 'location_id' => null, 'title' => 'Other Day', 'note' => '', 'cost_minor' => 0],        // not running that day → dropped
                    ['start' => '22:30', 'end' => '23:30', 'kind' => 'event', 'event_id' => $show->id, 'location_id' => null, 'title' => 'Late', 'note' => '', 'cost_minor' => 0],                    // outside hours → dropped
                    ['start' => '20:00', 'end' => '20:30', 'kind' => 'break', 'event_id' => null, 'location_id' => 4242, 'title' => 'Rest', 'note' => '', 'cost_minor' => null],                    // bad location → null
                    ['start' => 'soon', 'end' => '21:00', 'kind' => 'activity', 'event_id' => null, 'location_id' => null, 'title' => 'Bad time', 'note' => '', 'cost_minor' => null],             // bad time → dropped
                ],
                'tips' => ['Bring water'],
                'warnings' => [],
            ])]]]],
        ]]);

        $res = $this->postJson('/api/v1/ai/plan', [
            'date' => $date, 'start_time' => '15:00', 'end_time' => '23:00',
            'party' => ['adults' => 2, 'kids' => 1, 'kids_ages' => [6]],
            'interests' => ['shows', 'food'], 'budget_minor' => 3000, 'locale' => 'en',
        ], $this->guestHeaders())->assertOk();

        $plan = $res->json('data.plan');
        $this->assertSame($date, $plan['date']);
        $this->assertCount(3, $plan['items']);
        $this->assertSame(['start' => '16:00', 'end' => '17:00', 'kind' => 'event', 'event_id' => $show->id, 'location_id' => null, 'title' => 'Carnival', 'note' => 'Arrive early', 'cost_minor' => 4500], $plan['items'][0]);
        $this->assertSame($loc->id, $plan['items'][1]['location_id']);
        $this->assertNull($plan['items'][1]['cost_minor']);
        $this->assertNull($plan['items'][2]['location_id']);
        $this->assertSame(4500, $plan['total_cost_minor']);
        $this->assertSame('4.500', $plan['total_cost']);
        $this->assertSame(5, $plan['dropped_items']);
        $this->assertStringContainsString('above your budget', $plan['warnings'][0]);
        $this->assertSame(['Bring water'], $plan['tips']);

        // The model only saw this tenant's events running that day.
        $data = $this->sent(0)['input'][0]['content'];
        $this->assertStringContainsString('Carnival', $data);
        $this->assertStringNotContainsString('Foreign', $data);
        $this->assertStringNotContainsString('Other Day', $data);
        $this->assertSame('json_schema', $this->sent(0)['text']['format']['type']);
        $this->assertTrue($this->sent(0)['text']['format']['strict']);
        $this->assertSame('gpt-test-smart', $this->sent(0)['model']);

        // Saved and shareable by id inside the tenant only.
        $this->assertSame(1, AiPlan::count());
        $this->assertSame('plan', AiRequest::value('feature'));
        $this->getJson('/api/v1/ai/plans/'.$plan['id'], ['X-Tenant' => $this->tenant->slug])->assertOk()->assertJsonPath('data.plan.total_cost_minor', 4500);
        $this->enableAi($otherTenant);
        $this->getJson('/api/v1/ai/plans/'.$plan['id'], ['X-Tenant' => 'other-org'])->assertStatus(404);
        $this->getJson('/api/v1/ai/plans/not-a-uuid', ['X-Tenant' => $this->tenant->slug])->assertStatus(404);
    }

    #[Test]
    public function plan_request_is_validated(): void
    {
        $this->fakeOpenAi([]);
        $this->postJson('/api/v1/ai/plan', ['date' => '22-12-2026', 'party' => ['adults' => 1]], $this->guestHeaders())->assertStatus(422);
        $this->postJson('/api/v1/ai/plan', ['date' => now()->toDateString(), 'party' => ['adults' => 0, 'kids' => 0]], $this->guestHeaders())->assertStatus(422);
        $this->postJson('/api/v1/ai/plan', ['date' => now()->toDateString(), 'party' => ['adults' => 1]], ['X-Tenant' => $this->tenant->slug])->assertStatus(422);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
