<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoSpot extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'image_url',
        'best_time_for_photos',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function heritageVillage()
    {
        return $this->belongsTo(HeritageVillage::class);
    }

    public function photographyTips()
    {
        return $this->hasMany(PhotographyTip::class);
    }
}
