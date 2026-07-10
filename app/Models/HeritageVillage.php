<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeritageVillage extends Model
{
    protected $fillable = [
        'name_en', 'name_ar', 'description_en', 'description_ar', 'type',
        'cover_image', 'opening_hours', 'virtual_tour_url', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function images()
    {
        return $this->hasMany(VillageImage::class, 'heritage_village_id');
    }

    public function attractions()
    {
        return $this->hasMany(VillageAttraction::class, 'heritage_village_id');
    }

    public function craftDemonstrations()
    {
        return $this->hasMany(CraftDemonstration::class, 'heritage_village_id');
    }

    public function traditionalActivities()
    {
        return $this->hasMany(TraditionalActivity::class, 'heritage_village_id');
    }

    // NOTE: The cultural-workshops subsystem was removed (tables dropped in the
    // 2025_09_21 migration). The related model, controller and relationships
    // were deleted along with it.

    public function photoSpots()
    {
        return $this->hasMany(PhotoSpot::class, 'heritage_village_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}