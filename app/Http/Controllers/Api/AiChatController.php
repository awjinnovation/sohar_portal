<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiCall;
use App\Services\Ai\AiException;
use App\Services\Ai\AiIdentity;
use App\Services\Ai\AiUsage;
use App\Services\Ai\Concierge\ConciergeService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Visitor concierge (docs/ai_and_gate_contract.md §A3). Auth optional:
 * Sanctum user or X-Ai-Guest. `Accept: application/json` → JSON envelope,
 * anything else → Server-Sent Events (delta / card / done / error).
 */
class AiChatController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private AiUsage $usage,
        private ConciergeService $concierge,
    ) {}

    public function chat(Request $request)
    {
        $max = (int) config('ai.chat.max_message_chars', 1000);
        $data = $request->validate([
            'conversation_id' => ['nullable', 'string', 'uuid'],
            'message' => ['required', 'string', 'max:'.$max],
            'locale' => ['nullable', 'string', 'in:ar,en'],
            'context' => ['nullable', 'array'],
            'context.event_id' => ['nullable', 'integer'],
            'context.screen' => ['nullable', 'string', 'max:50'],
        ]);
        $tenant = $this->context->get();
        $locale = $data['locale'] ?? app()->getLocale();
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        app()->setLocale($locale);
        $message = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data['message']));
        $streaming = $this->wantsStream($request);

        try {
            $this->usage->ensureModule($tenant, 'ai_assistant');
            $identity = AiIdentity::from($request);
            $call = new AiCall($tenant, 'chat', $identity->user, $identity->guestHash);
            $this->admit($call, $identity, 'chat');
            if ($message === '') {
                throw new ApiException('VALIDATION_ERROR', __('validation.required', ['attribute' => 'message']), 422);
            }
            $conversation = $this->concierge->conversation($tenant, $identity->user, $identity->guestHash, $data['conversation_id'] ?? null, $locale, $message);
        } catch (AiException $e) {
            return $streaming ? $this->sseError($e) : $e->render();
        }

        $context = (array) ($data['context'] ?? []);

        if (! $streaming) {
            $result = $this->concierge->reply($tenant, $conversation, $identity->user, $identity->guestHash, $message, $locale, $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message' => [
                        'id' => $result['message']->id,
                        'text' => $result['text'],
                        'cards' => $result['cards'],
                        'suggestions' => $result['suggestions'],
                    ],
                ],
            ]);
        }

        return $this->sse(function (callable $send) use ($tenant, $conversation, $identity, $message, $locale, $context) {
            try {
                $result = $this->concierge->reply($tenant, $conversation, $identity->user, $identity->guestHash, $message, $locale, $context, $send);
                $send('done', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $result['message']->id,
                    'suggestions' => $result['suggestions'],
                ]);
            } catch (AiException $e) {
                $send('error', $e->ssePayload());
            } catch (Throwable $e) {
                report($e);
                $send('error', AiException::unavailable()->ssePayload());
            }
        });
    }

    public function feedback(Request $request, int $id)
    {
        $data = $request->validate([
            'rating' => ['required', 'in:up,down'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $tenant = $this->context->get();
        $this->usage->ensureModule($tenant, 'ai_assistant');
        $identity = AiIdentity::from($request);

        $message = AiMessage::query()->where('tenant_id', $tenant->id)->where('role', 'assistant')->find($id);
        $conversation = $message ? $this->owned($message->conversation_id, $identity) : null;
        if (! $message || ! $conversation) {
            throw new ApiException('NOT_FOUND', __('ai.errors.message_not_found'), 404);
        }
        $message->forceFill([
            'feedback_rating' => $data['rating'],
            'feedback_comment' => $data['comment'] ?? null,
            'feedback_at' => now(),
        ])->save();

        return response()->json(['success' => true, 'message' => __('ai.feedback_saved'), 'data' => ['id' => $message->id, 'rating' => $message->feedback_rating]]);
    }

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $this->usage->ensureModule($tenant, 'ai_assistant');
        $identity = AiIdentity::from($request);

        $page = $this->scoped($identity)->orderByDesc('updated_at')->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn (AiConversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'locale' => $c->locale,
                'last_message' => \Illuminate\Support\Str::limit((string) $c->messages()->reorder()->orderByDesc('id')->value('content'), 120),
                'created_at' => TenantTime::iso($c->created_at, $tenant),
                'updated_at' => TenantTime::iso($c->updated_at, $tenant),
            ])->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $tenant = $this->context->get();
        $this->usage->ensureModule($tenant, 'ai_assistant');
        $conversation = $this->owned($id, AiIdentity::from($request));
        if (! $conversation) {
            throw new ApiException('NOT_FOUND', __('ai.errors.conversation_not_found'), 404);
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'locale' => $conversation->locale,
            'created_at' => TenantTime::iso($conversation->created_at, $tenant),
            'updated_at' => TenantTime::iso($conversation->updated_at, $tenant),
            'messages' => $conversation->messages()->get()->map(fn (AiMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'text' => $m->content,
                'cards' => $m->cards ?? [],
                'suggestions' => $m->suggestions ?? [],
                'feedback' => $m->feedback_rating,
                'created_at' => TenantTime::iso($m->created_at, $tenant),
            ])->all(),
        ]]);
    }

    public function destroy(Request $request, string $id)
    {
        $tenant = $this->context->get();
        $this->usage->ensureModule($tenant, 'ai_assistant');
        $conversation = $this->owned($id, AiIdentity::from($request));
        if (! $conversation) {
            throw new ApiException('NOT_FOUND', __('ai.errors.conversation_not_found'), 404);
        }
        $conversation->delete();

        return response()->json(['success' => true, 'message' => __('ai.conversation_deleted')]);
    }

    /* ------------------------------------------------------------------ */

    /** Module/limits/budget for a visitor AI call (blocked attempts are logged). */
    private function admit(AiCall $call, AiIdentity $identity, string $feature): void
    {
        $limits = (array) config("ai.$feature.rate", []);
        $tenantId = $call->tenant->id;
        try {
            if ($identity->user) {
                $this->usage->throttle("$feature:u:$tenantId:".$identity->user->id, $limits['user'] ?? ['max' => 20, 'minutes' => 10]);
            } else {
                $this->usage->throttle("$feature:g:$tenantId:".$identity->guestHash, $limits['guest'] ?? ['max' => 8, 'minutes' => 10]);
                $this->usage->throttle("$feature:ip:$tenantId:".$identity->ip, $limits['guest_ip'] ?? ['max' => 60, 'minutes' => 10]);
            }
            $this->usage->ensureBudget($call->tenant);
        } catch (AiException $e) {
            $this->usage->recordBlocked($call, $e);
            throw $e;
        }
    }

    private function scoped(AiIdentity $identity)
    {
        return AiConversation::query()
            ->where('tenant_id', $this->context->id())
            ->ownedBy($identity->user, $identity->guestHash)
            ->where('updated_at', '>=', now()->subDays((int) config('ai.conversation_ttl_days', 30)));
    }

    private function owned(string $id, AiIdentity $identity): ?AiConversation
    {
        return $this->scoped($identity)->whereKey($id)->first();
    }

    private function wantsStream(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/event-stream')) {
            return true;
        }

        return ! str_contains($accept, 'application/json');
    }

    private function sse(callable $producer): StreamedResponse
    {
        return response()->stream(function () use ($producer) {
            @set_time_limit(120);
            $flush = function () {
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            echo ": ok\n\n";
            $flush();
            $send = function (string $event, array $data) use ($flush) {
                echo 'event: '.$event."\n".'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                $flush();
            };
            $producer($send);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Pre-stream refusal as a one-event SSE body (with the matching HTTP status). */
    private function sseError(AiException $e): StreamedResponse
    {
        $response = $this->sse(fn (callable $send) => $send('error', $e->ssePayload()));
        $response->setStatusCode($e->status);
        if (isset($e->extra['retry_after'])) {
            $response->headers->set('Retry-After', (string) $e->extra['retry_after']);
        }

        return $response;
    }
}
