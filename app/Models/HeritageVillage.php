<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Heritage village — a simplified, self-contained model matching the
 * recreated heritage_villages table (see the 2025_11_10 migration). The old
 * attractions/images/craft relations were dropped with the "simplify"
 * migration; images/features now live in JSON columns on this table.
 */
class HeritageVillage extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'description', 'description_ar',
        'image', 'images', 'location', 'location_ar',
        'latitude', 'longitude', 'opening_hours', 'features',
        'historical_period', 'is_featured', 'is_active', 'status',
    ];

    protected $casts = [
        'images' => 'array',
        'features' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
