<?php

namespace App\Services\Ai;

use App\Models\AiPlan;
use App\Models\Event;
use App\Models\MapLocation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantTime;

/**
 * "Plan my visit" (contract §A4): one structured-output call on the smart
 * model with the day's real events + places as DATA, then strict server
 * validation — unknown / other-tenant ids and event items outside the
 * event's open window are fixed or dropped, costs are recomputed from the
 * server price, totals and budget warnings are server-side. Arabic text
 * written by the model goes through ArabicTextFixer.
 */
class PlanService
{
    public const KINDS = ['event', 'food', 'break', 'travel', 'activity'];

    public function __construct(private OpenAiClient $client) {}

    public function schema(): array
    {
        $item = [
            'type' => 'object',
            'properties' => [
                'start' => ['type' => 'string', 'description' => 'HH:MM 24h'],
                'end' => ['type' => 'string', 'description' => 'HH:MM 24h'],
                'kind' => ['type' => 'string', 'enum' => self::KINDS],
                'event_id' => ['type' => ['integer', 'null']],
                'location_id' => ['type' => ['integer', 'null']],
                'title' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'cost_minor' => ['type' => ['integer', 'null']],
            ],
            'required' => ['start', 'end', 'kind', 'event_id', 'location_id', 'title', 'note', 'cost_minor'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => $item],
                'tips' => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'summary', 'items', 'tips', 'warnings'],
            'additionalProperties' => false,
        ];
    }

    /** Events running on $date (tenant) and the places a plan may use. */
    public function data(Tenant $tenant, EventFacts $facts, string $date): array
    {
        $events = Event::query()->where('tenant_id', $tenant->id)->where('is_active', true)->with(['category', 'mapLocation'])
            ->where('start_time', '<=', $date.' 23:59:59')->where('end_time', '>=', $date.' 00:00:00')
            ->orderBy('start_time')->limit(25)->get();

        $eventRows = [];
        foreach ($events as $event) {
            $window = $facts->windowOn($event, $date);
            if (! $window) {
                continue;
            }
            $row = $facts->event($event);
            unset($row['start'], $row['end'], $row['daily_hours'], $row['featured']);
            $row['open'] = $window[0];
            $row['close'] = $window[1];
            $row['map_location_id'] = $event->map_location_id;
            $eventRows[$event->id] = ['event' => $event, 'row' => $row, 'window' => $window];
        }

        $locations = MapLocation::query()->where('tenant_id', $tenant->id)->where('is_active', true)
            ->whereIn('type', ['food', 'restaurant', 'entertainment', 'heritage', 'fort', 'beach', 'shopping', 'parking', 'venue', 'stage', 'facilities', 'restroom', 'first_aid', 'info'])
            ->orderBy('id')->limit(40)->get()->keyBy('id');

        return [
            'events' => $eventRows,
            'locations' => $locations,
        ];
    }

    /**
     * @param  array{date:string, start_time?:?string, end_time?:?string, party:array, interests:array, budget_minor?:?int, notes?:?string, locale:string}  $req
     */
    public function create(Tenant $tenant, ?User $user, ?string $guestHash, array $req): AiPlan
    {
        $locale = $req['locale'];
        $facts = new EventFacts($tenant, $locale);
        $data = $this->data($tenant, $facts, $req['date']);
        $call = new AiCall($tenant, 'plan', $user, $guestHash);

        $people = max(1, (int) ($req['party']['adults'] ?? 1) + (int) ($req['party']['kids'] ?? 0));
        $doc = [
            'date' => $req['date'],
            'weekday' => \Carbon\Carbon::parse($req['date'])->englishDayOfWeek,
            'visitor_window' => [($req['start_time'] ?? null) ?: '09:00', ($req['end_time'] ?? null) ?: '23:30'],
            'party' => [
                'adults' => (int) ($req['party']['adults'] ?? 1),
                'kids' => (int) ($req['party']['kids'] ?? 0),
                'kids_ages' => array_values(array_map('intval', (array) ($req['party']['kids_ages'] ?? []))),
                'people' => $people,
            ],
            'interests' => array_values($req['interests'] ?? []),
            'budget' => isset($req['budget_minor']) ? $facts->amount((int) $req['budget_minor']).' (budget_minor '.(int) $req['budget_minor'].')' : null,
            'notes' => $req['notes'] ?? null,
            'events' => array_values(array_map(fn ($e) => $e['row'], $data['events'])),
            'locations' => $data['locations']->map(fn ($l) => $facts->location($l))->values()->all(),
        ];

        $payload = $this->client->payload('smart', [
            'instructions' => Prompts::plan($tenant, $locale),
            'input' => [['role' => 'user', 'content' => 'DATA: '.json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'visit_plan', 'schema' => $this->schema(), 'strict' => true]],
            'max_output_tokens' => (int) config('ai.plan.max_output_tokens', 2500),
            'safety_identifier' => $call->safetyIdentifier(),
        ]);
        $response = $this->client->create($payload, $call);
        $raw = $response->json();
        if (! $raw) {
            throw AiException::unavailable('plan_invalid_json');
        }

        $plan = $this->validate($tenant, $facts, $data, $raw, $req, $people);

        $model = AiPlan::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'guest_key' => $user ? null : $guestHash,
            'locale' => $locale,
            'date' => $req['date'],
            'request' => array_intersect_key($req, array_flip(['date', 'start_time', 'end_time', 'party', 'interests', 'budget_minor', 'locale'])),
            'plan' => $plan,
        ]);

        return $model;
    }

    /** Server-side validation / repair of the model output. */
    public function validate(Tenant $tenant, EventFacts $facts, array $data, array $raw, array $req, int $people): array
    {
        $locale = $req['locale'];
        $fix = fn (string $s) => $locale === 'ar' ? ArabicTextFixer::fix($s) : $s;
        $warnings = [];
        $items = [];
        $dropped = 0;
        $winStart = $this->time($req['start_time'] ?? null) ?? '00:00';
        $winEnd = $this->time($req['end_time'] ?? null) ?? '23:59';

        foreach ((array) ($raw['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $kind = in_array($item['kind'] ?? null, self::KINDS, true) ? $item['kind'] : 'activity';
            $start = $this->time($item['start'] ?? null);
            $end = $this->time($item['end'] ?? null);
            if (! $start || ! $end) {
                $dropped++;

                continue;
            }
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }
            $eventId = is_numeric($item['event_id'] ?? null) ? (int) $item['event_id'] : null;
            $locationId = is_numeric($item['location_id'] ?? null) ? (int) $item['location_id'] : null;
            $cost = null;
            $title = $fix(trim(strip_tags((string) ($item['title'] ?? ''))));

            if ($kind === 'event' || $eventId) {
                if (! $eventId || ! isset($data['events'][$eventId])) {
                    $dropped++;          // invented / other-tenant / not running that day

                    continue;
                }
                $kind = 'event';
                [$open, $close] = $data['events'][$eventId]['window'];
                $start = max($start, $open);
                $end = min($end, $close);
                if ($end <= $start) {
                    $dropped++;          // outside the event's hours

                    continue;
                }
                $event = $data['events'][$eventId]['event'];
                $cost = $facts->priceMinor($event) * $people;
                $title = $facts->tr($event, 'title');
                $locationId = $event->map_location_id;
            }
            if ($locationId !== null && ! $data['locations']->has($locationId)
                && ! MapLocation::query()->where('tenant_id', $tenant->id)->where('is_active', true)->whereKey($locationId)->exists()) {
                $locationId = null;
            }
            // Clip to the visitor window.
            $start = max($start, $winStart);
            $end = min($end, $winEnd);
            if ($end <= $start) {
                $dropped++;

                continue;
            }

            $items[] = [
                'start' => $start,
                'end' => $end,
                'kind' => $kind,
                'event_id' => $kind === 'event' ? $eventId : null,
                'location_id' => $locationId,
                'title' => mb_substr($title !== '' ? $title : __('ai.plan.kinds.'.$kind, [], $locale), 0, 120),
                'note' => mb_substr($fix(trim(strip_tags((string) ($item['note'] ?? '')))), 0, 300),
                'cost_minor' => $cost,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['start'], $b['start']));
        $items = array_slice($items, 0, 12);
        $total = array_sum(array_map(fn ($i) => (int) ($i['cost_minor'] ?? 0), $items));

        if (! array_filter($items, fn ($i) => $i['kind'] === 'event')) {
            $warnings[] = __('ai.plan.no_events', [], $locale);
        }
        if (isset($req['budget_minor']) && $req['budget_minor'] !== null && $total > (int) $req['budget_minor']) {
            $warnings[] = __('ai.plan.over_budget', ['total' => $facts->amount($total), 'budget' => $facts->amount((int) $req['budget_minor'])], $locale);
        }
        foreach ((array) ($raw['warnings'] ?? []) as $w) {
            if (is_string($w) && trim($w) !== '' && count($warnings) < 4) {
                $warnings[] = mb_substr($fix(trim(strip_tags($w))), 0, 200);
            }
        }

        return [
            'title' => mb_substr($fix(trim(strip_tags((string) ($raw['title'] ?? '')))) ?: __('ai.plan.default_title', [], $locale), 0, 120),
            'summary' => mb_substr($fix(trim(strip_tags((string) ($raw['summary'] ?? '')))), 0, 600),
            'date' => $req['date'],
            'items' => $items,
            'total_cost_minor' => $total,
            'total_cost' => $facts->money($total),
            'currency' => $tenant->currency(),
            'tips' => array_values(array_slice(array_filter(array_map(fn ($t) => is_string($t) ? mb_substr($fix(trim(strip_tags($t))), 0, 200) : '', (array) ($raw['tips'] ?? []))), 0, 4)),
            'warnings' => array_values($warnings),
            'dropped_items' => $dropped,
        ];
    }

    private function time(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return $h === 24 && $i === 0 ? '23:59' : null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }

    public function present(AiPlan $plan): array
    {
        return array_merge(['id' => $plan->id], $plan->plan, [
            'created_at' => TenantTime::iso($plan->created_at),
        ]);
    }
}
