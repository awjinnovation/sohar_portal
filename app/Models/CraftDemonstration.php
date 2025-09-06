<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CraftDemonstration extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'craft_name_en',
        'craft_name_ar',
        'description_en',
        'description_ar',
        'artisan_name',
        'demonstration_times',
        'materials_used_en',
        'materials_used_ar',
        'historical_significance_en',
        'historical_significance_ar',
        'duration_minutes',
        'can_try_hands_on',
        'is_active'
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'can_try_hands_on' => 'boolean',
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
