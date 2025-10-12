<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Event extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'title', 'title_ar', 'description', 'description_ar', 'category_id',
        'start_time', 'end_time', 'location', 'location_ar', 'map_location_id',
        'image_url', 'images', 'price', 'currency', 'available_tickets', 'total_tickets',
        'organizer_name', 'organizer_name_ar', 'is_featured', 'is_active', 'pricing'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'price' => 'decimal:3',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'images' => 'array',
        'pricing' => 'array'
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

    public function ticketPricing()
    {
        return $this->hasMany(TicketPricing::class);
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
        return $query->where('start_time', '>', now());
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
        $bookingDate = \Carbon\Carbon::parse($date);
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