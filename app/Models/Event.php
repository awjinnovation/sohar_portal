<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use \App\Tenancy\BelongsToTenant;
    use \App\Models\Concerns\BustsPublicApiCache;

    use InteractsWithMedia;
    protected $fillable = [
        'title', 'title_ar', 'description', 'description_ar', 'category_id',
        'start_time', 'end_time', 'location', 'location_ar', 'map_location_id',
        'image_url', 'images', 'price', 'currency', 'available_tickets', 'total_tickets',
        'organizer_name', 'organizer_name_ar', 'is_featured', 'is_active', 'pricing',
        'deposit_minor', 'deposit_type',
    ];

    protected $casts = [
        // Wall-clock times in the tenant time zone (what organizers type).
        'start_time' => \App\Casts\TenantLocalDateTime::class,
        'end_time' => \App\Casts\TenantLocalDateTime::class,
        'price' => 'decimal:3',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'images' => 'array',
        'pricing' => 'array',
        'deposit_minor' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function mapLocation()
    {
        return $this->belongsTo(MapLocation::class);
    }

    public function tags()
    {
        return $this->hasMany(EventTag::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /** Ticket types (docs/ticket_types_contract.md), in display order. */
    public function ticketTypes()
    {
        return $this->hasMany(EventTicketType::class)->orderBy('sort')->orderBy('id');
    }

    protected static function booted(): void
    {
        // Every event has at least one type: `general` at the event price
        // (plus one per legacy `pricing` tier), like the migration backfill.
        static::created(fn (Event $event) => $event->ensureDefaultTicketTypes());
    }

    public function ensureDefaultTicketTypes(): void
    {
        if (EventTicketType::withoutTenantScope()->where('event_id', $this->id)->exists()) {
            return;
        }
        $decimals = Tenant::find($this->tenant_id)?->currencyDecimals() ?? 3;
        $pricing = is_array($this->pricing) ? $this->pricing : [];
        $standard = collect($pricing)->first(fn ($p) => in_array($p['type'] ?? null, ['standard', 'general'], true));
        $rows = [['general', ['en' => 'General admission', 'ar' => 'تذكرة عامة'], 'general',
            \App\Support\Money::toMinor((string) ($standard['price'] ?? $this->getRawOriginal('price') ?? $this->price ?? 0), $decimals)]];
        $labels = ['vip' => ['VIP', 'كبار الشخصيات', 'vip'], 'premium' => ['Premium', 'مميزة', 'other'], 'early_bird' => ['Early bird', 'حجز مبكر', 'other']];
        foreach ($pricing as $tier) {
            $key = (string) ($tier['type'] ?? '');
            if ($key === '' || in_array($key, ['standard', 'general'], true) || collect($rows)->contains(0, $key)) {
                continue;
            }
            [$en, $ar, $cat] = $labels[$key] ?? [\Illuminate\Support\Str::headline($key), $key, 'other'];
            $rows[] = [$key, ['en' => $en, 'ar' => $ar], $cat, \App\Support\Money::toMinor((string) ($tier['price'] ?? 0), $decimals)];
        }
        foreach ($rows as $i => [$key, $name, $category, $price]) {
            $type = new EventTicketType([
                'event_id' => $this->id, 'key' => $key, 'name' => $name, 'category' => $category, 'price_minor' => $price,
                'admits' => 1, 'per_order_max' => (int) config('payments.max_tickets_per_order', 10), 'sort' => $i,
                'visibility' => EventTicketType::VISIBILITY_PUBLIC, 'is_active' => true,
            ]);
            $type->tenant_id = $this->tenant_id;
            $type->saveQuietly();
        }
    }

    /** Active types sold in a channel (app = public only, box_office = public + box_office). */
    public function sellableTicketTypes(string $channel = EventTicketType::CHANNEL_APP)
    {
        return EventTicketType::withoutTenantScope()->where('event_id', $this->id)->active()
            ->forChannel($channel)->ordered()->get()->each->setRelation('event', $this);
    }

    /** The type a legacy single-`quantity` order maps to: `general`, else the first active type of the channel. */
    public function defaultTicketType(string $channel = EventTicketType::CHANNEL_APP): ?EventTicketType
    {
        $types = $this->sellableTicketTypes($channel);

        return $types->firstWhere('key', 'general') ?? $types->first();
    }

    /** Type by key for legacy callers (`standard` = the default type). */
    public function ticketTypeByKey(?string $key, string $channel = EventTicketType::CHANNEL_APP): ?EventTicketType
    {
        if ($key === null || $key === '' || $key === 'standard') {
            return $this->defaultTicketType($channel);
        }

        return $this->sellableTicketTypes($channel)->firstWhere('key', $key);
    }

    /** Date shown with type availability: today while the event runs, else its first day (null without dates). */
    public function nextSaleDate(): ?string
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }
        $today = \Carbon\Carbon::now(\App\Support\TenantTime::zone(Tenant::find($this->tenant_id)))->toDateString();
        $first = $this->start_time->toDateString();
        $last = $this->end_time->toDateString();

        return $today < $first ? $first : ($today > $last ? $last : $today);
    }

    /** Cheapest active public type, or null when the event has none. */
    public function priceFromMinor(): ?int
    {
        $min = EventTicketType::withoutTenantScope()->where('event_id', $this->id)->active()
            ->where('visibility', EventTicketType::VISIBILITY_PUBLIC)->min('price_minor');

        return $min === null ? null : (int) $min;
    }

    /**
     * Keep the legacy `price` column equal to the default type's price
     * (General; older clients and the AI tools read `price`). The cheapest
     * type is price_from.
     */
    public function syncPriceFromTypes(): void
    {
        $from = $this->defaultTicketType()?->price_minor;
        if ($from === null) {
            return;
        }
        $decimals = Tenant::find($this->tenant_id)?->currencyDecimals() ?? 3;
        $price = \App\Support\Money::format($from, $decimals);
        if (\App\Support\Money::toMinor((string) $this->getRawOriginal('price'), $decimals) !== $from) {
            $this->forceFill(['price' => $price])->saveQuietly();
        }
    }

    /** Places to visit where this event is held (docs/places_content_contract.md §2). */
    public function places()
    {
        return $this->belongsToMany(Place::class, 'event_place');
    }

    /**
     * Server-side unit price in minor units for a ticket type. A matching
     * entry in the `pricing` JSON ([{type, price}]) wins over the base price.
     */
    public function unitPriceMinor(?string $ticketType = null, int $decimals = 3): int
    {
        // Ticket types are authoritative (docs/ticket_types_contract.md).
        if ($ticketType && ($type = $this->ticketTypeByKey($ticketType, EventTicketType::CHANNEL_BOX_OFFICE))) {
            return (int) $type->price_minor;
        }
        if ($ticketType && is_array($this->pricing)) {
            foreach ($this->pricing as $tier) {
                if (($tier['type'] ?? null) === $ticketType && isset($tier['price'])) {
                    return \App\Support\Money::toMinor((string) $tier['price'], $decimals);
                }
            }
        }

        return \App\Support\Money::toMinor((string) ($this->getRawOriginal('price') ?? $this->price ?? 0), $decimals);
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', \App\Support\TenantTime::localNow());
    }

    /**
     * Check if event has unlimited tickets
     * -1 means unlimited
     */
    public function hasUnlimitedTickets(): bool
    {
        return $this->total_tickets === -1;
    }

    /**
     * Seats left for a date (-1 = unlimited): the daily capacity minus the
     * tickets issued for that date (active or already used) and the seats
     * held by pending, unexpired gate box office orders
     * (docs/gate_sales_contract.md §2).
     */
    public function getAvailableTicketsForDate(string $date): int
    {
        if ($this->hasUnlimitedTickets()) {
            return -1;
        }

        $date = substr($date, 0, 10);
        $issued = (int) Ticket::withoutTenantScope()
            ->where('event_id', $this->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['active', 'used'])
            ->sum('quantity');
        $held = (int) BoxOfficeOrder::withoutTenantScope()
            ->where('event_id', $this->id)
            ->whereDate('booking_date', $date)
            ->holding()
            ->sum('quantity');

        return max(0, (int) $this->total_tickets - $issued - $held);
    }

    /**
     * Get availability for all dates in event range
     */
    public function getDailyAvailability(): array
    {
        if (!$this->start_time || !$this->end_time) {
            return [];
        }

        $availability = [];
        $currentDate = $this->start_time->copy()->startOfDay();
        $endDate = $this->end_time->copy()->startOfDay();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');
            $available = $this->getAvailableTicketsForDate($dateString);

            $availability[] = [
                'date' => $dateString,
                'day_name' => $currentDate->format('l'),
                'available_tickets' => $available,
                'is_unlimited' => $available === -1,
                'is_sold_out' => $available === 0,
                'is_available' => $available !== 0,
            ];

            $currentDate->addDay();
        }

        return $availability;
    }

    /**
     * Check if tickets are available for a specific date
     */
    public function canBookForDate(string $date, int $quantity = 1): bool
    {
        // Check if date is within event range
        $bookingDate = \Carbon\Carbon::parse($date, $this->start_time->getTimezone());
        if ($bookingDate < $this->start_time->startOfDay() || $bookingDate > $this->end_time->endOfDay()) {
            return false;
        }

        // Unlimited tickets always available
        if ($this->hasUnlimitedTickets()) {
            return true;
        }

        $available = $this->getAvailableTicketsForDate($date);
        return $available >= $quantity;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }
}