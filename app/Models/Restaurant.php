<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'description', 'description_ar', 'cuisine', 'cuisine_ar',
        'location', 'location_ar', 'latitude', 'longitude', 'rating', 'total_ratings',
        'price_range', 'image_url', 'phone', 'website', 'is_open', 'is_featured', 'is_active',
        'features', 'images', 'opening_hours', 'menu_url', 'has_delivery', 'has_parking', 'has_wifi'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:1',
        'total_ratings' => 'integer',
        'is_open' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'features' => 'array',
        'images' => 'array',
        'opening_hours' => 'array',
        'has_delivery' => 'boolean',
        'has_parking' => 'boolean',
        'has_wifi' => 'boolean'
    ];

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