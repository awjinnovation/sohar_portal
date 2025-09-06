<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulturalWorkshop extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'workshop_title_en',
        'workshop_title_ar',
        'description_en',
        'description_ar',
        'instructor_name',
        'instructor_bio_en',
        'instructor_bio_ar',
        'schedule',
        'duration_minutes',
        'max_participants',
        'min_age',
        'skill_level',
        'materials_included',
        'price',
        'booking_link',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
