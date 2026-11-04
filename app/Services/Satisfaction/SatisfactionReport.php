<?php

namespace App\Services\Satisfaction;

use App\Models\Event;
use App\Models\SatisfactionResponse;
use App\Models\Tenant;
use App\Support\TenantTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Admin report of the satisfaction survey «راضي» (docs/radi_satisfaction_contract.md §4).
 * Filters: from / to (tenant-local days, default the last 30), event (id,
 * `none` = general answers without an event) for the whole page; score
 * (1–5) for the comments list and the CSV export only.
 */
class SatisfactionReport
{
    public const DEFAULT_DAYS = 30;

    public const MAX_DAYS = 366;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $event = null,
        public readonly ?int $score = null,
    ) {}

    public static function fromRequest(Request $request, Tenant $tenant): self
    {
        $today = CarbonImmutable::parse(TenantTime::today($tenant));
        $date = fn (?string $v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? CarbonImmutable::parse($v) : null;

        $to = $date($request->query('to')) ?? $today;
        $from = $date($request->query('from')) ?? $to->subDays(self::DEFAULT_DAYS - 1);
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        $event = (string) $request->query('event', '');
        $event = $event === 'none' || ctype_digit($event) ? $event : null;
        $score = (int) $request->query('score', 0);

        return new self($tenant, $from->toDateString(), $to->toDateString(), $event ?: null, $score >= 1 && $score <= 5 ? $score : null);
    }

    /** Responses of the period and event (all scores). */
    public function base(): Builder
    {
        return SatisfactionResponse::query()
            ->whereBetween('response_date', [$this->from, $this->to])
            ->when($this->event === 'none', fn ($q) => $q->whereNull('event_id'))
            ->when($this->event !== null && $this->event !== 'none', fn ($q) => $q->where('event_id', (int) $this->event));
    }

    /** base() narrowed to the score filter (comments list, export). */
    public function rows(): Builder
    {
        return $this->base()->when($this->score, fn ($q) => $q->where('score', $this->score));
    }

    /** @return array{responses:int, average:?float, csat:?int, comments:int, contact:int, today:int} */
    public function kpis(): array
    {
        $min = SatisfactionResponse::SATISFIED_MIN;
        $row = $this->base()->toBase()->selectRaw(
            "count(*) as n, avg(score) as avg_score, sum(case when score >= $min then 1 else 0 end) as sat,
             sum(case when comment is not null then 1 else 0 end) as comments, sum(case when contact_ok then 1 else 0 end) as contact"
        )->first();
        $n = (int) ($row->n ?? 0);

        return [
            'responses' => $n,
            'average' => $n ? round((float) $row->avg_score, 1) : null,
            'csat' => $n ? (int) round(100 * (int) $row->sat / $n) : null,
            'comments' => (int) ($row->comments ?? 0),
            'contact' => (int) ($row->contact ?? 0),
            'today' => (int) $this->base()->where('response_date', TenantTime::today($this->tenant))->count(),
        ];
    }

    /** Responses and average per day of the period (days without answers included). */
    public function trend(): array
    {
        $byDay = $this->base()->toBase()
            ->selectRaw('response_date as d, count(*) as n, avg(score) as avg_score')
            ->groupBy('response_date')->get()
            ->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        $out = ['labels' => [], 'responses' => [], 'average' => []];
        for ($d = CarbonImmutable::parse($this->from); $d->lessThanOrEqualTo(CarbonImmutable::parse($this->to)); $d = $d->addDay()) {
            $row = $byDay->get($d->toDateString());
            $out['labels'][] = $d->format('m-d');
            $out['responses'][] = (int) ($row->n ?? 0);
            $out['average'][] = $row ? round((float) $row->avg_score, 2) : null;
        }

        return $out;
    }

    /** @return array<int, int> score => count, 5 down to 1 */
    public function distribution(): array
    {
        $counts = $this->base()->toBase()->selectRaw('score, count(*) as n')->groupBy('score')->pluck('n', 'score');
        $out = [];
        foreach ([5, 4, 3, 2, 1] as $s) {
            $out[$s] = (int) ($counts[$s] ?? 0);
        }

        return $out;
    }

    /** Average per aspect, in the tenant's aspect order (keys no longer configured come last). */
    public function aspects(): array
    {
        $sum = $count = [];
        foreach ($this->base()->whereNotNull('aspects')->select(['id', 'aspects'])->lazyById(500) as $r) {
            foreach ((array) $r->aspects as $key => $value) {
                if (is_numeric($value) && $value >= 1 && $value <= 5) {
                    $sum[$key] = ($sum[$key] ?? 0) + (int) $value;
                    $count[$key] = ($count[$key] ?? 0) + 1;
                }
            }
        }

        $keys = array_values(array_unique(array_merge(RadiSettings::aspectKeys($this->tenant), array_keys($count))));
        $out = [];
        foreach ($keys as $key) {
            $out[] = [
                'key' => (string) $key,
                'label' => RadiSettings::aspectLabel($this->tenant, (string) $key),
                'average' => isset($count[$key]) ? round($sum[$key] / $count[$key], 1) : null,
                'count' => $count[$key] ?? 0,
            ];
        }

        return $out;
    }

    /** Responses, average and CSAT per event, most answered first. */
    public function perEvent(): array
    {
        $min = SatisfactionResponse::SATISFIED_MIN;
        $rows = $this->base()->toBase()
            ->selectRaw("event_id, count(*) as n, avg(score) as avg_score, sum(case when score >= $min then 1 else 0 end) as sat")
            ->groupBy('event_id')->orderByDesc('n')->limit(100)->get();
        $events = Event::query()->whereIn('id', $rows->pluck('event_id')->filter())->get(['id', 'title', 'title_ar'])->keyBy('id');

        return $rows->map(fn ($r) => [
            'event_id' => $r->event_id,
            'title' => $r->event_id ? (self::eventTitle($events->get($r->event_id)) ?? '#'.$r->event_id) : null,
            'responses' => (int) $r->n,
            'average' => round((float) $r->avg_score, 1),
            'csat' => (int) round(100 * (int) $r->sat / max(1, (int) $r->n)),
        ])->all();
    }

    /** Event filter choices: the tenant's events, newest first. */
    public function eventOptions()
    {
        return Event::query()->orderByDesc('start_time')->limit(300)->get(['id', 'title', 'title_ar']);
    }

    public static function eventTitle(?Event $event): ?string
    {
        if (! $event) {
            return null;
        }

        return app()->getLocale() === 'ar' ? ($event->title_ar ?: $event->title) : ($event->title ?: $event->title_ar);
    }

    /** Query string of the current filters (links, export). */
    public function query(array $merge = []): array
    {
        return array_filter(array_merge([
            'from' => $this->from, 'to' => $this->to, 'event' => $this->event, 'score' => $this->score,
        ], $merge), fn ($v) => $v !== null && $v !== '');
    }
}
