<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use \App\Tenancy\BelongsToTenant;

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
     * Get available tickets for a specific date
     */
    public function getAvailableTicketsForDate(string $date): int
    {
        // Unlimited tickets
        if ($this->hasUnlimitedTickets()) {
            return -1; // Return -1 to indicate unlimited
        }

        // Get total per day
        $dailyCapacity = $this->total_tickets;

        // Calculate booked tickets for this date
        $bookedCount = $this->tickets()
            ->where('booking_date', $date)
            ->where('status', 'active')
            ->sum('quantity');

        return max(0, $dailyCapacity - $bookedCount);
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