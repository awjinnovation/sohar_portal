<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraditionalActivity extends Model
{
    protected $fillable = [
        'heritage_village_id',
        'activity_name_en',
        'activity_name_ar',
        'description_en',
        'description_ar',
        'image_url',
        'is_interactive',
        'age_recommendation',
        'timing',
        'is_active',
    ];

    protected $casts = [
        'is_interactive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function heritageVillage()
    {
        return $this->belongsTo(HeritageVillage::class);
    }
}
