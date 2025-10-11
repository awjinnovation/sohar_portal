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
        'start_time', 'end_time', 'location', 'location_ar', 'latitude', 'longitude',
        'image_url', 'images', 'price', 'currency', 'available_tickets', 'total_tickets',
        'organizer_name', 'organizer_name_ar', 'is_featured', 'is_active', 'pricing'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }
}