<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'description', 'description_ar', 'cuisine', 'cuisine_ar',
        'location', 'location_ar', 'latitude', 'longitude', 'rating', 'total_ratings',
        'price_range', 'image_url', 'phone', 'website', 'is_open', 'is_featured', 'is_active'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:1',
        'total_ratings' => 'integer',
        'is_open' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function images()
    {
        return $this->hasMany(RestaurantImage::class);
    }

    public function openingHours()
    {
        return $this->hasMany(RestaurantOpeningHour::class);
    }

    public function features()
    {
        return $this->hasMany(RestaurantFeature::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }
}