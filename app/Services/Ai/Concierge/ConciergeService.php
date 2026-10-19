<?php

namespace App\Services\Ai\Concierge;

use App\Exceptions\ApiException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Event;
use App\Models\ContentItem;
use App\Models\MapLocation;
use App\Models\Place;
use App\Support\MediaPresenter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiCall;
use App\Services\Ai\ArabicTextFixer;
use App\Services\Ai\EventFacts;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\Prompts;
use Illuminate\Support\Str;

/**
 * Visitor concierge (contract §A3): conversation bookkeeping, the tool
 * loop against OpenAI (streamed or not), card / suggestion extraction and
 * validation. Callers (Api\AiChatController) do the module / rate /
 * budget checks first.
 */
class ConciergeService
{
    public const ACTIONS = ['open_event', 'buy_ticket', 'open_map', 'open_wallet', 'top_up', 'open_refunds', 'open_plan'];

    public function __construct(private OpenAiClient $client) {}

    public function conversation(Tenant $tenant, ?User $user, ?string $guestHash, ?string $id, string $locale, string $firstMessage): AiConversation
    {
        if ($id) {
            $conversation = AiConversation::query()->where('tenant_id', $tenant->id)->find($id);
            if (! $conversation || ! $conversation->ownedBy($user, $guestHash)) {
                throw new ApiException('NOT_FOUND', __('ai.errors.conversation_not_found'), 404);
            }

            return $conversation;
        }

        return AiConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'guest_key' => $user ? null : $guestHash,
            'locale' => $locale,
            'title' => Str::limit(trim(preg_replace('/\s+/u', ' ', $firstMessage)), 60, '…'),
        ]);
    }

    /**
     * Answer one visitor message. $emit(string $event, array $data) streams
     * `delta` / `card` events; null = non-streaming. Arabic punctuation is
     * repaired by ArabicTextFixer: per delta while streaming (single-character
     * mappings only), and in full on the stored / returned text.
     *
     * @return array{conversation: AiConversation, message: AiMessage, text: string, cards: array, suggestions: array}
     */
    /**
     * Language of the answer: the language the visitor wrote in (more Arabic
     * letters → ar, more Latin letters → en), falling back to the `locale`
     * the app sent when the message has neither (numbers, emoji…).
     */
    public static function replyLocale(string $message, string $fallback): string
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $message);
        $latin = preg_match_all('/[A-Za-z]/', $message);
        if ($arabic > $latin) {
            return 'ar';
        }
        if ($latin > $arabic) {
            return 'en';
        }

        return in_array($fallback, ['ar', 'en'], true) ? $fallback : 'ar';
    }

    public function reply(Tenant $tenant, AiConversation $conversation, ?User $user, ?string $guestHash, string $message, string $locale, array $context = [], ?callable $emit = null): array
    {
        // Answer (text, tool data, cards, suggestions) in the visitor's language.
        $locale = self::replyLocale($message, $locale);
        $facts = new EventFacts($tenant, $locale);
        $tools = new ConciergeTools($tenant, $facts, $user);
        $call = new AiCall($tenant, 'chat', $user, $guestHash);

        $contextEvent = null;
        if (! empty($context['event_id'])) {
            $contextEvent = Event::query()->where('tenant_id', $tenant->id)->where('is_active', true)->find((int) $context['event_id']);
        }

        // History (oldest first), then the new message.
        $limit = (int) config('ai.chat.max_history_messages', 12);
        $history = $conversation->messages()->reorder()->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        $input = [];
        foreach ($history as $m) {
            $content = $m->content;
            if ($m->role === 'assistant' && $m->cards) {
                $refs = collect($m->cards)->filter(fn ($c) => in_array($c['type'] ?? '', ['event', 'location', 'place', 'content'], true))
                    ->map(fn ($c) => $c['type'].' #'.$c['id'].' '.($c['title'] ?? $c['name'] ?? ''))->implode('; ');
                if ($refs !== '') {
                    $content .= "\n[shown: {$refs}]";
                }
            }
            $input[] = ['role' => $m->role === 'assistant' ? 'assistant' : 'user', 'content' => $content];
        }
        $input[] = ['role' => 'user', 'content' => $message];

        AiMessage::create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        $filter = new MetaFilter;
        $delta = $emit ? ArabicTextFixer::stream(fn (string $text) => $emit('delta', ['text' => $text])) : null;
        $tokens = 0;
        $rounds = max(1, (int) config('ai.chat.max_tool_rounds', 4));
        $instructions = Prompts::concierge($tenant, $locale, (bool) $user, $contextEvent, $context['screen'] ?? null);

        for ($round = 1; $round <= $rounds; $round++) {
            $payload = $this->client->payload('fast', [
                'instructions' => $instructions,
                'input' => $input,
                'tools' => $tools->definitions(),
                'tool_choice' => $round === $rounds ? 'none' : 'auto',
                'parallel_tool_calls' => true,
                'max_output_tokens' => (int) config('ai.chat.max_output_tokens', 1200),
                'include' => ['reasoning.encrypted_content'],
                'safety_identifier' => $call->safetyIdentifier(),
            ]);

            if ($emit) {
                $response = $this->client->stream($payload, $call, function (string $type, array $data) use ($filter, $delta) {
                    if ($type === 'response.output_text.delta' && isset($data['delta']) && is_string($data['delta'])) {
                        $visible = $filter->feed($data['delta']);
                        if ($visible !== '') {
                            $delta($visible);
                        }
                    }
                });
            } else {
                $response = $this->client->create($payload, $call);
                $filter->feed($response->text());
            }
            $tokens += $response->totalTokens();

            $calls = $response->functionCalls();
            if (! $calls) {
                break;
            }
            foreach ($response->output() as $item) {
                $input[] = $item;
            }
            foreach (array_slice($calls, 0, 6) as $fc) {
                $result = $tools->execute($fc['name'], $fc['arguments']);
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $fc['call_id'],
                    'output' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        $rest = $filter->flush();
        if ($delta && $rest !== '') {
            $delta($rest);
        }
        $text = ArabicTextFixer::fix($filter->visibleText());
        if ($text === '') {
            $text = __('ai.chat.no_answer', [], $locale);
            if ($emit) {
                $emit('delta', ['text' => $text]);
            }
        }

        $meta = $filter->meta() ?? [];
        $cards = $this->cards($tenant, $facts, $tools, (array) ($meta['cards'] ?? []), $text, $locale);
        $suggestions = $this->suggestions($tenant, (array) ($meta['suggestions'] ?? []), $locale);

        $assistant = AiMessage::create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $text,
            'cards' => $cards,
            'suggestions' => $suggestions,
            'tokens' => $tokens,
        ]);
        $conversation->touch();

        if ($emit) {
            foreach ($cards as $card) {
                $emit('card', $card);
            }
        }

        return ['conversation' => $conversation, 'message' => $assistant, 'text' => $text, 'cards' => $cards, 'suggestions' => $suggestions];
    }

    /**
     * Cards are rebuilt from the database: the model only names ids. Unknown
     * ids, other tenants' records, other users' tickets and disabled actions
     * are dropped. Without a META block, events/locations the tools returned
     * and the answer names are used.
     */
    public function cards(Tenant $tenant, EventFacts $facts, ConciergeTools $tools, array $requested, string $text, string $locale): array
    {
        $entities = [];
        $actions = [];
        $seen = [];

        if (! $requested) {
            foreach (array_keys($tools->seenEvents) as $id) {
                $event = Event::query()->where('tenant_id', $tenant->id)->find($id);
                if ($event && $this->mentions($text, [$event->title, $event->title_ar])) {
                    $requested[] = ['type' => 'event', 'id' => $id];
                }
            }
            foreach (array_keys($tools->seenLocations) as $id) {
                $loc = MapLocation::query()->where('tenant_id', $tenant->id)->find($id);
                if ($loc && $this->mentions($text, [$loc->name, $loc->name_ar])) {
                    $requested[] = ['type' => 'location', 'id' => $id];
                }
            }
            foreach (array_keys($tools->seenPlaces) as $id) {
                $place = Place::query()->where('tenant_id', $tenant->id)->find($id);
                if ($place && $this->mentions($text, array_values((array) $place->name))) {
                    $requested[] = ['type' => 'place', 'id' => $id];
                }
            }
            foreach (array_keys($tools->seenContent) as $id) {
                $item = ContentItem::query()->where('tenant_id', $tenant->id)->find($id);
                if ($item && $this->mentions($text, array_values((array) $item->title))) {
                    $requested[] = ['type' => 'content', 'id' => $id];
                }
            }
        }

        foreach ($requested as $card) {
            if (! is_array($card)) {
                continue;
            }
            $type = $card['type'] ?? null;
            $id = isset($card['id']) && is_numeric($card['id']) ? (int) $card['id'] : null;

            if ($type === 'event' && $id && count($entities) < 4 && ! isset($seen["e$id"])) {
                $event = Event::query()->where('tenant_id', $tenant->id)->where('is_active', true)->with('mapLocation')->find($id);
                if ($event) {
                    $entities[] = $facts->eventCard($event);
                    $seen["e$id"] = true;
                }
            } elseif ($type === 'location' && $id && count($entities) < 4 && ! isset($seen["l$id"])) {
                $loc = MapLocation::query()->where('tenant_id', $tenant->id)->where('is_active', true)->find($id);
                if ($loc) {
                    $entities[] = $facts->locationCard($loc);
                    $seen["l$id"] = true;
                }
            } elseif ($type === 'place' && $id && count($entities) < 4 && ! isset($seen["p$id"])) {
                $card = $this->placeCard($tenant, $id, $locale);
                if ($card) {
                    $entities[] = $card;
                    $seen["p$id"] = true;
                }
            } elseif ($type === 'content' && $id && count($entities) < 4 && ! isset($seen["c$id"])) {
                $card = $this->contentCard($tenant, $id, $locale);
                if ($card) {
                    $entities[] = $card;
                    $seen["c$id"] = true;
                }
            } elseif ($type === 'ticket' && $id && isset($tools->seenTickets[$id]) && count($entities) < 4 && ! isset($seen["t$id"])) {
                $entities[] = $tools->seenTickets[$id];
                $seen["t$id"] = true;
            } elseif ($type === 'action' && count($actions) < 2) {
                $action = $this->action($tenant, (string) ($card['action'] ?? ''), $card['event_id'] ?? null, $locale);
                if ($action && ! isset($seen['a'.$action['action'].json_encode($action['params'])])) {
                    $actions[] = $action;
                    $seen['a'.$action['action'].json_encode($action['params'])] = true;
                }
            }
        }

        return array_merge($entities, $actions);
    }

    /**
     * Place card (docs/places_content_contract.md §5): {type, id, name, category,
     * icon, cover_url, latitude, longitude} + category_key, color. Published
     * places of this tenant only, and only while the module is on.
     */
    public function placeCard(Tenant $tenant, int $id, string $locale): ?array
    {
        if (! $tenant->moduleEnabled('places')) {
            return null;
        }
        $place = Place::query()->where('tenant_id', $tenant->id)->published()->with(['category', 'media'])->find($id);
        if (! $place || ($place->category && ! $place->category->is_active)) {
            return null;
        }
        $cover = MediaPresenter::image($place->getFirstMedia('cover'));

        return [
            'type' => 'place',
            'id' => $place->id,
            'name' => $place->t('name', $locale),
            'category' => $place->category?->t('name', $locale),
            'category_key' => $place->category?->key,
            'icon' => $place->displayIcon(),
            'color' => $place->displayColor(),
            'cover_url' => $cover['thumb_url'] ?? null,
            'latitude' => (float) $place->latitude,
            'longitude' => (float) $place->longitude,
        ];
    }

    /** Content card (§5): {type, id, collection_key, title, image_url, link_url}. */
    public function contentCard(Tenant $tenant, int $id, string $locale): ?array
    {
        if (! $tenant->moduleEnabled('content')) {
            return null;
        }
        $item = ContentItem::query()->where('tenant_id', $tenant->id)->published()
            ->whereHas('collection', fn ($c) => $c->where('is_active', true))->with(['collection', 'media'])->find($id);
        if (! $item) {
            return null;
        }
        $image = MediaPresenter::image($item->getFirstMedia('image'));

        return [
            'type' => 'content',
            'id' => $item->id,
            'collection_key' => $item->collection->key,
            'title' => $item->t('title', $locale),
            'image_url' => $image['thumb_url'] ?? null,
            'link_url' => $item->link_url,
        ];
    }

    private function action(Tenant $tenant, string $action, mixed $eventId, string $locale): ?array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return null;
        }
        $module = ['buy_ticket' => 'tickets', 'open_map' => 'map', 'open_wallet' => 'wallet', 'top_up' => 'topup', 'open_refunds' => 'refunds', 'open_plan' => 'ai_plan', 'open_event' => 'events'][$action];
        if (! $tenant->moduleEnabled($module) || ($action === 'top_up' && ! $tenant->moduleEnabled('wallet'))) {
            return null;
        }
        $params = [];
        if (in_array($action, ['open_event', 'buy_ticket'], true)) {
            $event = is_numeric($eventId) ? Event::query()->where('tenant_id', $tenant->id)->where('is_active', true)->find((int) $eventId) : null;
            if (! $event) {
                return null;
            }
            $params['event_id'] = $event->id;
        }

        return ['type' => 'action', 'action' => $action, 'label' => __('ai.actions.'.$action, [], $locale), 'params' => (object) $params];
    }

    private function mentions(string $text, array $names): bool
    {
        $hay = mb_strtolower($text);
        foreach (array_filter($names) as $name) {
            $name = mb_strtolower(trim((string) $name));
            if ($name !== '' && mb_strlen($name) >= 4 && str_contains($hay, $name)) {
                return true;
            }
        }

        return false;
    }

    public function suggestions(Tenant $tenant, array $fromModel, string $locale): array
    {
        $out = [];
        foreach ($fromModel as $s) {
            if (is_string($s) && ($s = trim(strip_tags($s))) !== '' && mb_strlen($s) <= 80) {
                $out[] = ArabicTextFixer::fix($s);
            }
            if (count($out) === 3) {
                break;
            }
        }
        if (! $out) {
            foreach ((array) $tenant->setting('ai.suggestions', []) as $s) {
                $value = is_array($s) ? ($s[$locale] ?? $s['en'] ?? null) : $s;
                if (is_string($value) && $value !== '') {
                    $out[] = $value;
                }
            }
            $out = array_slice($out, 0, 3);
        }

        return $out;
    }
}
