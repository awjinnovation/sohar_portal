<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillageImage extends Model
{
    protected $fillable = [
        'heritage_village_id', 
        'image_url', 
        'caption_en',
        'caption_ar',
        'display_order',
        'is_featured'
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_featured' => 'boolean'
    ];

    public function village()
    {
        return $this->belongsTo(HeritageVillage::class, 'heritage_village_id');
    }
}