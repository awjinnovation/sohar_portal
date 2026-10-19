<?php

namespace Tests\Feature\Ai;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/**
 * Base for AI tests: a fake OpenAI key, no real HTTP (Http::fake only),
 * no real sleeping in retries, AI modules on for the default tenant.
 */
abstract class AiTestCase extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    public const KEY = 'sk-test-DO-NOT-LEAK-7f3a9c1e5b';

    public const URL = 'https://api.openai.test/v1/responses';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openai.key' => self::KEY,
            'services.openai.base_url' => 'https://api.openai.test/v1',
            'services.openai.model_fast' => 'gpt-test-fast',
            'services.openai.model_smart' => 'gpt-test-smart',
        ]);
        Http::preventStrayRequests();
        Sleep::fake();
        $this->enableAi($this->tenant);
    }

    protected function enableAi(Tenant $tenant, bool $assistant = true, bool $plan = true): Tenant
    {
        $tenant->putSetting('modules.ai_assistant', $assistant)->putSetting('modules.ai_plan', $plan)->save();

        return $tenant->refresh();
    }

    protected function usage(int $in = 100, int $out = 20): array
    {
        return ['input_tokens' => $in, 'output_tokens' => $out, 'total_tokens' => $in + $out,
            'input_tokens_details' => ['cached_tokens' => 0], 'output_tokens_details' => ['reasoning_tokens' => 5]];
    }

    /** Non-streaming Responses API object with a text answer. */
    protected function textResponse(string $text, array $usage = []): array
    {
        return [
            'id' => 'resp_'.uniqid(), 'object' => 'response', 'status' => 'completed', 'model' => 'gpt-test-fast',
            'output' => [
                ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => [], 'encrypted_content' => 'enc'],
                ['type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'status' => 'completed',
                    'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]],
            ],
            'usage' => $usage ?: $this->usage(),
        ];
    }

    /** Non-streaming response with function calls: [[name, args], ...]. */
    protected function toolResponse(array $calls, array $usage = []): array
    {
        $output = [['type' => 'reasoning', 'id' => 'rs_t', 'summary' => [], 'encrypted_content' => 'enc']];
        foreach ($calls as $i => [$name, $args]) {
            $output[] = ['type' => 'function_call', 'id' => 'fc_'.$i, 'call_id' => 'call_'.$i, 'name' => $name,
                'arguments' => json_encode((object) $args), 'status' => 'completed'];
        }

        return ['id' => 'resp_'.uniqid(), 'object' => 'response', 'status' => 'completed', 'model' => 'gpt-test-fast',
            'output' => $output, 'usage' => $usage ?: $this->usage(80, 15)];
    }

    /** SSE body for a streamed version of a response object (text split into deltas). */
    protected function sse(array $response, int $chunk = 7): string
    {
        $events = [['type' => 'response.created', 'response' => ['id' => $response['id'], 'status' => 'in_progress', 'output' => []]]];
        foreach ($response['output'] as $index => $item) {
            $events[] = ['type' => 'response.output_item.added', 'output_index' => $index, 'item' => array_merge($item, ['status' => 'in_progress'])];
            if ($item['type'] === 'message') {
                $text = $item['content'][0]['text'];
                foreach (mb_str_split($text, $chunk) as $piece) {
                    $events[] = ['type' => 'response.output_text.delta', 'item_id' => $item['id'], 'output_index' => $index, 'content_index' => 0, 'delta' => $piece];
                }
                $events[] = ['type' => 'response.output_text.done', 'item_id' => $item['id'], 'output_index' => $index, 'content_index' => 0, 'text' => $text];
            }
            if ($item['type'] === 'function_call') {
                $events[] = ['type' => 'response.function_call_arguments.delta', 'item_id' => $item['id'], 'output_index' => $index, 'delta' => $item['arguments']];
                $events[] = ['type' => 'response.function_call_arguments.done', 'item_id' => $item['id'], 'output_index' => $index, 'arguments' => $item['arguments']];
            }
            $events[] = ['type' => 'response.output_item.done', 'output_index' => $index, 'item' => $item];
        }
        $events[] = ['type' => 'response.completed', 'response' => $response];

        $body = '';
        foreach ($events as $i => $e) {
            $body .= 'event: '.$e['type']."\n".'data: '.json_encode($e + ['sequence_number' => $i], JSON_UNESCAPED_UNICODE)."\n\n";
        }

        return $body;
    }

    protected array $openAiQueue = [];

    protected int $sentOffset = 0;

    private bool $openAiFaked = false;

    /**
     * Queue fake Responses-endpoint replies: arrays = JSON objects, strings =
     * SSE bodies, ['__status' => 500, 'body' => …] = an error. sent($i)
     * reads the i-th request made after this call.
     */
    protected function fakeOpenAi(array $responses): void
    {
        $this->sentOffset = count(Http::recorded());
        foreach ($responses as $r) {
            $this->openAiQueue[] = $r;
        }
        if ($this->openAiFaked) {
            return;
        }
        $this->openAiFaked = true;
        Http::fake(['api.openai.test/*' => function () {
            $r = array_shift($this->openAiQueue);
            if ($r === null) {
                return Http::response(['error' => ['message' => 'fake queue empty']], 500);
            }
            if ($r instanceof \Closure) {
                return $r();
            }
            if (is_string($r)) {
                return Http::response($r, 200, ['Content-Type' => 'text/event-stream']);
            }
            if (isset($r['__status'])) {
                return Http::response($r['body'] ?? '', $r['__status'], $r['headers'] ?? []);
            }

            return Http::response($r, 200);
        }]);
    }

    /** Decoded body of the i-th OpenAI request since the last fakeOpenAi(). */
    protected function sent(int $i): array
    {
        return json_decode(Http::recorded()[$this->sentOffset + $i][0]->body(), true);
    }

    /** Parse an SSE response body into [[event, data], ...]. */
    protected function sseEvents(string $body): array
    {
        $out = [];
        foreach (preg_split("/\n\n/", trim($body)) as $block) {
            $event = null;
            $data = null;
            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }
            if ($event) {
                $out[] = [$event, $data];
            }
        }

        return $out;
    }

    protected function makeLocation(?Tenant $tenant = null, array $attrs = []): \App\Models\MapLocation
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => \App\Models\MapLocation::create(array_merge([
            'type' => 'parking', 'name' => 'Parking C', 'name_ar' => 'موقف C', 'description' => 'Main parking', 'description_ar' => 'الموقف الرئيسي',
            'latitude' => 24.1, 'longitude' => 56.7, 'icon' => 'local_parking', 'is_active' => true,
        ], $attrs)));
    }

    protected function makeTicket(\App\Models\User $user, \App\Models\Event $event, array $attrs = []): \App\Models\Ticket
    {
        return $this->inTenant(\App\Models\Tenant::find($event->tenant_id), fn () => \App\Models\Ticket::create(array_merge([
            'ticket_number' => 'T-'.uniqid(), 'event_id' => $event->id, 'user_id' => $user->id, 'booking_date' => now()->toDateString(),
            'quantity' => 1, 'ticket_type' => 'standard', 'status' => 'active', 'price' => '2.500', 'currency' => 'OMR',
            'qr_code' => 'TKT-'.uniqid(), 'holder_name' => 'Holder', 'purchase_date' => now(), 'max_check_ins' => 1, 'check_in_count' => 0,
        ], $attrs)));
    }

    protected function guestHeaders(?Tenant $tenant = null, string $guest = 'guest-device-0001', array $extra = []): array
    {
        return array_merge(['X-Tenant' => ($tenant ?? $this->tenant)->slug, 'X-Ai-Guest' => $guest], $extra);
    }
}
