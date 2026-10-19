<?php

namespace Tests\Feature\Ai;

use App\Models\AiRequest;
use App\Services\Ai\Concierge\MetaFilter;
use App\Services\Ai\SseParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AiGuardsTest extends AiTestCase
{
    #[Test]
    public function sse_parser_handles_split_chunks_crlf_comments_and_multiline_data(): void
    {
        $parser = new SseParser;
        $events = [];
        $raw = ": keep-alive\r\nevent: response.output_text.delta\r\ndata: {\"type\":\"response.output_text.delta\",\"delta\":\"Hel\"}\r\n\r\n"
            ."event: response.output_text.delta\ndata: {\"type\":\"response.output_text.delta\",\n"
            ."data: \"delta\":\"lo\"}\n\n"
            .'data: [DONE]';
        foreach (str_split($raw, 9) as $chunk) {
            array_push($events, ...$parser->feed($chunk));
        }
        array_push($events, ...$parser->finish());

        $this->assertCount(3, $events);
        $this->assertSame('response.output_text.delta', $events[0]['event']);
        $this->assertSame('Hel', json_decode($events[0]['data'], true)['delta']);
        $this->assertSame('lo', json_decode($events[1]['data'], true)['delta']);
        $this->assertSame(['event' => null, 'data' => '[DONE]'], $events[2]);
    }

    #[Test]
    public function meta_marker_split_across_deltas_is_never_shown(): void
    {
        $f = new MetaFilter;
        $shown = '';
        foreach (['Two shows tonight', ' <', '<', '<ME', 'TA {"cards":[{"type":"event","id":3}],', '"suggestions":["a"]}>', '>>'] as $d) {
            $shown .= $f->feed($d);
        }
        $shown .= $f->flush();
        $this->assertSame('Two shows tonight ', $shown);
        $this->assertSame(['cards' => [['type' => 'event', 'id' => 3]], 'suggestions' => ['a']], $f->meta());

        // A lone "<" that is not the marker is released at the end.
        $g = new MetaFilter;
        $out = $g->feed('a < b <').$g->flush();
        $this->assertSame('a < b <', $out);
        $this->assertNull($g->meta());
    }

    #[Test]
    public function tool_results_and_cards_are_tenant_scoped(): void
    {
        $other = $this->enableAi($this->makeTenant('other-org'));
        $secret = $this->makeEvent($other, ['title' => 'Secret Gala of Other Org', 'title_ar' => 'حفل سري', 'location' => 'Elsewhere']);
        $own = $this->makeEvent(null, ['title' => 'Public Fair', 'title_ar' => 'معرض عام']);

        $this->fakeOpenAi([
            $this->toolResponse([
                ['search_events', ['query' => null, 'date_from' => null, 'date_to' => null, 'category' => null, 'free_only' => null]],
                ['get_event', ['id' => $secret->id]],
            ]),
            $this->textResponse("Here is the Public Fair.\n<<<META {\"cards\":[{\"type\":\"event\",\"id\":{$secret->id}},{\"type\":\"event\",\"id\":{$own->id}}],\"suggestions\":[]}>>>"),
        ]);

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'events?', 'locale' => 'en'], $this->guestHeaders())->assertOk();

        $outputs = collect($this->sent(1)['input'])->where('type', 'function_call_output')->pluck('output')->implode("\n");
        $this->assertStringContainsString('Public Fair', $outputs);
        $this->assertStringNotContainsString('Secret Gala', $outputs);
        $this->assertStringContainsString('event_not_found', $outputs);
        $this->assertSame([$own->id], array_column($res->json('data.message.cards'), 'id'));

        // …and the other tenant sees only its own event.
        $this->fakeOpenAi([
            $this->toolResponse([['search_events', ['query' => 'fair', 'date_from' => null, 'date_to' => null, 'category' => null, 'free_only' => null]]]),
            $this->textResponse("Nothing called fair.\n<<<META {\"cards\":[{\"type\":\"event\",\"id\":{$own->id}}],\"suggestions\":[]}>>>"),
        ]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'fair?', 'locale' => 'en'], $this->guestHeaders($other))->assertOk();
        $outputs = collect($this->sent(1)['input'])->where('type', 'function_call_output')->pluck('output')->implode("\n");
        $this->assertStringNotContainsString('Public Fair', $outputs);
        $this->assertStringContainsString('Secret Gala', $outputs);    // fallback: all events of *this* tenant
        $this->assertSame([], $res->json('data.message.cards'));
        $this->assertSame(2, AiRequest::where('tenant_id', $other->id)->count());
    }

    #[Test]
    public function module_disabled_returns_ai_disabled_in_json_and_sse(): void
    {
        $this->enableAi($this->tenant, false, false);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(403)->assertJsonPath('code', 'AI_DISABLED');

        $res = $this->post('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'ar'], $this->guestHeaders(null, 'guest-device-0001', ['Accept' => 'text/event-stream']));
        $res->assertStatus(403);
        $events = $this->sseEvents($res->streamedContent());
        $this->assertSame('error', $events[0][0]);
        $this->assertSame('AI_DISABLED', $events[0][1]['code']);
        $this->assertSame(__('ai.errors.disabled', [], 'ar'), $events[0][1]['message']);

        $this->postJson('/api/v1/ai/plan', ['date' => now()->toDateString(), 'party' => ['adults' => 2], 'interests' => []], $this->guestHeaders())
            ->assertStatus(403)->assertJsonPath('code', 'AI_DISABLED');
        $this->getJson('/api/v1/ai/conversations', $this->guestHeaders())->assertStatus(403)->assertJsonPath('code', 'AI_DISABLED');
        Http::assertNothingSent();
    }

    #[Test]
    public function monthly_token_budget_is_enforced(): void
    {
        $this->tenant->putSetting('ai.monthly_token_budget', 1000)->save();
        AiRequest::create(['tenant_id' => $this->tenant->id, 'feature' => 'chat', 'model' => 'x', 'input_tokens' => 900, 'output_tokens' => 100, 'latency_ms' => 1, 'status' => 'ok']);
        // Last month's usage does not count.
        DB::table('ai_requests')->insert(['tenant_id' => $this->tenant->id, 'feature' => 'chat', 'model' => 'x', 'input_tokens' => 5000, 'output_tokens' => 0, 'latency_ms' => 1, 'status' => 'ok', 'created_at' => now()->subMonths(2), 'updated_at' => now()->subMonths(2)]);
        $this->fakeOpenAi([]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(429)->assertJsonPath('code', 'AI_BUDGET_EXCEEDED');
        $res = $this->post('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders(null, 'guest-device-0001', ['Accept' => 'text/event-stream']));
        $this->assertSame('AI_BUDGET_EXCEEDED', $this->sseEvents($res->streamedContent())[0][1]['code']);
        Http::assertNothingSent();
        $this->assertSame(2, AiRequest::where('status', 'budget_exceeded')->count());

        // Other tenants are unaffected.
        $other = $this->enableAi($this->makeTenant('fresh-org'));
        $this->fakeOpenAi([$this->textResponse('Hi there')]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders($other))->assertOk();
    }

    #[Test]
    public function guest_and_user_rate_limits(): void
    {
        config(['ai.chat.rate.guest' => ['max' => 2, 'minutes' => 10], 'ai.chat.rate.user' => ['max' => 3, 'minutes' => 10]]);
        $this->fakeOpenAi(array_fill(0, 10, $this->textResponse('ok')));

        $this->postJson('/api/v1/ai/chat', ['message' => 'a', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $this->postJson('/api/v1/ai/chat', ['message' => 'b', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $this->postJson('/api/v1/ai/chat', ['message' => 'c', 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(429)->assertJsonPath('code', 'AI_RATE_LIMITED')->assertJsonStructure(['data' => ['retry_after']]);
        // A different guest still has its own allowance.
        $this->postJson('/api/v1/ai/chat', ['message' => 'd', 'locale' => 'en'], $this->guestHeaders(null, 'second-guest-02'))->assertOk();

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/ai/chat', ['message' => "u$i", 'locale' => 'en'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        }
        $res = $this->post('/api/v1/ai/chat', ['message' => 'u4', 'locale' => 'en'], ['X-Tenant' => $this->tenant->slug, 'Accept' => 'text/event-stream']);
        $res->assertStatus(429);
        $this->assertNotNull($res->headers->get('Retry-After'));
        $this->assertSame('AI_RATE_LIMITED', $this->sseEvents($res->streamedContent())[0][1]['code']);
        $this->assertSame(2, AiRequest::where('status', 'rate_limited')->count());
        $this->assertSame(6, AiRequest::where('status', 'ok')->count());
    }

    #[Test]
    public function the_api_key_never_appears_in_logs_responses_or_ai_requests(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            $logged[] = $e->message.' '.json_encode($e->context);
        });

        // 401 whose body echoes the key (as OpenAI does, partially masked), then a connection failure, then a 500 storm.
        $this->fakeOpenAi([
            ['__status' => 401, 'body' => ['error' => ['message' => 'Incorrect API key provided: '.self::KEY, 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']]],
        ]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(503)->assertJsonPath('code', 'AI_UNAVAILABLE');
        $this->assertStringNotContainsString(self::KEY, $res->getContent());

        $this->fakeOpenAi(array_fill(0, 3, fn () => throw new ConnectionException('cURL error 28: Operation timed out for https://api.openai.test/v1/responses (Bearer '.self::KEY.')')));
        $res = $this->post('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders(null, 'guest-device-0001', ['Accept' => 'text/event-stream']));
        $this->assertStringNotContainsString(self::KEY, $res->streamedContent());
        $this->assertSame('AI_UNAVAILABLE', collect($this->sseEvents($res->streamedContent()))->firstWhere(0, 'error')[1]['code']);

        // Requests did carry the key (as a bearer header only).
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.self::KEY));

        $this->assertSame(['openai_401_invalid_api_key', 'timeout'], AiRequest::where('status', 'error')->orderBy('id')->pluck('error_code')->all());
        $rows = json_encode(DB::table('ai_requests')->get());
        $this->assertStringNotContainsString(self::KEY, $rows);
        $this->assertStringNotContainsString(self::KEY, implode("\n", $logged));
        $this->assertStringNotContainsString(substr(self::KEY, 0, 12), implode("\n", $logged));
    }

    #[Test]
    public function transient_upstream_errors_are_retried_with_backoff(): void
    {
        $this->fakeOpenAi([
            ['__status' => 503, 'body' => ['error' => ['message' => 'overloaded']]],
            ['__status' => 429, 'body' => ['error' => ['message' => 'slow down', 'code' => 'rate_limit_exceeded']], 'headers' => ['Retry-After' => '1']],
            $this->textResponse('Recovered'),
        ]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())->assertOk()
            ->assertJsonPath('data.message.text', 'Recovered');
        Http::assertSentCount(3);
        \Illuminate\Support\Sleep::assertSleptTimes(2);

        // insufficient_quota is not retried.
        $this->fakeOpenAi([['__status' => 429, 'body' => ['error' => ['message' => 'quota', 'code' => 'insufficient_quota']]]]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())->assertStatus(503);
        Http::assertSentCount(4);
    }

    #[Test]
    public function missing_key_is_ai_unavailable_without_calling_openai(): void
    {
        config(['services.openai.key' => null]);
        $this->fakeOpenAi([]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(503)->assertJsonPath('code', 'AI_UNAVAILABLE');
        Http::assertNothingSent();
        $this->assertSame('not_configured', AiRequest::value('error_code'));
    }

    #[Test]
    public function tenant_config_exposes_ai_modules_and_public_ai_block_only(): void
    {
        $res = $this->getJson('/api/v1/tenant/config', ['X-Tenant' => $this->tenant->slug])->assertOk()
            ->assertJsonPath('data.modules.ai_assistant', true)
            ->assertJsonPath('data.modules.ai_plan', true)
            ->assertJsonPath('data.ai.assistant_name.en', 'Ask Mawsim')
            ->assertJsonPath('data.ai.welcome.ar', config('tenancy.defaults.ai.welcome.ar'))
            ->assertJsonCount(3, 'data.ai.suggestions')
            ->assertJsonMissingPath('data.ai.monthly_token_budget')
            ->assertJsonMissingPath('data.ai.venue_info');
        $this->assertSame(config('tenancy.defaults.ai.suggestions.0'), $res->json('data.ai.suggestions.0'));
        $this->assertSame("What's on tonight?", $res->json('data.ai.suggestions.0.en'));

        // Platform default for a new tenant: AI off.
        $this->makeTenant('new-org');
        $this->getJson('/api/v1/tenant/config', ['X-Tenant' => 'new-org'])->assertJsonPath('data.modules.ai_assistant', false);
    }

    #[Test]
    public function prune_command_removes_expired_conversations(): void
    {
        $old = \App\Models\AiConversation::create(['tenant_id' => $this->tenant->id, 'guest_key' => 'x', 'locale' => 'en', 'title' => 'old']);
        \App\Models\AiMessage::create(['tenant_id' => $this->tenant->id, 'conversation_id' => $old->id, 'role' => 'user', 'content' => 'hi']);
        DB::table('ai_conversations')->where('id', $old->id)->update(['updated_at' => now()->subDays(31)]);
        $fresh = \App\Models\AiConversation::create(['tenant_id' => $this->tenant->id, 'guest_key' => 'y', 'locale' => 'en', 'title' => 'new']);

        $this->artisan('ai:prune')->assertSuccessful();
        $this->assertNull(\App\Models\AiConversation::find($old->id));
        $this->assertNotNull(\App\Models\AiConversation::find($fresh->id));
        $this->assertSame(0, \App\Models\AiMessage::count());
    }
}
