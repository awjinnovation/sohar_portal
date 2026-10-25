<?php

namespace App\Models;

use App\Support\TenantTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A ticket type of an event (docs/ticket_types_contract.md §1): General,
 * Family (admits 4), VIP, Child, Senior… each with its own price and rules.
 *
 * Capacity is counted in tickets per booking date. `capacity` null = the
 * type only shares the event's daily capacity; otherwise both limits apply.
 * Sales window times are tenant wall-clock (like events.start_time).
 *
 * @property array $name {en, ar}
 * @property array|null $description {en, ar}
 */
class EventTicketType extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const CATEGORIES = ['general', 'family', 'vip', 'child', 'senior', 'student', 'group', 'other'];

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_BOX_OFFICE = 'box_office';

    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_BOX_OFFICE];

    /** Sales channels: the visitor app sees public types, the gate box office sees both. */
    public const CHANNEL_APP = 'app';
    public const CHANNEL_BOX_OFFICE = 'box_office';

    /** Default colour per category (when the type has none). */
    public const CATEGORY_COLORS = [
        'general' => '#2F8E80', 'family' => '#E5883C', 'vip' => '#B8860B', 'child' => '#3B82F6',
        'senior' => '#8B5CF6', 'student' => '#0EA5E9', 'group' => '#DB2777', 'other' => '#6B7280',
    ];

    /** Material Symbols / Bootstrap icon hint per category (apps map it). */
    public const CATEGORY_ICONS = [
        'general' => 'confirmation_number', 'family' => 'family_restroom', 'vip' => 'workspace_premium', 'child' => 'child_care',
        'senior' => 'elderly', 'student' => 'school', 'group' => 'groups', 'other' => 'local_activity',
    ];

    protected $fillable = [
        'event_id', 'key', 'name', 'description', 'category', 'price_minor', 'admits', 'capacity', 'per_order_max',
        'min_age', 'max_age', 'requires_id', 'sales_start', 'sales_end', 'visibility', 'color', 'sort', 'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price_minor' => 'integer',
        'admits' => 'integer',
        'capacity' => 'integer',
        'per_order_max' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'requires_id' => 'boolean',
        'sales_start' => \App\Casts\TenantLocalDateTime::class,
        'sales_end' => \App\Casts\TenantLocalDateTime::class,
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Event.price mirrors the default (General) type (older clients read `price`).
        $sync = function (EventTicketType $type) {
            $event = Event::withoutTenantScope()->find($type->event_id);
            $event?->syncPriceFromTypes();
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'ticket_type_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForChannel(Builder $q, string $channel): Builder
    {
        return $channel === self::CHANNEL_BOX_OFFICE ? $q : $q->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('id');
    }

    public function visibleIn(string $channel): bool
    {
        return $channel === self::CHANNEL_BOX_OFFICE || $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function nameText(?string $locale = null): string
    {
        return self::pickLocale($this->name, $locale) ?: $this->key;
    }

    public function descriptionText(?string $locale = null): ?string
    {
        return self::pickLocale($this->description, $locale) ?: null;
    }

    public function colorValue(): string
    {
        return $this->color ?: (self::CATEGORY_COLORS[$this->category] ?? self::CATEGORY_COLORS['other']);
    }

    /** Within the sales window right now (tenant wall-clock). */
    public function salesOpen(): bool
    {
        $now = TenantTime::localNow();
        if ($this->sales_start && $this->sales_start->format('Y-m-d H:i:s') > $now) {
            return false;
        }

        return ! ($this->sales_end && $this->sales_end->format('Y-m-d H:i:s') < $now);
    }

    /**
     * Tickets left of this type for a date, within the event capacity:
     * null = unlimited. Counts issued tickets (active / used) and seats
     * held by pending gate orders.
     */
    public function availableOn(string $date, ?int $eventAvailable = null): ?int
    {
        $event = $this->relationLoaded('event') ? $this->event : Event::withoutTenantScope()->find($this->event_id);
        $eventAvailable ??= $event ? $event->getAvailableTicketsForDate($date) : -1;
        $eventLeft = $eventAvailable === -1 ? null : max(0, $eventAvailable);

        if ($this->capacity === null) {
            return $eventLeft;
        }
        $own = max(0, $this->capacity - $this->soldOn($date) - $this->heldOn($date));

        return $eventLeft === null ? $own : min($own, $eventLeft);
    }

    public function soldOn(string $date): int
    {
        return (int) Ticket::withoutTenantScope()->where('ticket_type_id', $this->id)
            ->whereDate('booking_date', substr($date, 0, 10))->whereIn('status', ['active', 'used'])->count();
    }

    public function heldOn(string $date): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('box_office_order_lines as l')
            ->join('box_office_orders as o', 'o.id', '=', 'l.order_id')
            ->where('l.ticket_type_id', $this->id)
            ->whereDate('o.booking_date', substr($date, 0, 10))
            ->where('o.status', BoxOfficeOrder::STATUS_PENDING)
            ->where('o.expires_at', '>', now())
            ->sum('l.quantity');
    }

    /** True when tickets were ever sold for this type (it can then only be deactivated). */
    public function hasSales(): bool
    {
        return Ticket::withoutTenantScope()->where('ticket_type_id', $this->id)->exists()
            || BoxOfficeOrderLine::where('ticket_type_id', $this->id)->exists();
    }

    /**
     * API shape (docs/ticket_types_contract.md §2). `available` is for $date
     * (null = unlimited).
     */
    public function toApi(?string $date = null, ?Tenant $tenant = null, ?int $eventAvailable = null): array
    {
        $tenant ??= app(\App\Tenancy\TenantContext::class)->get();
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => ['en' => (string) ($this->name['en'] ?? ''), 'ar' => (string) ($this->name['ar'] ?? '')],
            'name_text' => $this->nameText($locale),
            'description_text' => $this->descriptionText($locale),
            'category' => $this->category,
            'icon' => self::CATEGORY_ICONS[$this->category] ?? self::CATEGORY_ICONS['other'],
            'price_minor' => (int) $this->price_minor,
            'price' => $tenant ? $tenant->formatMoney((int) $this->price_minor) : \App\Support\Money::format((int) $this->price_minor),
            'is_free' => (int) $this->price_minor === 0,
            'admits' => max(1, (int) $this->admits),
            'per_order_max' => max(1, (int) $this->per_order_max),
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'requires_id' => (bool) $this->requires_id,
            'color' => $this->colorValue(),
            'available' => $date ? $this->availableOn($date, $eventAvailable) : null,
            'sales_open' => $this->salesOpen(),
            'sales_start' => TenantTime::iso($this->sales_start, $tenant),
            'sales_end' => TenantTime::iso($this->sales_end, $tenant),
            'visibility' => $this->visibility,
        ];
    }

    /** {name_text, category, color, requires_id, min_age, max_age} for gate scan results. */
    public function toScanApi(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name_text' => $this->nameText(),
            'category' => $this->category,
            'color' => $this->colorValue(),
            'requires_id' => (bool) $this->requires_id,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
        ];
    }

    public static function pickLocale(?array $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $value ??= [];
        $other = $locale === 'ar' ? 'en' : 'ar';

        return trim((string) (($value[$locale] ?? '') !== '' ? $value[$locale] : ($value[$other] ?? '')));
    }
}
