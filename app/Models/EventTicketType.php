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
 * Undated types (`validity` any_day / all_days, §7) have no booking date:
 * their `capacity` is a total for the whole event (sold + held, all time)
 * and the event's daily capacity does not apply to them.
 * Sales window times are tenant wall-clock (like events.start_time).
 *
 * @property array $name {en, ar}
 * @property array|null $description {en, ar}
 */
class EventTicketType extends Model
{
    use \App\Models\Concerns\BustsPublicApiCache;
    use \App\Tenancy\BelongsToTenant;

    public const CATEGORIES = ['general', 'family', 'vip', 'child', 'senior', 'student', 'group', 'other'];

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_BOX_OFFICE = 'box_office';

    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_BOX_OFFICE];

    /**
     * Validity (docs/ticket_types_contract.md §7): `date` = the visitor picks
     * a day and the ticket admits on that gate day only; `any_day` = no date,
     * one visit (its `admits` entries) on any day the event runs; `all_days`
     * = no date, `admits` entries on every day the event runs (a season pass).
     */
    public const VALIDITY_DATE = 'date';

    public const VALIDITY_ANY_DAY = 'any_day';

    public const VALIDITY_ALL_DAYS = 'all_days';

    public const VALIDITIES = [self::VALIDITY_DATE, self::VALIDITY_ANY_DAY, self::VALIDITY_ALL_DAYS];

    /** Sales channels: the visitor app sees public types, the gate box office sees both. */
    public const CHANNEL_APP = 'app';

    public const CHANNEL_BOX_OFFICE = 'box_office';

    /**
     * Default colour per category (when the type has none): green for
     * general admission, yellow for children… The organizer usually sets
     * the colour of the printed ticket / wristband instead (v1.2); gate apps
     * and the gate statistics draw each type in its colour.
     */
    public const CATEGORY_COLORS = [
        'general' => '#16A34A', 'family' => '#F97316', 'vip' => '#7C3AED', 'child' => '#FACC15',
        'senior' => '#0EA5E9', 'student' => '#2563EB', 'group' => '#DB2777', 'other' => '#6B7280',
    ];

    /**
     * Preset swatches of the admin editor: common printed ticket / wristband
     * colours, hex => Arabic name (translated with __()).
     */
    public const SWATCHES = [
        '#16A34A' => 'أخضر', '#FACC15' => 'أصفر', '#FFFFFF' => 'أبيض', '#F97316' => 'برتقالي',
        '#DC2626' => 'أحمر', '#2563EB' => 'أزرق', '#0EA5E9' => 'سماوي', '#7C3AED' => 'بنفسجي',
        '#DB2777' => 'وردي', '#92400E' => 'بني', '#6B7280' => 'رمادي', '#111827' => 'أسود',
    ];

    /**
     * Text on a light badge. Max-contrast rule (same as the apps): dark text
     * when its contrast (L + 0.05) / 0.05 beats white's 1.05 / (L + 0.05),
     * i.e. relative luminance L > 0.179.
     */
    public const TEXT_ON_LIGHT = '#111827';

    public const LIGHT_LUMINANCE = 0.179;

    /** Material Symbols / Bootstrap icon hint per category (apps map it). */
    public const CATEGORY_ICONS = [
        'general' => 'confirmation_number', 'family' => 'family_restroom', 'vip' => 'workspace_premium', 'child' => 'child_care',
        'senior' => 'elderly', 'student' => 'school', 'group' => 'groups', 'other' => 'local_activity',
    ];

    protected $fillable = [
        'event_id', 'key', 'name', 'description', 'category', 'price_minor', 'admits', 'validity', 'capacity', 'per_order_max',
        'min_age', 'max_age', 'requires_id', 'sales_start', 'sales_end', 'visibility', 'color', 'sort', 'is_active',
    ];

    protected $attributes = [
        'validity' => self::VALIDITY_DATE,
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

    /** `date`, `any_day` or `all_days` (unknown values read as `date`). */
    public function validityValue(): string
    {
        return self::normalizeValidity($this->validity);
    }

    /** Bought for one chosen day (needs a booking date). */
    public function isDated(): bool
    {
        return $this->validityValue() === self::VALIDITY_DATE;
    }

    public static function normalizeValidity(?string $validity): string
    {
        return in_array($validity, self::VALIDITIES, true) ? $validity : self::VALIDITY_DATE;
    }

    public function colorValue(): string
    {
        return $this->color ?: (self::CATEGORY_COLORS[$this->category] ?? self::CATEGORY_COLORS['other']);
    }

    /** WCAG relative luminance of `#RRGGBB` / `#RGB` (0 = black, 1 = white); null when unreadable. */
    public static function luminance(?string $hex): ?float
    {
        $h = ltrim(trim((string) $hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (strlen($h) === 8) {
            $h = substr($h, 2); // AARRGGBB
        }
        if (strlen($h) !== 6 || ! ctype_xdigit($h)) {
            return null;
        }
        $c = array_map(function (string $p) {
            $v = hexdec($p) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split($h, 2));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** A light colour (yellow, white, green…) takes dark text (max-contrast rule). */
    public static function isLightColor(?string $hex): bool
    {
        return (self::luminance($hex) ?? 0) > self::LIGHT_LUMINANCE;
    }

    /** Text / icon colour on a badge of [$hex]: dark on light colours, else white. */
    public static function textColorFor(?string $hex): string
    {
        return self::isLightColor($hex) ? self::TEXT_ON_LIGHT : '#FFFFFF';
    }

    /** Very light colours (white) also need a border to be seen on a white page. */
    public static function needsBorder(?string $hex): bool
    {
        return (self::luminance($hex) ?? 0) > 0.8;
    }

    /**
     * Inline CSS variables of a type badge (`.vb-badge`, `.bo-badge`):
     * the colour, its text colour and a ring for white. Null = general.
     */
    public static function badgeStyle(?string $hex): string
    {
        $hex = $hex ?: self::CATEGORY_COLORS['general'];

        return '--type-color: '.$hex.'; --type-text: '.self::textColorFor($hex).';'
            .(self::needsBorder($hex) ? ' --type-ring: inset 0 0 0 1px #D1D5DB;' : '');
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
     * held by pending gate orders. Undated types ignore the date: see
     * availableTotal().
     */
    public function availableOn(?string $date, ?int $eventAvailable = null): ?int
    {
        if (! $this->isDated()) {
            return $this->availableTotal();
        }
        if ($date === null) {
            return null;
        }
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

    /**
     * Tickets left of an undated type (any_day / all_days): its own
     * `capacity` minus every ticket sold (active / used) and every seat held
     * by a pending gate order, whatever the day; null = unlimited. Not
     * limited by the event's daily capacity. 0 once the event has ended.
     */
    public function availableTotal(): ?int
    {
        $event = $this->relationLoaded('event') ? $this->event : Event::withoutTenantScope()->find($this->event_id);
        if ($event?->hasEnded()) {
            return 0;
        }
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->soldTotal() - $this->heldTotal());
    }

    public function soldTotal(): int
    {
        return (int) Ticket::withoutTenantScope()->where('ticket_type_id', $this->id)->whereIn('status', ['active', 'used'])->count();
    }

    public function heldTotal(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('box_office_order_lines as l')
            ->join('box_office_orders as o', 'o.id', '=', 'l.order_id')
            ->where('l.ticket_type_id', $this->id)
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
     * API shape (docs/ticket_types_contract.md §2, `validity` §7). `available`
     * is for $date (null = unlimited); for undated types it is the total left
     * whatever the date.
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
            'validity' => $this->validityValue(),
            'per_order_max' => $this->perOrderMax(),
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'requires_id' => (bool) $this->requires_id,
            'color' => $this->colorValue(),
            'available' => $date || ! $this->isDated() ? $this->availableOn($date, $eventAvailable) : null,
            'sales_open' => $this->salesOpen(),
            'sales_start' => TenantTime::iso($this->sales_start, $tenant),
            'sales_end' => TenantTime::iso($this->sales_end, $tenant),
            'visibility' => $this->visibility,
        ];
    }

    /** Upper bound of per_order_max in the admin (tickets of one type in one order). */
    public const PER_ORDER_LIMIT = 100;

    /** Most tickets of this type in one order (admin «الحد الأقصى في الطلب»). */
    public function perOrderMax(): int
    {
        return max(1, min(self::PER_ORDER_LIMIT, (int) $this->per_order_max));
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

    /**
     * Short validity label for staff and admin pages, e.g. «صالحة في جميع
     * أيام الفعالية» (lang/{ar,en}/platform.php `validity`).
     */
    public static function validityLabel(?string $validity, ?string $locale = null): string
    {
        return trans('platform.validity.'.self::normalizeValidity($validity), [], $locale);
    }

    public static function pickLocale(?array $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $value ??= [];
        $other = $locale === 'ar' ? 'en' : 'ar';

        return trim((string) (($value[$locale] ?? '') !== '' ? $value[$locale] : ($value[$other] ?? '')));
    }
}
