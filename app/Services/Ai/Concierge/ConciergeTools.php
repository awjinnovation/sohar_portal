<?php

namespace App\Services\Ai\Concierge;

use App\Models\Announcement;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Support\Geo;
use App\Support\HtmlSanitizer;
use App\Models\Event;
use App\Models\MapLocation;
use App\Models\PointsAccount;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ai\EventFacts;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server-side tools of the visitor concierge (contract §A3). Every query
 * runs inside the current tenant (global TenantScope) AND is pinned to
 * `$tenant->id` explicitly, so data of another tenant can never reach the
 * model. User tools (my_tickets / my_wallet) only exist when signed in and
 * only read the caller's own rows. Results are compact JSON for the model;
 * the entities they return are remembered so cards can only reference
 * records the server actually looked up or that exist in this tenant.
 */
class ConciergeTools
{
    /** @var array<int, true> event ids returned by tools this turn */
    public array $seenEvents = [];

    /** @var array<int, true> */
    public array $seenLocations = [];

    /** @var array<int, true> place ids returned by find_places */
    public array $seenPlaces = [];

    /** @var array<int, true> content item ids returned by search_content */
    public array $seenContent = [];

    /** @var array<int, array> ticket cards of the caller */
    public array $seenTickets = [];

    public function __construct(
        private Tenant $tenant,
        private EventFacts $facts,
        private ?User $user = null,
    ) {}

    /** Responses API tool definitions (strict JSON schema). */
    public function definitions(): array
    {
        $nullable = fn (string $type, string $description) => ['type' => [$type, 'null'], 'description' => $description];
        $tool = fn (string $name, string $description, array $properties = []) => [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $properties,
                'required' => array_keys($properties),
                'additionalProperties' => false,
            ],
        ];

        $tools = [
            $tool('search_events', 'Search this organizer\'s published events. Use for any question about what is on, dates, prices or free events. Dates are YYYY-MM-DD in the organizer time zone; an event matches when it runs on any day of the range.', [
                'query' => $nullable('string', 'Keywords (title, venue, description) in Arabic or English, or null for all.'),
                'date_from' => $nullable('string', 'First day YYYY-MM-DD, or null.'),
                'date_to' => $nullable('string', 'Last day YYYY-MM-DD, or null.'),
                'category' => $nullable('string', 'Category name, or null.'),
                'free_only' => $nullable('boolean', 'true = only free events.'),
            ]),
            $tool('get_event', 'Full details of one event by id (description, hours, ticket types, deposit).', [
                'id' => ['type' => 'integer', 'description' => 'Event id from search_events.'],
            ]),
            $tool('list_announcements', 'Current announcements / notices from the organizer.'),
            $tool('find_locations', 'Find places on the venue map: parking, restrooms, first aid, food, stages, gates, info desks, heritage sites.', [
                'query' => $nullable('string', 'Name keywords, or null.'),
                'category' => $nullable('string', 'One of: '.implode(', ', array_keys(MapLocation::getTypes())).'; or null.'),
            ]),
            $tool('get_policies', 'Organizer policies: refunds (window, fee, minimum, methods), deposits, loyalty points rules, wallet top-up limits, enabled features, support contacts, venue info / opening hours if provided.'),
        ];

        // Places to visit + content (docs/places_content_contract.md §5), only when the module is on.
        if ($this->tenant->moduleEnabled('places')) {
            $categories = PlaceCategory::query()->where('tenant_id', $this->tenant->id)->where('is_active', true)->orderBy('sort')->pluck('key')->all();
            $tools[] = $tool('find_places', 'Places to visit published by this organizer (forts, beaches, souqs, parks, museums, event venues…) with their wilayat/area, summary and coordinates. Use for "what can I visit", "places in <town>", "near me".', [
                'query' => $nullable('string', 'Keywords (name, wilayat/town, summary) in Arabic or English, or null.'),
                'category' => $nullable('string', 'Category key'.($categories ? ' (one of: '.implode(', ', $categories).')' : '').', or null.'),
                'near' => $nullable('string', '"lat,lng" to sort by distance (only if the visitor shared a location), or null.'),
            ]);
        }
        if ($this->tenant->moduleEnabled('content')) {
            $collections = ContentCollection::query()->where('tenant_id', $this->tenant->id)->where('is_active', true)->orderBy('sort')->pluck('key')->all();
            $tools[] = $tool('search_content', 'The organizer\'s published content: visitor services (ticket office, first aid, lost and found, parking…), partners/brands, visitor guide tips and information pages.', [
                'query' => $nullable('string', 'Keywords in Arabic or English, or null for all.'),
                'collection' => $nullable('string', 'Collection key'.($collections ? ' (one of: '.implode(', ', $collections).')' : '').', or null.'),
            ]);
        }

