<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillageAttraction extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'location_description_en',
        'location_description_ar',
        'visiting_hours',
        'accessibility_info_en',
        'accessibility_info_ar',
        'recommended_duration',
        'age_suitability',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function village()
    {
        return $this->belongsTo(HeritageVillage::class, 'heritage_village_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}