<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulturalWorkshop extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'instructor_name',
        'image_url',
        'duration_minutes',
        'max_participants',
        'price_omr',
        'skill_level',
        'is_active'
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'max_participants' => 'integer',
        'min_age' => 'integer',
        'materials_included' => 'boolean',
        'price' => 'decimal:3',
        'is_active' => 'boolean'
    ];

    public function village()
    {
        return $this->belongsTo(HeritageVillage::class, 'heritage_village_id');
    }

    public function schedules()
    {
        return $this->hasMany(\App\Models\WorkshopSchedule::class, 'workshop_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