        if ($this->user) {
            $tools[] = $tool('my_tickets', 'The signed-in visitor\'s own tickets (summary).');
            $tools[] = $tool('my_wallet', 'The signed-in visitor\'s own wallet balance and points (summary).');
        }

        return $tools;
    }

    /**
     * Run a tool call. Never throws: errors come back as {"error": "..."}.
     */
    public function execute(string $name, array $args): array
    {
        try {
            return match ($name) {
                'search_events' => $this->searchEvents($args),
                'get_event' => $this->getEvent((int) ($args['id'] ?? 0)),
                'list_announcements' => $this->announcements(),
                'find_locations' => $this->findLocations($args),
                'get_policies' => $this->policies(),
                'find_places' => $this->tenant->moduleEnabled('places') ? $this->findPlaces($args) : ['error' => 'module_disabled'],
                'search_content' => $this->tenant->moduleEnabled('content') ? $this->searchContent($args) : ['error' => 'module_disabled'],
                'my_tickets' => $this->user ? $this->myTickets() : ['error' => 'not_signed_in'],
                'my_wallet' => $this->user ? $this->myWallet() : ['error' => 'not_signed_in'],
                default => ['error' => 'unknown_tool'],
            };
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'tool_failed'];
        }
    }

    private function events()
    {
        return Event::query()->where('tenant_id', $this->tenant->id)->where('is_active', true)->with(['category', 'mapLocation']);
    }

    private function date(?string $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public function searchEvents(array $args): array
    {
        $from = $this->date($args['date_from'] ?? null);
        $to = $this->date($args['date_to'] ?? null);
        if ($from && $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        $query = trim((string) ($args['query'] ?? ''));
        $category = trim((string) ($args['category'] ?? ''));
        $freeOnly = (bool) ($args['free_only'] ?? false);

        $base = function () use ($from, $to, $category, $freeOnly) {
            $q = $this->events();
            if ($from || $to) {
                $q->where('start_time', '<=', ($to ?? $from).' 23:59:59')
                    ->where('end_time', '>=', ($from ?? TenantTime::today($this->tenant)).' 00:00:00');
            } else {
                $q->where('end_time', '>=', TenantTime::localNow($this->tenant));
            }
            if ($category !== '') {
                $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$category}%")->orWhere('name_ar', 'like', "%{$category}%"));
            }
            if ($freeOnly) {
                $q->where(fn ($p) => $p->whereNull('price')->orWhere('price', '<=', 0));
            }

            return $q;
        };

        $note = null;
        $q = $base();
        if ($query !== '') {
            $terms = array_slice(array_filter(preg_split('/\s+/u', $query)), 0, 5);
            $q->where(function ($w) use ($terms) {
                foreach ($terms as $term) {
                    foreach (['title', 'title_ar', 'description', 'description_ar', 'location', 'location_ar', 'organizer_name', 'organizer_name_ar'] as $col) {
                        $w->orWhere($col, 'like', '%'.$term.'%');
                    }
                }
            });
        }
        $events = $q->orderBy('start_time')->limit(8)->get();
        if ($events->isEmpty() && $query !== '') {
            $events = $base()->orderBy('start_time')->limit(8)->get();
            $note = 'No event matched the keywords; these are all events in the requested range.';
        }
        $events = $events->filter(fn ($e) => $this->isFreeFilter($e, $freeOnly))->values();

        foreach ($events as $event) {
            $this->seenEvents[$event->id] = true;
        }

        $result = [
            'range' => ['from' => $from, 'to' => $to],
            'count' => $events->count(),
            'events' => $events->map(fn ($e) => $this->facts->event($e))->all(),
        ];
        if ($events->isEmpty()) {
            // Nearest real alternatives: events still running now or coming up (outside the range).
            $next = $this->events()->where('end_time', '>=', TenantTime::localNow($this->tenant))
                ->orderBy('start_time')->limit(4)->get();
            foreach ($next as $event) {
                $this->seenEvents[$event->id] = true;
            }
            $result['nearest_other_dates'] = $next->map(fn ($e) => $this->facts->event($e))->all();
            $note = $next->isEmpty()
                ? 'No events in this range and no other upcoming events.'
                : 'No events in this range. nearest_other_dates lists events running now or coming up on other dates — offer them (with their real dates).';
        }
        if ($note) {
            $result['note'] = $note;
        }

        return $result;
    }

    private function isFreeFilter(Event $event, bool $freeOnly): bool
    {
        return ! $freeOnly || $this->facts->isFree($event);
    }

    public function getEvent(int $id): array
    {
        $event = $this->events()->find($id);
        if (! $event) {
            return ['error' => 'event_not_found', 'id' => $id];
        }
        $this->seenEvents[$event->id] = true;
        $facts = $this->facts->event($event, true);
        $facts['today'] = TenantTime::today($this->tenant);
        $window = $this->facts->windowOn($event, TenantTime::today($this->tenant));
        $facts['open_today'] = $window ? $window[0].'–'.$window[1] : null;

        return $facts;
    }

    public function announcements(): array
    {
        $items = Announcement::query()->where('tenant_id', $this->tenant->id)->active()
            ->orderByDesc('is_pinned')->orderByDesc('priority')->orderByDesc('start_datetime')->limit(8)->get();

        return ['announcements' => $items->map(fn (Announcement $a) => [
            'title' => $this->facts->tr($a, 'title'),
            'content' => Str::limit(trim(strip_tags((string) $this->facts->tr($a, 'content'))), 400),
            'type' => $a->type,
            'from' => TenantTime::iso($a->start_datetime, $this->tenant),
            'until' => TenantTime::iso($a->end_datetime, $this->tenant),
        ])->all()];
    }

    public function findLocations(array $args): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        $category = $args['category'] ?? null;
        $types = array_keys(MapLocation::getTypes());

        $q = MapLocation::query()->where('tenant_id', $this->tenant->id)->where('is_active', true);
        if (is_string($category) && in_array($category, $types, true)) {
            $related = ['food' => ['food', 'restaurant'], 'restaurant' => ['food', 'restaurant'], 'first_aid' => ['first_aid', 'emergency'], 'emergency' => ['emergency', 'first_aid']][$category] ?? [$category];
            $q->whereIn('type', $related);
        }
        if ($query !== '') {
            $q->where(function ($w) use ($query) {
                foreach (array_slice(array_filter(preg_split('/\s+/u', $query)), 0, 4) as $term) {
                    $w->orWhere('name', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")->orWhere('description_ar', 'like', "%{$term}%");
                }
            });
        }
        $locations = $q->orderBy('id')->limit(10)->get();
        foreach ($locations as $loc) {
            $this->seenLocations[$loc->id] = true;
        }

        return [
            'map_enabled' => $this->tenant->moduleEnabled('map'),
            'count' => $locations->count(),
            'locations' => $locations->map(fn ($l) => $this->facts->location($l))->all(),
        ];
    }

    public function findPlaces(array $args): array
    {
        $locale = $this->facts->locale();
        $query = trim((string) ($args['query'] ?? ''));
        $category = trim((string) ($args['category'] ?? ''));
        $near = Geo::parse(is_string($args['near'] ?? null) ? $args['near'] : null);

        $base = function () use ($category) {
            $q = Place::query()->where('tenant_id', $this->tenant->id)->published()->with('category')
                ->where(fn ($w) => $w->whereNull('category_id')->orWhereHas('category', fn ($c) => $c->where('is_active', true)));
            if ($category !== '') {
                $q->whereHas('category', fn ($c) => $c->where('key', $category)->orWhere('name', 'like', '%'.$category.'%'));
            }

            return $q;
        };
        $note = null;
        $q = $base();
        if ($query !== '') {
            $q->searchLocalized($query, ['name', 'summary', 'wilayat', 'address']);
        }
        $places = $q->orderByDesc('featured')->orderBy('sort')->limit(30)->get();
        if ($places->isEmpty() && $query !== '') {
            $places = $base()->orderByDesc('featured')->orderBy('sort')->limit(30)->get();
            $note = 'No place matched the keywords; these are all places'.($category !== '' ? ' of the category' : '').'.';
        }
        if ($near) {
            $places->each(fn (Place $p) => $p->distance_m = Geo::distanceMeters($near[0], $near[1], (float) $p->latitude, (float) $p->longitude));
            $places = $places->sortBy('distance_m')->values();
        }
        $places = $places->take(8);
        foreach ($places as $p) {
            $this->seenPlaces[$p->id] = true;
        }

        $out = [
            'count' => $places->count(),
            'places' => $places->map(fn (Place $p) => array_filter([
                'id' => $p->id,
                'name' => $p->t('name', $locale),
                'category' => $p->category?->t('name', $locale),
                'wilayat' => $p->t('wilayat', $locale),
                'summary' => $p->t('summary', $locale),
                'description' => HtmlSanitizer::toText($p->t('description', $locale), 300) ?: null,
                'opening_hours' => $p->t('opening_hours', $locale),
                'entry_fee' => $p->entry_fee_minor === null ? null : ($p->entry_fee_minor === 0 ? 'free' : $this->facts->amount($p->entry_fee_minor)),
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'distance_km' => isset($p->distance_m) ? round($p->distance_m / 1000, 1) : null,
            ], fn ($v) => $v !== null && $v !== ''))->all(),
            'unknown_fields_note' => 'Opening hours / entry fee missing = not published; say so instead of guessing.',
        ];
        if ($note) {
            $out['note'] = $note;
        }

        return $out;
    }

    public function searchContent(array $args): array
    {
        $locale = $this->facts->locale();
        $query = trim((string) ($args['query'] ?? ''));
        $collection = trim((string) ($args['collection'] ?? ''));

        $base = function () use ($collection) {
            $q = ContentItem::query()->where('tenant_id', $this->tenant->id)->published()
                ->whereHas('collection', fn ($c) => $c->where('is_active', true))->with(['collection', 'place']);
            if ($collection !== '') {
                $q->whereHas('collection', fn ($c) => $c->where('key', $collection));
            }

            return $q;
        };
        $note = null;
        $q = $base();
        if ($query !== '') {
            $q->searchLocalized($query, ['title', 'subtitle', 'body', 'tags']);
        }
        $items = $q->orderByDesc('featured')->orderBy('sort')->limit(10)->get();
        if ($items->isEmpty() && $query !== '') {
            $items = $base()->orderByDesc('featured')->orderBy('sort')->limit(10)->get();
            $note = 'No item matched the keywords; these are items'.($collection !== '' ? ' of the collection' : ' of all collections').'.';
        }
        foreach ($items as $i) {
            $this->seenContent[$i->id] = true;
        }

        $pages = collect();
        if ($collection === '') {
            $pq = ContentPage::query()->where('tenant_id', $this->tenant->id)->published();
            if ($query !== '') {
                $pq->searchLocalized($query, ['title', 'body']);
            }
            $pages = $pq->orderBy('sort')->limit(3)->get();
        }

        $out = [
            'count' => $items->count(),
            'items' => $items->map(fn (ContentItem $i) => array_filter([
                'id' => $i->id,
                'collection' => $i->collection?->key,
                'collection_name' => $i->collection?->t('name', $locale),
                'title' => $i->t('title', $locale),
                'subtitle' => $i->t('subtitle', $locale),
                'text' => HtmlSanitizer::toText($i->t('body', $locale), 400) ?: null,
                'phone' => $i->phone,
                'email' => $i->email,
                'place' => $i->place && $i->place->isPublished() ? $i->place->t('name', $locale) : null,
                'has_link' => $i->link_url ? true : null,
            ], fn ($v) => $v !== null && $v !== ''))->all(),
            'pages' => $pages->map(fn (ContentPage $p) => [
                'slug' => $p->slug,
                'title' => $p->t('title', $locale),
                'text' => HtmlSanitizer::toText($p->t('body', $locale), 700),
            ])->all(),
        ];
        if ($note) {
            $out['note'] = $note;
        }

        return $out;
    }

    public function policies(): array
    {
        $t = $this->tenant;
        $cur = $t->currency();
        $m = fn (int $minor) => $this->facts->amount($minor);
        $modules = array_keys(array_filter((array) $t->setting('modules', [])));
        $locale = $this->facts->locale();

        $out = [
            'organizer' => $t->localizedName($locale),
            'currency' => $cur.' ('.$t->currencyDecimals().' decimals)',
            'time_zone' => TenantTime::zone($t),
            'enabled_features' => $modules,
            'support' => array_filter((array) $t->setting('tenant.support', [])),
        ];
        $venue = data_get($t->setting('map.venue', []), $locale) ?: data_get($t->setting('map.venue', []), 'en');
        if ($venue) {
            $out['main_venue'] = $venue;
        }
        $info = data_get($t->setting('ai.venue_info', []), $locale) ?: data_get($t->setting('ai.venue_info', []), $locale === 'ar' ? 'en' : 'ar');
        if ($info) {
            $out['venue_info'] = Str::limit((string) $info, 2000);
        }
        if ($t->moduleEnabled('wallet')) {
            $out['wallet'] = [
                'closed_loop' => 'Wallet credit can be spent on tickets and at the organizer\'s vendors only.',
                'topup_min' => $m((int) $t->setting('wallet.min_topup_minor', 0)),
                'topup_max' => $m((int) $t->setting('wallet.max_topup_minor', 0)),
                'max_balance' => $m((int) $t->setting('wallet.max_balance_minor', 0)),
                'topup_bonus' => array_map(fn ($b) => ['from' => $m((int) ($b['min_minor'] ?? 0)), 'bonus_percent' => $b['bonus_percent'] ?? 0], (array) $t->setting('wallet.topup_bonus', [])),
                'promo_credit' => 'Bonus / points credit is spent first and is never refunded'.($t->setting('wallet.promo_expiry_days') ? '; it expires after '.$t->setting('wallet.promo_expiry_days').' days.' : '.'),
            ];
        }
        if ($t->moduleEnabled('refunds')) {
            $r = (array) $t->setting('wallet.refunds', []);
            $out['refunds'] = [
                'enabled' => (bool) ($r['enabled'] ?? false),
                'window_days_after_last_topup' => (int) ($r['window_days'] ?? 0),
                'fee' => $m((int) ($r['fee_minor'] ?? 0)),
                'minimum' => $m((int) ($r['min_minor'] ?? 0)),
                'methods' => array_values((array) ($r['methods'] ?? [])),
                'how' => 'Request in the app: Wallet → Refund. Only unspent top-up (cash) balance minus held deposits is refundable; the organizer reviews each request.',
            ];
        }
        if ($t->moduleEnabled('deposits')) {
            $out['deposits'] = 'Some items (wristbands, cups, lockers, equipment) hold a refundable deposit from the wallet; it is released when the item is returned to the vendor.';
        }
        if ($t->moduleEnabled('points')) {
            $p = (array) $t->setting('points', []);
            $label = data_get($p, 'label.'.$locale) ?: data_get($p, 'label.en', 'Points');
            $out['points'] = [
                'name' => $label,
                'earn' => ($p['earn_per_currency_unit'] ?? 0).' '.$label.' per 1 '.$cur.' spent on tickets and at vendors',
                'redeem_value' => $m((int) ($p['redeem_value_minor_per_point'] ?? 0)).' per point',
                'min_redeem' => (int) ($p['min_redeem_points'] ?? 0),
                'expiry_days' => (int) ($p['expiry_days'] ?? 0) ?: null,
                'tiers' => array_map(fn ($tier) => [
                    'name' => data_get($tier, 'name.'.$locale) ?: data_get($tier, 'name.en'),
                    'from_points' => (int) ($tier['min_points'] ?? 0),
                    'multiplier' => $tier['multiplier'] ?? 1,
                ], (array) ($p['tiers'] ?? [])),
            ];
        }

        return $out;
    }

    public function myTickets(): array
    {
        $tickets = Ticket::query()->where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)
            ->with('event')->orderByDesc('booking_date')->orderByDesc('id')->limit(10)->get();

        $rows = [];
        foreach ($tickets as $t) {
            $card = [
                'type' => 'ticket',
                'id' => $t->id,
                'event_title' => $t->event ? $this->facts->tr($t->event, 'title') : (string) $t->ticket_type,
                'date' => $t->booking_date?->toDateString() ?? $t->event?->start_time?->toDateString(),
                'status' => $t->status,
            ];
            $this->seenTickets[$t->id] = $card;
            $rows[] = $card + ['event_id' => $t->event_id, 'quantity' => (int) $t->quantity, 'ticket_type' => $t->ticket_type];
        }

        return ['count' => count($rows), 'tickets' => array_map(function ($r) {
            unset($r['type']);

            return $r;
        }, $rows)];
    }

    public function myWallet(): array
    {
        $out = [];
        if ($this->tenant->moduleEnabled('wallet')) {
            $wallet = Wallet::query()->where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->first();
            $out['wallet'] = $wallet ? [
                'status' => $wallet->status,
                'balance' => $this->facts->amount($wallet->balance_minor),
                'available' => $this->facts->amount($wallet->availableMinor()),
                'promo_credit' => $this->facts->amount($wallet->promo_minor),
                'held_deposits' => $this->facts->amount($wallet->held_minor),
                'refundable' => $this->facts->amount($wallet->refundableMinor()),
            ] : ['status' => 'none', 'note' => 'No wallet yet; it is created on the first top-up.'];
        }
        if ($this->tenant->moduleEnabled('points')) {
            $account = PointsAccount::query()->where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->first();
            $out['points'] = ['balance' => (int) ($account?->balance ?? 0), 'lifetime_earned' => (int) ($account?->lifetime_earned ?? 0)];
        }

        return $out ?: ['note' => 'Wallet and points are not enabled for this organizer.'];
    }
}
