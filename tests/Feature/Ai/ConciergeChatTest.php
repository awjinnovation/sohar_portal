<?php

namespace Tests\Feature\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRequest;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ConciergeChatTest extends AiTestCase
{
    #[Test]
    public function it_streams_deltas_cards_and_done_using_tool_results(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Opening Night Concert', 'title_ar' => 'حفل الافتتاح', 'price' => '5.000']);
        $answer = "Tonight there is the Opening Night Concert at the Hall for 5.000 OMR.\n<<<META {\"cards\":[{\"type\":\"event\",\"id\":{$event->id}},{\"type\":\"action\",\"action\":\"buy_ticket\",\"event_id\":{$event->id}}],\"suggestions\":[\"Is parking available?\",\"Any free events?\"]}>>>";

        $this->fakeOpenAi([
            $this->sse($this->toolResponse([['search_events', ['query' => null, 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString(), 'category' => null, 'free_only' => null]]])),
            $this->sse($this->textResponse($answer, $this->usage(300, 60)), 5),
        ]);

        $res = $this->post('/api/v1/ai/chat', ['message' => "What's on tonight?", 'locale' => 'en'], $this->guestHeaders(null, 'guest-device-0001', ['Accept' => 'text/event-stream']));
        $res->assertOk();
        $this->assertStringStartsWith('text/event-stream', $res->headers->get('Content-Type'));

        $events = $this->sseEvents($res->streamedContent());
        $types = array_column($events, 0);
        $this->assertContains('delta', $types);
        $this->assertSame('done', end($types));

        $text = implode('', array_map(fn ($e) => $e[1]['text'], array_filter($events, fn ($e) => $e[0] === 'delta')));
        $this->assertSame('Tonight there is the Opening Night Concert at the Hall for 5.000 OMR.', trim($text));
        $this->assertStringNotContainsString('META', $text);
        $this->assertStringNotContainsString('<<', $text);

        $cards = array_values(array_map(fn ($e) => $e[1], array_filter($events, fn ($e) => $e[0] === 'card')));
        $this->assertSame('event', $cards[0]['type']);
        $this->assertSame($event->id, $cards[0]['id']);
        $this->assertSame(5000, $cards[0]['price_minor']);
        $this->assertSame('5.000', $cards[0]['price']);
        $this->assertFalse($cards[0]['is_free']);
        $this->assertSame(['type' => 'action', 'action' => 'buy_ticket', 'label' => 'Get tickets', 'params' => ['event_id' => $event->id]], $cards[1]);

        $done = end($events)[1];
        $this->assertSame(['Is parking available?', 'Any free events?'], $done['suggestions']);
        $conversation = AiConversation::findOrFail($done['conversation_id']);
        $this->assertNull($conversation->user_id);
        $this->assertSame(64, strlen($conversation->guest_key));     // stored hashed
        $this->assertNotSame('guest-device-0001', $conversation->guest_key);
        $this->assertSame(['user', 'assistant'], $conversation->messages()->pluck('role')->all());
        $this->assertSame($done['message_id'], AiMessage::where('role', 'assistant')->value('id'));

        // Tool output (server data) was sent back to the model with the call id.
        Http::assertSentCount(2);
        $second = $this->sent(1);
        $outputs = array_values(array_filter($second['input'], fn ($i) => ($i['type'] ?? null) === 'function_call_output'));
        $this->assertSame('call_0', $outputs[0]['call_id']);
        $this->assertStringContainsString('Opening Night Concert', $outputs[0]['output']);
        $this->assertTrue($second['stream']);
        $this->assertFalse($second['store']);
        $this->assertSame('gpt-test-fast', $second['model']);
        $this->assertStringContainsString('Never guess', $second['instructions']);

        // Usage logged per call, without content.
        $this->assertSame(2, AiRequest::count());
        $this->assertSame(380, (int) AiRequest::sum('input_tokens'));
        $this->assertSame(['chat'], AiRequest::distinct()->pluck('feature')->all());
        $this->assertSame(['ok'], AiRequest::distinct()->pluck('status')->all());
    }

    #[Test]
    public function json_fallback_returns_message_cards_and_suggestions(): void
    {
        $loc = $this->makeLocation();
        $this->fakeOpenAi([
            $this->toolResponse([['find_locations', ['query' => null, 'category' => 'parking']]]),
            // No META block: the server falls back to tool results named in the text.
            $this->textResponse('You can park at Parking C next to the main gate.'),
        ]);

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Where can I park?', 'locale' => 'en'], $this->guestHeaders())
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame('You can park at Parking C next to the main gate.', $res->json('data.message.text'));
        $this->assertSame([['type' => 'location', 'id' => $loc->id, 'name' => 'Parking C', 'lat' => 24.1, 'lng' => 56.7, 'category' => 'parking']], $res->json('data.message.cards'));
        // Tenant default suggestions when the model gave none.
        $this->assertCount(3, $res->json('data.message.suggestions'));
        $this->assertNotEmpty($res->json('data.conversation_id'));
        $this->assertIsInt($res->json('data.message.id'));
    }

    #[Test]
    public function the_conversation_continues_with_history_and_feedback_list_get_delete_work(): void
    {
        $this->fakeOpenAi([$this->textResponse('Hello!'), $this->textResponse('Sure.')]);
        $first = $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $id = $first->json('data.conversation_id');

        $this->postJson('/api/v1/ai/chat', ['conversation_id' => $id, 'message' => 'And tomorrow?', 'locale' => 'en'], $this->guestHeaders())->assertOk()
            ->assertJsonPath('data.conversation_id', $id);
        $sent = $this->sent(1)['input'];
        $this->assertSame([['role' => 'user', 'content' => 'Hi'], ['role' => 'assistant', 'content' => 'Hello!'], ['role' => 'user', 'content' => 'And tomorrow?']], $sent);

        // Another guest cannot continue or read it.
        $this->postJson('/api/v1/ai/chat', ['conversation_id' => $id, 'message' => 'x', 'locale' => 'en'], $this->guestHeaders(null, 'someone-else-999'))
            ->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson("/api/v1/ai/conversations/$id", $this->guestHeaders(null, 'someone-else-999'))->assertStatus(404);

        $this->getJson('/api/v1/ai/conversations', $this->guestHeaders())->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.title', 'Hi');
        $show = $this->getJson("/api/v1/ai/conversations/$id", $this->guestHeaders())->assertOk();
        $this->assertCount(4, $show->json('data.messages'));

        $messageId = $first->json('data.message.id');
        $this->postJson("/api/v1/ai/messages/$messageId/feedback", ['rating' => 'down', 'comment' => 'wrong'], $this->guestHeaders(null, 'someone-else-999'))->assertStatus(404);
        $this->postJson("/api/v1/ai/messages/$messageId/feedback", ['rating' => 'meh'], $this->guestHeaders())->assertStatus(422);
        $this->postJson("/api/v1/ai/messages/$messageId/feedback", ['rating' => 'down', 'comment' => 'wrong'], $this->guestHeaders())->assertOk();
        $this->assertSame('down', AiMessage::find($messageId)->feedback_rating);

        $this->deleteJson("/api/v1/ai/conversations/$id", [], $this->guestHeaders())->assertOk();
        $this->assertSame(0, AiConversation::count());
        $this->assertSame(0, AiMessage::count());
    }

    #[Test]
    public function guests_need_a_guest_key_and_only_signed_in_users_get_personal_tools(): void
    {
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');

        $user = $this->makeUser();
        $other = $this->makeUser();
        $event = $this->makeEvent();
        $mine = $this->makeTicket($user, $event, ['quantity' => 2]);
        $theirs = $this->makeTicket($other, $event);

        // Guest: no my_tickets / my_wallet tools.
        $this->fakeOpenAi([$this->textResponse('Please sign in to see your tickets.')]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'My tickets?', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $tools = array_column($this->sent(0)['tools'], 'name');
        $this->assertNotContains('my_tickets', $tools);
        $this->assertNotContains('my_wallet', $tools);
        $this->assertStringContainsString('NOT signed in', $this->sent(0)['instructions']);

        // Signed in: own tickets only; a card for someone else's ticket is dropped.
        $this->fakeOpenAi([
            $this->toolResponse([['my_tickets', []]]),
            $this->textResponse("You have 1 ticket for Concert.\n<<<META {\"cards\":[{\"type\":\"ticket\",\"id\":{$mine->id}},{\"type\":\"ticket\",\"id\":{$theirs->id}}],\"suggestions\":[]}>>>"),
        ]);
        $this->actingAs($user, 'sanctum');
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'My tickets?', 'locale' => 'en'], ['X-Tenant' => $this->tenant->slug])->assertOk();
        $body = $this->sent(0);
        $this->assertContains('my_tickets', array_column($body['tools'], 'name'));
        $toolOutput = collect($this->sent(1)['input'])->firstWhere('type', 'function_call_output')['output'];
        $this->assertStringContainsString('"id":'.$mine->id, $toolOutput);
        $this->assertStringNotContainsString('"id":'.$theirs->id, $toolOutput);
        $this->assertStringNotContainsString('TKT-', $toolOutput);       // QR codes never go to the model
        $this->assertSame([['type' => 'ticket', 'id' => $mine->id, 'event_title' => 'Concert', 'date' => now()->toDateString(), 'status' => 'active']], $res->json('data.message.cards'));

        $conversation = AiConversation::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($user->id, (int) $conversation->user_id);
        $this->assertNull($conversation->guest_key);
        $this->assertSame((int) $user->id, (int) AiRequest::latest('id')->value('user_id'));
    }

    #[Test]
    public function message_length_is_limited(): void
    {
        $this->postJson('/api/v1/ai/chat', ['message' => str_repeat('a', 1001), 'locale' => 'en'], $this->guestHeaders())
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        Http::assertNothingSent();
    }

    #[Test]
    public function history_sent_to_the_model_is_capped(): void
    {
        $this->fakeOpenAi([$this->textResponse('ok')]);
        $c = AiConversation::create(['tenant_id' => $this->tenant->id, 'guest_key' => hash('sha256', config('app.key').'|ai-guest|guest-device-0001'), 'locale' => 'en', 'title' => 't']);
        for ($i = 0; $i < 30; $i++) {
            AiMessage::create(['tenant_id' => $this->tenant->id, 'conversation_id' => $c->id, 'role' => $i % 2 ? 'assistant' : 'user', 'content' => "m$i"]);
        }
        $this->postJson('/api/v1/ai/chat', ['conversation_id' => $c->id, 'message' => 'latest', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $input = $this->sent(0)['input'];
        $this->assertCount(13, $input);                 // 12 history messages + the new one
        $this->assertSame('m18', $input[0]['content']);
        $this->assertSame('latest', $input[12]['content']);
    }

    #[Test]
    public function the_last_tool_round_forces_an_answer(): void
    {
        config(['ai.chat.max_tool_rounds' => 2]);
        $this->fakeOpenAi([
            $this->toolResponse([['list_announcements', []]]),
            $this->textResponse('No announcements right now.'),
        ]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'News?', 'locale' => 'en'], $this->guestHeaders())->assertOk()
            ->assertJsonPath('data.message.text', 'No announcements right now.');
        $this->assertSame('auto', $this->sent(0)['tool_choice']);
        $this->assertSame('none', $this->sent(1)['tool_choice']);
    }
}
